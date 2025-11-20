#!/usr/bin/env python3
import sys
import os
import json
import math
import numpy as np
from datetime import datetime

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


# ------------------------------------------------------
# CONFIG
# ------------------------------------------------------
DB_CONFIG = {
    "host": "localhost",
    "user": "tdt_user",
    "password": "00N80r!B7032B",
    "database": "tdt_optimization",
    "port": 3306
}

OUTPUT_DIR_BASE = os.path.join(os.path.dirname(__file__), "output")


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
            params[name] = float(s)
        except:
            params[name] = val
    return params


def load_dataset_rows(conn, dataset_id):
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT * FROM dataset_rows WHERE dataset_id=%s ORDER BY record_index, row_id", (dataset_id,))
    rows = cur.fetchall()
    cur.close()

    groups = {}
    for r in rows:
        idx = r["record_index"]
        groups.setdefault(idx, {})
        groups[idx][r["field_name"]] = r["field_value"]

    apartments, tus = [], []
    for idx, data in groups.items():
        if "tu_index" in data:
            tus.append({
                "piso": int(data.get("piso") or 0),
                "apartamento": int(data.get("apartamento") or 0),
                "tu_index": int(data.get("tu_index") or 0),
                "largo_cable_tu": float(data.get("largo_cable_tu") or 0)
            })
        else:
            apartments.append({
                "piso": int(data.get("piso") or 0),
                "apartamento": int(data.get("apartamento") or 0),
                "tus_requeridos": int(data.get("tus_requeridos") or 0),
                "largo_cable_derivador": float(data.get("largo_cable_derivador") or 0),
                "largo_cable_repartidor": float(data.get("largo_cable_repartidor") or 0)
            })

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
        d["derivacion"] = float(str(d.get("derivacion", 0) or 0))
        # If DB previously had perdida_insercion here, keep it but default 0
        d["perdida_insercion"] = float(str(d.get("perdida_insercion", 0) or 0))
        derivadores[d.get("deriv_id")] = d

    cur.execute("SELECT * FROM repartidores")
    raw_rep = cur.fetchall()
    repartidores = {}
    for row in raw_rep:
        r = dict(row)
        # repartidor should have perdida_insercion; default to 0 if absent
        r["perdida_insercion"] = float(str(r.get("perdida_insercion", 0) or 0))
        repartidores[r.get("rep_id")] = r

    cur.close()
    return derivadores, repartidores


def create_optimization_run(conn, dataset_id):
    ts = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO optimizations (dataset_id, status, created_at) VALUES (%s,%s,%s)",
        (dataset_id, "running", ts)
    )
    conn.commit()
    opt_id = cur.lastrowid
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
    meta_json = json.dumps(meta) if meta else None
    cur.execute(
        "INSERT INTO results (opt_id, parameter, value, unit, deviation, meta_json) "
        "VALUES (%s,%s,%s,%s,%s,%s)",
        (opt_id, parameter, str(value), unit, deviation, meta_json)
    )
    conn.commit()
    cur.close()


# ======================================================
# MILP MODEL
# ======================================================
def build_and_solve(params, apartments, tus, derivadores, repartidores):
    # --- Read params (same keys used in DB script) ---
    Piso_Maximo = int(params.get("Piso_Maximo") or 1)
    apartamentos_por_piso = int(params.get("Apartamentos_Piso") or 1)

    pot_in = float(params.get("Potencia_Entrada_dBuV") or params.get("potencia_entrada", 110.0))
    Nivel_min = float(params.get("Nivel_Minimo_dBuV") or params.get("Nivel_minimo", 47.0))
    Nivel_max = float(params.get("Nivel_Maximo_dBuV") or params.get("Nivel_maximo", 70.0))
    Pot_obj = float(params.get("Potencia_Objetivo_TU_dBuV") or params.get("Potencia_Objetivo_TU", 60.0))

    aten_cable = float(params.get("Atenuacion_Cable_dBporM") or params.get("atenuacion_cable_por_metro", 0.2))
    aten_con = float(params.get("Atenuacion_Conector_dB") or params.get("atenuacion_conector", 0.2))
    aten_tu = float(params.get("Atenuacion_Conexion_TU_dB") or params.get("atenuacion_conexion_tu", 1.0))

    largo_piso = float(params.get("Largo_Entre_Pisos_m") or params.get("largo_cable_entre_pisos", 3.0))
    conns = int(params.get("Conectores_por_Union") or params.get("conectores_por_union", 2))
    feeder_min = float(params.get("Largo_Feeder_Bloque_m (Mínimo)") or params.get("largo_cable_feeder_bloque", 3.0))
    long_amp = float(params.get("Largo_Cable_Amplificador_Ultimo_Piso") or params.get("largo_cable_amplificador_ultimo_piso", 7.0))

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
    missing = [p for p in range(1, Piso_Maximo+1) if p not in dataset_floors]

    if missing:
        return {"status": "error", "message": f"Dataset missing floors: {missing}. Provided pisos: {dataset_floors}"}
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
    loss_troncal_ins = lpSum(r_troncal[r] * repartidores[r]["perdida_insercion"] for r in rep_ids)

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

        long_vertical = abs(p_ent - p_troncal) * largo_piso
        long_feeder = feeder_min + long_vertical
        loss_feeder = long_feeder * aten_cable
        loss_conns_feeder = conns * aten_con

        # initial pot at p_ent for block
        prob += (
            pot_in_riser_by_block[(p_ent, b_idx)]
            == pot_in - loss_ant - loss_ant_con - loss_troncal_ins - loss_feeder - loss_conns_feeder
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
        b_idx_toma = next(b for b, bloque in enumerate(bloques) if p in bloque)
        # deriv loss selected by x for that piso
        deriv_loss = lpSum(x[(p, d)] * derivadores[d]["derivacion"] for d in deriv_ids)
        paso_loss = lpSum(x[(p, d)] * derivadores[d]["paso"] for d in deriv_ids)
        lc_drp = apt_map.get((p, a), {}).get("largo_cable_derivador", 0.0)
        cable_deriv_rep = lc_drp * aten_cable
        repartidor_loss = lpSum(y[(p, a, r)] * repartidores[r]["perdida_insercion"] for r in rep_ids)
        lc_tu = tu_map.get((p, a, t), {}).get("largo_cable_tu", 0.0)
        cable_tu_loss = lc_tu * aten_cable
        conns_apto_loss = 4 * aten_con
        conn_tu_loss = aten_tu

        # nivel_tu uses pot_in at actual piso p (pot_in_riser_by_block)
        prob += (
            nivel_tu[(p, a, t)]
            == pot_in_riser_by_block[(p, b_idx_toma)]
            - deriv_loss
            - cable_deriv_rep - conns_apto_loss
            - repartidor_loss
            - cable_tu_loss
            - conn_tu_loss
        )

        prob += nivel_tu[(p, a, t)] >= Nivel_min
        prob += nivel_tu[(p, a, t)] <= Nivel_max
        prob += (nivel_tu[(p, a, t)] - Pot_obj) == d_plus[(p, a, t)] - d_minus[(p, a, t)]

    # -------------------- Solve --------------------
    prob.solve(pulp.PULP_CBC_CMD(msg=0))

    status = LpStatus[prob.status]
    if status != "Optimal":
        # produce diagnostic similar to earlier: optimistic estimate (min deriv & min rep)
        diagnostics = []
        sample_tomas = list(all_tomas)[:8]
        for (p, a, t) in sample_tomas:
            lc_drp = apt_map.get((p, a), {}).get("largo_cable_derivador", 0)
            lc_tu = tu_map.get((p, a, t), {}).get("largo_cable_tu", 0)
            min_deriv = min((derivadores[d]["derivacion"] for d in deriv_ids), default=0)
            min_rep = min((repartidores[r]["perdida_insercion"] for r in rep_ids), default=0)
            long_vertical = abs(p - p_troncal) * largo_piso
            long_feeder = feeder_min + long_vertical
            loss_est = (long_ant * aten_cable) + (conns * aten_con) + min_deriv + (lc_drp * aten_cable) + (conns * aten_con) + min_rep + (lc_tu * aten_cable) + aten_tu + (long_feeder * aten_cable) + (conns * aten_con)
            est_nivel = pot_in - loss_est
            diagnostics.append({
                "piso": p, "apt": a, "tu": t,
                "est_nivel_dBuV": round(est_nivel, 2),
                "min_deriv_dB": round(min_deriv, 3),
                "min_rep_dB": round(min_rep, 3),
                "largo_cable_derivador_m": lc_drp,
                "largo_cable_tu_m": lc_tu
            })
        print(json.dumps({"status": "infeasible_diagnostic", "diag_sample": diagnostics}))
        return None

    # --- Extract results like earlier DB code ---
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

    tu_results = []
    for (p, a, t) in all_tomas:
        tu_results.append({
            "piso": p,
            "apartamento": a,
            "tu_index": t,
            "nivel": float(value(nivel_tu[(p, a, t)])),
            "deviation": float(value(d_plus[(p, a, t)] + d_minus[(p, a, t)])),
            "meta": {
                "derivador": chosen_der.get(p),
                "repartidor": chosen_rep.get((p, a)),
                "largo_cable_tu": tu_map[(p, a, t)]["largo_cable_tu"]
            }
        })

    return {
        "status": "optimal",
        "p_troncal": p_troncal,
        "chosen_derivadores": chosen_der,
        "chosen_repartidores": chosen_rep,
        "tu_results": tu_results
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

        if result is None:
            update_optimization_status(conn, opt_id, "infeasible")
            return {"status": "infeasible", "dataset_id": dataset_id, "opt_id": opt_id}

        # store TU results
        for tr in result["tu_results"]:
            pname = f"nivel_p{tr['piso']}_a{tr['apartamento']}_t{tr['tu_index']}"
            insert_result(
                conn, opt_id, pname,
                tr["nivel"], unit="dBuV",
                deviation=tr["deviation"],
                meta=tr["meta"]
            )

        update_optimization_status(conn, opt_id, "completed")

        return {
            "status": "success",
            "opt_id": opt_id,
            "dataset_id": dataset_id,
            "tu_count": len(result["tu_results"]),
            "chosen_derivadores": result["chosen_derivadores"],
            "chosen_repartidores": {
                f"{p}_{a}": v for (p, a), v in result["chosen_repartidores"].items()
            }
        }

    except Exception as e:
        update_optimization_status(conn, opt_id, "failed")
        return {"status": "error", "message": str(e)}

    finally:
        conn.close()


if __name__ == "__main__":
    res = main()
    print(json.dumps(res))
