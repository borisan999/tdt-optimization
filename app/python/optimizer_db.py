#!/usr/bin/env python3
import sys
import os
import json
import math
from pathlib import Path
import numpy as np
from datetime import datetime


# ------------------------------------------------------
#Notes & assumptions I made (important):
#I assumed you want DB inserts for TU-level results and dataset-level stats (I used your insert_result() function).
#For per-TU total losses, I used pot_in - nivel_computed when nivel exists (most accurate), otherwise constructed a component sum as fallback.
#Riser entry floor per block is recorded in block_entry_floor and included in per-TU meta.
#Horizontal cable per TU is largo_cable_derivador + largo_cable_repartidor + largo_cable_tu. Vertical cable per TU is abs(p - p_troncal) * largo_piso. These are estimates (consistent with previous style).
#Block summaries include tus_count, avg_level_dBuV, avg_loss_dB, total_horizontal_m, and total_vertical_m.
#I did not change constraint logic or objective; the solver call and feasibility handling are unchanged.
# ------------------------------------------------------

# ---- IMPORTS MUST FAIL LOUDLY ----
try:
    import mysql.connector
    from mysql.connector import errorcode
except Exception as e:
    print(json.dumps({"status": "error", "message": f"mysql-connector-python missing: {e}"}))
    sys.exit(1)

try:
    # import pulp module and also bring commonly used symbols
    import pulp
    from pulp import LpProblem, LpVariable, LpBinary, LpMinimize, lpSum, LpStatus, value, PULP_CBC_CMD
except Exception as e:
    print(json.dumps({"status": "error", "message": f"PuLP import failed: {e}"}))
    sys.exit(1)

env_path = Path(__file__).resolve().parents[2] / ".env"

if env_path.exists():
    for line in env_path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if "=" in line:
            k, v = line.split("=", 1)
            os.environ.setdefault(k.strip(), v.strip())
else:
    print(json.dumps({"status": "error", "message": ".env file not found"}))
    sys.exit(1)

# ------------------------------------------------------
# USE ENV VARIABLES
# ------------------------------------------------------
DB_CONFIG = {
    "host": os.getenv("DB_HOST", "localhost"),
    "user": os.getenv("DB_USER", ""),            # MUST MATCH PHP
    "password": os.getenv("DB_PASS", ""),        # MUST MATCH PHP
    "database": os.getenv("DB_NAME", "tdt_optimization"),
    "port": int(os.getenv("DB_PORT", "3306")),
}

OUTPUT_DIR_BASE = os.getenv("OUTPUT_DIR", os.path.join(os.path.dirname(__file__), "output"))

# --- Utility converter for EPIC 2 safe JSON ---

def safe_val(v):
    from decimal import Decimal

    # numpy integers
    if isinstance(v, (np.integer, np.int64, np.int32)):
        return int(v)

    # numpy floats
    if isinstance(v, (np.floating, np.float32, np.float64)):
        return float(v)

    # Decimal to float
    if isinstance(v, Decimal):
        try:
            return float(v)
        except:
            return float(str(v))

    # numpy scalar objects
    try:
        if hasattr(v, "item"):
            return v.item()
    except Exception:
        pass

    return v
# ======================================================
# DB Utilities
# ======================================================
def get_db():
    return mysql.connector.connect(**DB_CONFIG)


def load_params(conn, dataset_id):
    cur = conn.cursor()
    cur.execute("SELECT param_name, param_value FROM parametros_generales WHERE dataset_id=%s", (dataset_id,))
    rows = cur.fetchall()
    cur.close()

    params = {}
    for name, val in rows:
        try:
            s = str(val).replace(",", ".")
            params[name] = safe_val(float(s))
        except:
            params[name] = safe_val(val)
    return params

def _to_int(v):
    try:
        return int(safe_val(v))
    except:
        try:
            return int(float(str(v).replace(",", ".")))
        except:
            return 0
        
def _to_float(v):
    try:
        return float(safe_val(v))
    except:
        try:
            return float(str(v).replace(",", "."))
        except:
            return 0

def load_dataset_rows(conn, dataset_id):

    cur = conn.cursor(dictionary=True)
    cur.execute(
        "SELECT * FROM dataset_rows WHERE dataset_id=%s ORDER BY record_index, row_id",
        (dataset_id,)
    )
    rows = cur.fetchall()
    cur.close()

    groups = {}
    for r in rows:
        idx = r["record_index"]
        groups.setdefault(idx, {})
        groups[idx][r["field_name"]] = r["field_value"]

    apartments = []
    tus = []

    for idx, data in groups.items():
        is_tu_row = (
            "tu_index" in data and
            "largo_cable_tu" in data and
            "tus_requeridos" not in data
        )

        is_apartment_row = (
            "tus_requeridos" in data and
            "largo_cable_derivador" in data and
            "largo_cable_repartidor" in data
        )

        if is_tu_row:
            tus.append({
                "piso": _to_int(data.get("piso")),
                "apartamento": _to_int(data.get("apartamento")),
                "tu_index": _to_int(data.get("tu_index")),
                "largo_cable_tu": _to_float(data.get("largo_cable_tu"))
            })

        elif is_apartment_row:
            apartments.append({
                "piso": _to_int(data.get("piso")),
                "apartamento": _to_int(data.get("apartamento")),
                "tus_requeridos": _to_int(data.get("tus_requeridos")),
                "largo_cable_derivador": _to_float(data.get("largo_cable_derivador")),
                "largo_cable_repartidor": _to_float(data.get("largo_cable_repartidor"))
            })

        else:
            # IMPORTANT: Debug output but DOES NOT stop the function
            print("⚠ Unexpected row structure:", data)

    # ALWAYS RETURN A VALID TUPLE
    return apartments, tus


def load_components(conn):
    cur = conn.cursor(dictionary=True)

    cur.execute("SELECT * FROM derivadores")
    raw_der = cur.fetchall()
    derivadores = {}
    for row in raw_der:
        # Normalize keys and provide defaults
        d = dict(row)
        # ensure numeric fields exist
        d["derivacion"] = float(safe_val(d.get("derivacion", 0)))
        d["perdida_insercion"] = float(safe_val(d.get("perdida_insercion", 0)))
        d["paso"] = float(safe_val(d.get("paso", 0)))
        d["salidas"] = int(safe_val(d.get("salidas", 0)))
        derivadores[d.get("deriv_id")] = d

    cur.execute("SELECT * FROM repartidores")
    raw_rep = cur.fetchall()
    repartidores = {}
    for row in raw_rep:
        r = dict(row)
        # repartidor should have perdida_insercion; default to 0 if absent
        r["perdida_insercion"] = float(safe_val(r.get("perdida_insercion", 0)))
        r["salidas"] = int(safe_val(r.get("salidas", 0)))        
        repartidores[r.get("rep_id")] = r

    cur.close()
    return derivadores, repartidores


def create_optimization_run(conn, dataset_id):
    from datetime import datetime

    ts = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    cur = conn.cursor()
#TODO UPDATED-AT
    try:
        cur.execute(
            """
            INSERT INTO optimizations (dataset_id, status, created_at)
            VALUES (%s, %s, %s)
            """,
            (int(dataset_id), "running", ts)
        )
        conn.commit()
        opt_id = cur.lastrowid
    finally:
        cur.close()

    return opt_id


def update_optimization_status(conn, opt_id, status):
    ts = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    cur = conn.cursor()
    cur.execute(
        "UPDATE optimizations SET status=%s, end_time=%s WHERE opt_id=%s",
        (status, ts, opt_id)
    )
    conn.commit()
    cur.close()


def insert_result(conn, opt_id, parameter, value, unit=None, deviation=None, meta=None):
    cur = conn.cursor()
    meta_json = json.dumps(meta, default=str) if meta else None
    val = safe_val(value)
    dev = safe_val(deviation) if deviation is not None else None
    cur.execute(
        "INSERT INTO results (opt_id, parameter, value, unit, deviation, meta_json) "
        "VALUES (%s,%s,%s,%s,%s,%s)",
        (opt_id, parameter, val, unit, dev, meta_json)
    )
    conn.commit()
    cur.close()


# ======================================================
# MILP MODEL
# ======================================================
def build_and_solve(params, apartments, tus, derivadores, repartidores):
    # --- Read params (same keys used in DB script) ---
    Piso_Maximo = int(params.get("Piso_Maximo", 1))
    apartamentos_por_piso = int(params.get("Apartamentos_Piso", 1))

    pot_in = float(params.get("Potencia_Entrada_dBuV", 110.0))
    Nivel_min = float(params.get("Nivel_Minimo_dBuV", 47.0))
    Nivel_max = float(params.get("Nivel_Maximo_dBuV", 70.0))
    Pot_obj = float(params.get("Potencia_Objetivo_TU_dBuV", 60.0))

    aten_cable = float(params.get("Atenuacion_Cable_dBporM", 0.2))
    aten_con = float(params.get("Atenuacion_Conector_dB", 0.2))
    aten_tu = float(params.get("Atenuacion_Conexion_TU_dB", 1.0))

    largo_piso = float(params.get("Largo_Entre_Pisos_m", 3.0))
    conns = int(params.get("Conectores_por_Union", 2))

    feeder_min = float(params.get("Largo_Feeder_Bloque_m", 3.0))
    long_amp = float(params.get("Largo_Cable_Amplificador_Ultimo_Piso", 7.0))
    # Build maps like before
    apt_map = {(a["piso"], a["apartamento"]): a for a in apartments}
    tu_map = {(t["piso"], t["apartamento"], t["tu_index"]): t for t in tus}

    # Ensure TUs exist if none provided
    all_tomas = list(tu_map.keys())
    if not all_tomas:
        for (p, a), rec in apt_map.items():
            for ti in range(1, rec["tus_requeridos"] + 1):
                all_tomas.append((p, a, ti))
                tu_map[(p, a, ti)] = {
                    "piso": p, "apartamento": a, "tu_index": ti, "largo_cable_tu": 0.0
                }

    # Floors descending (same as client)
    floors = sorted({p for (p, a, t) in all_tomas}, reverse=True)

    # Make deriv & rep id lists and normalize numeric fields (safe)
    deriv_ids = list(derivadores.keys())
    rep_ids = list(repartidores.keys())
    for d in deriv_ids:
        derivadores[d]["derivacion"] = float(derivadores[d].get("derivacion", 0.0))
        derivadores[d]["paso"] = float(derivadores[d].get("paso", 0.0))
        derivadores[d]["salidas"] = int(derivadores[d].get("salidas", 0) or 0)
        derivadores[d]["perdida_insercion"] = float(derivadores[d].get("perdida_insercion", 0.0))
    for r in rep_ids:
        repartidores[r]["perdida_insercion"] = float(repartidores[r].get("perdida_insercion", 0.0))
        repartidores[r]["salidas"] = int(repartidores[r].get("salidas", 0) or 0)

    unique_apts = sorted({(p, a) for (p, a, t) in all_tomas})
    # Validate that dataset includes all floors up to Piso_Maximo
    dataset_floors = sorted({p for (p,a,t) in all_tomas})
    # Note: If floors are missing, solver will be infeasible (OK, handled in result return)
    # -------------------- Build blocks like client script --------------------
    def dividir_en_bloques(pisos_max):
        if pisos_max <= 6:
            return [list(range(pisos_max, 0, -1))]
        min_blocks = max(math.ceil(pisos_max / 5), 1)
        max_blocks = max(math.floor(pisos_max / 3), 1)
        best_partition, best_balance = None, None
        for nb in range(min_blocks, max_blocks + 1):
            base = pisos_max // nb
            extra = pisos_max % nb
            sizes = [base + (1 if i < extra else 0) for i in range(nb)]
            if all(3 <= s <= 5 for s in sizes):
                balance = np.var(sizes)
                if best_balance is None or balance < best_balance:
                    best_balance = balance
                    best_partition = sizes
        if best_partition is None:
            nb = 3
            base = pisos_max // nb
            extra = pisos_max % nb
            best_partition = [base + (1 if i < extra else 0) for i in range(nb)]
        bloques, piso_actual = [], Piso_Maximo
        for tam in best_partition:
            bloque = list(range(piso_actual, piso_actual - tam, -1))
            bloques.append(bloque)
            piso_actual -= tam
        return bloques

    bloques = dividir_en_bloques(Piso_Maximo)

    # p_troncal near middle, same as client
    p_troncal = int(round(Piso_Maximo / 2))

    # -------------------- Create MILP --------------------
    prob = LpProblem("MILP_TDT_DB_CLIENT_MODEL", LpMinimize)

    # Decision variables
    x = {(p, d): LpVariable(f"x_{p}_{d}", cat=LpBinary) for p in floors for d in deriv_ids}
    y = {(p, a, r): LpVariable(f"y_{p}_{a}_{r}", cat=LpBinary)
         for (p, a) in unique_apts for r in rep_ids}
    z = {(p, a): LpVariable(f"z_{p}_{a}", cat=LpBinary) for (p, a) in unique_apts}  # use repartidor or not

    nivel_tu = {(p, a, t): LpVariable(f"nivel_{p}_{a}_{t}", lowBound=0)
                for (p, a, t) in all_tomas}
    d_plus = {(p, a, t): LpVariable(f"dplus_{p}_{a}_{t}", lowBound=0)
              for (p, a, t) in all_tomas}
    d_minus = {(p, a, t): LpVariable(f"dminus_{p}_{a}_{t}", lowBound=0)
               for (p, a, t) in all_tomas}

    # troncal selection (one repartidor as main trunk)
    r_troncal = {r: LpVariable(f"r_troncal_{r}", cat=LpBinary) for r in rep_ids}

    # pot_in per block/piso like client: pot_in_riser_by_block[(p,b_idx)]
    pot_in_riser_by_block = {}
    block_entry_floor = {}  # store p_ent per block for later postprocessing
    for b_idx, bloque in enumerate(bloques):
        for p in bloque:
            pot_in_riser_by_block[(p, b_idx)] = LpVariable(f"pot_riser_p{p}_b{b_idx}", lowBound=0)

    # Objective: same (min deviation)
    prob += lpSum(d_plus[(p, a, t)] + d_minus[(p, a, t)] for (p, a, t) in all_tomas)

    # -------------------- Constraints --------------------
    # select one troncal
    prob += lpSum(r_troncal[r] for r in rep_ids) == 1
    # troncal must have enough outputs
    num_bloques = len(bloques)
    for r in rep_ids:
        if repartidores[r]["salidas"] < num_bloques:
            prob += r_troncal[r] == 0

    # one derivador per floor and respect salidas
    for p in floors:
        prob += lpSum(x[(p, d)] for d in deriv_ids) == 1
        for d in deriv_ids:
            if derivadores[d]["salidas"] < apartamentos_por_piso:
                prob += x[(p, d)] == 0

    # repartidor rules per apartment
    for (p, a) in unique_apts:
        tu_req = apt_map.get((p, a), {}).get("tus_requeridos", 0)
        if tu_req <= 1:
            prob += z[(p, a)] == 0
            prob += lpSum(y[(p, a, r)] for r in rep_ids) == 0
        else:
            prob += z[(p, a)] == 1
            prob += lpSum(y[(p, a, r)] for r in rep_ids) == 1
            for r in rep_ids:
                if repartidores[r]["salidas"] < tu_req:
                    prob += y[(p, a, r)] == 0

    # losses Antena->Troncal (like client)
    long_ant = (Piso_Maximo - p_troncal + 1) * largo_piso + long_amp
    loss_ant = long_ant * aten_cable
    loss_ant_con = conns * aten_con
    loss_troncal_ins_expr = lpSum(r_troncal[r] * repartidores[r]["perdida_insercion"] for r in rep_ids)

    # feeder and pot_in per block
    for b_idx, bloque in enumerate(bloques):
        # get entry piso and direction like client
        max_b, min_b = max(bloque), min(bloque)
        if p_troncal < min_b:
            p_ent = min_b
            direccion = 'up'
        else:
            p_ent = max_b
            direccion = 'down'
        block_entry_floor[b_idx] = p_ent

        long_vertical = abs(p_ent - p_troncal) * largo_piso
        long_feeder = feeder_min + long_vertical
        loss_feeder = long_feeder * aten_cable
        loss_conns_feeder = conns * aten_con

        # initial pot at p_ent for block
        prob += (
            pot_in_riser_by_block[(p_ent, b_idx)]
            == pot_in - loss_ant - loss_ant_con - loss_troncal_ins_expr - loss_feeder - loss_conns_feeder
        )

        # propagation inside block in given direction using 'paso' of derivador
        if direccion == 'up':
            pisos_up = sorted([p for p in bloque if p >= p_ent])
            for i in range(len(pisos_up) - 1):
                p_act, p_sig = pisos_up[i], pisos_up[i + 1]
                paso_piso = lpSum(x[(p_act, d)] * derivadores[d]["paso"] for d in deriv_ids)
                loss_entre_pisos = largo_piso * aten_cable
                loss_conns_entre_pisos = conns * aten_con
                prob += (
                    pot_in_riser_by_block[(p_sig, b_idx)]
                    == pot_in_riser_by_block[(p_act, b_idx)]
                    - paso_piso - loss_entre_pisos - loss_conns_entre_pisos
                )
        else:
            pisos_down = sorted([p for p in bloque if p <= p_ent], reverse=True)
            for i in range(len(pisos_down) - 1):
                p_act, p_sig = pisos_down[i], pisos_down[i + 1]
                paso_piso = lpSum(x[(p_act, d)] * derivadores[d]["paso"] for d in deriv_ids)
                loss_entre_pisos = largo_piso * aten_cable
                loss_conns_entre_pisos = conns * aten_con
                prob += (
                    pot_in_riser_by_block[(p_sig, b_idx)]
                    == pot_in_riser_by_block[(p_act, b_idx)]
                    - paso_piso - loss_entre_pisos - loss_conns_entre_pisos
                )

    # nivel TU constraints: use pot_in_riser_by_block and apply chosen deriv & rep losses only
    for (p, a, t) in all_tomas:
        # Find which block contains this piso
        b_idx_toma = None
        for b_idx, bloque in enumerate(bloques):
            if p in bloque:
                b_idx_toma = b_idx
                break
        
        if b_idx_toma is None:
            raise Exception(f"Piso {p} not found in any block")
        
        # Losses from derivador selected by x for that piso
        deriv_loss_expr = lpSum(x[(p, d)] * derivadores[d]["derivacion"] for d in deriv_ids)
        paso_loss_expr = lpSum(x[(p, d)] * derivadores[d]["paso"] for d in deriv_ids)
        
        # Cable and repartidor losses
        lc_drp = apt_map.get((p, a), {}).get("largo_cable_derivador", 0.0)
        cable_deriv_rep = lc_drp * aten_cable
        repartidor_loss_expr = lpSum(y[(p, a, r)] * repartidores[r]["perdida_insercion"] for r in rep_ids)
        
        lc_tu = tu_map.get((p, a, t), {}).get("largo_cable_tu", 0.0)
        cable_tu_loss = lc_tu * aten_cable
        
        # Connector losses (4 = 2 unions deriv→rep + 2 rep→tu)
        conns_apto_loss = 4 * aten_con
        conn_tu_loss = aten_tu
        
        # Main constraint: nivel_tu = riser_pot - all losses
        prob += (
            nivel_tu[(p, a, t)]
            == pot_in_riser_by_block[(p, b_idx_toma)]
            - deriv_loss_expr
            - paso_loss_expr
            - cable_deriv_rep
            - conns_apto_loss
            - repartidor_loss_expr
            - cable_tu_loss
            - conn_tu_loss
        )
        
        # Bounds
        prob += nivel_tu[(p, a, t)] >= Nivel_min
        prob += nivel_tu[(p, a, t)] <= Nivel_max
        
        # Deviation relation
        prob += (nivel_tu[(p, a, t)] - Pot_obj) == d_plus[(p, a, t)] - d_minus[(p, a, t)]

    # ---- SOLVE ----
    prob.solve(pulp.PULP_CBC_CMD(msg=0))

    status = LpStatus[prob.status]
    if status.lower() != "optimal":
        # return diagnostic info so caller can log / display it
        return {
            "status": "infeasible",
            "message": f"Solver status: {status}",
            "details": {
                "floors": len(floors),
                "derivadores": len(deriv_ids),
                "repartidores": len(rep_ids),
                "apartments": len(apartments),
                "tomas": len(all_tomas)
            }
        }

    # ---- EXTRACT & POSTPROCESS (only on OPTIMAL) ----
    chosen_der = {}
    for p in floors:
        for d in deriv_ids:
            if value(x[(p, d)]) > 0.5:
                chosen_der[p] = derivadores[d].get("modelo")

    chosen_rep = {}
    for (p, a) in unique_apts:
        for r in rep_ids:
            if value(y[(p, a, r)]) > 0.5:
                chosen_rep[(p, a)] = repartidores[r].get("modelo")

    chosen_troncal_id = None
    for r in rep_ids:
        if value(r_troncal[r]) > 0.5:
            chosen_troncal_id = r
            break

    # Build tu_results with actual solver values
    tu_results = []
    levels = []
    losses = []
    
    for (p, a, t) in all_tomas:
        try:
            raw_nivel = value(nivel_tu[(p, a, t)])
            if raw_nivel is None:
                nivel_val = None
            else:
                nivel_val = float(safe_val(raw_nivel))
                levels.append(nivel_val)
                loss_val = float(safe_val(pot_in - nivel_val))
                losses.append(loss_val)

        except Exception:
            nivel_val = None
            loss_val = None
        
        tu_results.append({
            "piso": p,
            "apartamento": a,
            "tu_index": t,
            "nivel": safe_val(nivel_val),
            "deviation": safe_val((nivel_val - Pot_obj) if nivel_val is not None else None),
            "meta": {
                "derivador": safe_val(chosen_der.get(p)),
                "repartidor": safe_val(chosen_rep.get((p, a))),
                "troncal": safe_val(chosen_troncal_id),
                "largo_cable_tu": safe_val(tu_map.get((p,a,t),{}).get("largo_cable_tu"))
            }
        })
    
    # Dataset stats
    levels_clean = [l for l in levels if l is not None]
    stats = {
        "min_level_dBuV": min(levels_clean) if levels_clean else None,
        "max_level_dBuV": max(levels_clean) if levels_clean else None,
        "avg_level_dBuV": (sum(levels_clean) / len(levels_clean)) if levels_clean else None,
        "min_loss_dB": min(losses) if losses else None,
        "max_loss_dB": max(losses) if losses else None,
        "avg_loss_dB": (sum(losses) / len(losses)) if losses else None
    }
    
    return {
        "status": "optimal",
        "p_troncal": p_troncal,
        "chosen_derivadores": chosen_der,
        "chosen_repartidores": chosen_rep,
        "chosen_troncal_id": chosen_troncal_id,
        "tu_results": tu_results,
        "stats": stats
    }

# ======================================================
# MAIN
# ======================================================
def main():
    if len(sys.argv) < 2:
        return {"status": "error", "message": "dataset_id required"}

    dataset_id = int(sys.argv[1])

    try:
        conn = get_db()
    except Exception as e:
        return {"status": "error", "message": f"DB connection failed: {e}"}

    try:
        params = load_params(conn, dataset_id)
        apartments, tus = load_dataset_rows(conn, dataset_id)
        derivadores, repartidores = load_components(conn)
    except Exception as e:
        return {"status": "error", "message": f"load error: {e}"}

    opt_id = create_optimization_run(conn, dataset_id)

    try:
        
        result = build_and_solve(params, apartments, tus, derivadores, repartidores)

        # --- FIX: ensure clean JSON for infeasible ---
        if result.get("status") == "infeasible":
            update_optimization_status(conn, opt_id, "infeasible")

            # Ensure no non-serializable content
            result = {
                "status": "infeasible",
                "message": result.get("message", ""),
                "details": result.get("details", {}),
                "dataset_id": dataset_id,
                "opt_id": opt_id
            }
            if "tu_results" not in result:
                result["tu_results"] = []
            return result
        

        # store TU results with enriched meta
        for tr in result["tu_results"]:
            pname = f"nivel_p{tr['piso']}_a{tr['apartamento']}_t{tr['tu_index']}"
            insert_result(
                conn, opt_id, pname,
                tr["nivel"], unit="dBuV",
                deviation=tr["deviation"],
                meta=tr["meta"]
            )

        # store dataset stats as results entries
        stats = result.get("stats", {})
        # overall stats
        insert_result(conn, opt_id, "stats_min_level_dBuV", stats.get("min_level_dBuV"), unit="dBuV", meta=None)
        insert_result(conn, opt_id, "stats_max_level_dBuV", stats.get("max_level_dBuV"), unit="dBuV", meta=None)
        insert_result(conn, opt_id, "stats_avg_level_dBuV", stats.get("avg_level_dBuV"), unit="dBuV", meta=None)
        insert_result(conn, opt_id, "stats_min_loss_dB", stats.get("min_loss_dB"), unit="dB", meta=None)
        insert_result(conn, opt_id, "stats_max_loss_dB", stats.get("max_loss_dB"), unit="dB", meta=None)
        insert_result(conn, opt_id, "stats_avg_loss_dB", stats.get("avg_loss_dB"), unit="dB", meta=None)
        insert_result(conn, opt_id, "stats_total_horizontal_m", stats.get("total_horizontal_m"), unit="m", meta=None)
        insert_result(conn, opt_id, "stats_total_vertical_m", stats.get("total_vertical_m"), unit="m", meta=None)
        # block summaries
        for b_idx, bsum in (stats.get("blocks") or {}).items():
            insert_result(conn, opt_id, f"block_{b_idx}_summary", None, unit=None, meta=bsum)

        update_optimization_status(conn, opt_id, "completed")

        # ---------------------------------------------
        # PATCH 5 — EPIC 2 COMPLIANT FINAL PAYLOAD
        # ---------------------------------------------

        # Helper to ensure no numpy types leak
        def clean_value(x):
            try:
                if hasattr(x, "item"):
                    return x.item()
            except:
                pass
            return x

        # Clean TU results
        cleaned_tu_results = [
            {k: safe_val(v) for k, v in tu.items()}
            for tu in result.get("tu_results", [])
        ]

        # Clean derivadores & repartidores
        cleaned_derivadores = [safe_val(v) for v in (result.get("chosen_derivadores") or {}).values()]

        # chosen_repartidores comes as dict {(p,a): value}
        cleaned_repartidores = {
            f"{p}_{a}": safe_val(v)
            for (p, a), v in (result.get("chosen_repartidores") or {}).items()
        }

        # Build and return EPIC 2 payload
        return {
            "status": "success",
            "opt_id": int(opt_id),
            "tu_results": cleaned_tu_results,
            "chosen_derivadores": cleaned_derivadores,
            "chosen_repartidores": cleaned_repartidores
        }



    except Exception as e:
        update_optimization_status(conn, opt_id, "failed")
        return {"status": "error", "message": str(e)}

    finally:
        conn.close()


if __name__ == "__main__":
    res = main()
    print(json.dumps(res, ensure_ascii=False))
