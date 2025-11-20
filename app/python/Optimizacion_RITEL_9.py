#!/usr/bin/env python3
# optimizacion_tdt_troncal.py
# Script completo: carga, MILP (troncal + feeder variable a cada bloque), export y visualización.

import math
from pulp import LpProblem, LpVariable, LpBinary, LpMinimize, lpSum, LpStatus, value
import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import os
import sys

# -------------------------
# Config / Defaults
# -------------------------
INPUT_FILE = "datos_entrada.xlsx"
OUTPUT_XLSX = "Resultados_Optimizacion_TDT_Troncal.xlsx"
PLOT_LEVELS_PNG = "niveles_por_tu.png"
PLOT_HIST_PNG = "histograma_niveles.png"

# -------------------------
# Util: leer hojas de Excel
# -------------------------
def leer_datos(sheet_name, index_col=None):
    try:
        return pd.read_excel(INPUT_FILE, sheet_name=sheet_name, index_col=index_col)
    except FileNotFoundError:
        raise
    except Exception as e:
        raise RuntimeError(f"Error leyendo hoja '{sheet_name}': {e}")

# -------------------------
# División en bloques (3 a 5 pisos por bloque, lo más uniforme posible)
# -------------------------
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

    bloques, piso_actual = [], pisos_max
    for tam in best_partition:
        bloque = list(range(piso_actual, piso_actual - tam, -1))  # descendente
        bloques.append(bloque)
        piso_actual -= tam
    return bloques

# -------------------------
# Cargar parámetros y datos desde Excel
# -------------------------
def cargar_datos_y_parametros(input_file=INPUT_FILE):
    if not os.path.exists(input_file):
        print(f"Error: no se encuentra el archivo '{input_file}'.")
        sys.exit(1)

    df_param = leer_datos("Parametros_Generales", index_col=0)
    def getv(key, default=None, cast=float):
        return cast(df_param.loc[key, 'Valor']) if key in df_param.index else default

    Piso_Maximo = getv('Piso_Maximo', cast=int)
    apartamentos_por_piso = getv('Apartamentos_Piso', cast=int)

    # Atenuaciones
    atenuacion_cable_por_metro = getv('Atenuacion_Cable_dBporM', 0.2)
    atenuacion_conector = getv('Atenuacion_Conector_dB', 0.2)   # ajustado a 0.2 dB
    largo_cable_entre_pisos = getv('Largo_Entre_Pisos_m', 3.0)
    potencia_entrada = getv('Potencia_Entrada_dBuV', 110.0)
    Nivel_minimo = getv('Nivel_Minimo_dBuV', 47.0)
    Nivel_maximo = getv('Nivel_Maximo_dBuV', 70.0)
    Potencia_Objetivo_TU = getv('Potencia_Objetivo_TU_dBuV', 60.0)
    conectores_por_union = int(getv('Conectores_por_Union', 2, cast=float))
    atenuacion_conexion_tu = getv('Atenuacion_Conexion_TU_dB', 1.0)
    largo_cable_amplificador_ultimo_piso = getv('Largo_Cable_Amplificador_Ultimo_Piso',5)
    # Longitud fija del feeder troncal -> entrada de bloque (requerido), mínimo 3m
    largo_cable_feeder_bloque = getv('Largo_Feeder_Bloque_m (Mínimo)', 3.0)
    # Tablas
    df_lc_dr = leer_datos('largo_cable_derivador_repartidor', index_col=[0, 1])
    largo_cable_derivador_repartidor = df_lc_dr['Longitud_m'].to_dict()

    df_tr_pa = leer_datos('tus_requeridos_por_apartamento', index_col=[0, 1])
    tus_requeridos_por_apartamento = df_tr_pa['Cantidad_Tomas'].to_dict()

    df_lc_tu = leer_datos('largo_cable_tu', index_col=[0, 1, 2])
    largo_cable_tu = df_lc_tu['Longitud_m'].to_dict()

    df_deriv = leer_datos('derivadores_data', index_col=0)
    derivadores_data = df_deriv.to_dict(orient='index')

    df_rep = leer_datos('repartidores_data', index_col=0)
    repartidores_data = df_rep.to_dict(orient='index')

    # Piso donde está el troncal (cerca a la mitad)
    p_troncal = int(round(Piso_Maximo / 2))

    return {
        'Piso_Maximo': Piso_Maximo,
        'apartamentos_por_piso': apartamentos_por_piso,
        'largo_cable_derivador_repartidor': largo_cable_derivador_repartidor,
        'tus_requeridos_por_apartamento': tus_requeridos_por_apartamento,
        'largo_cable_tu': largo_cable_tu,
        'derivadores_data': derivadores_data,
        'repartidores_data': repartidores_data,
        'Nivel_minimo': Nivel_minimo,
        'Nivel_maximo': Nivel_maximo,
        'Potencia_Objetivo_TU': Potencia_Objetivo_TU,
        'potencia_entrada': potencia_entrada,
        'atenuacion_cable_por_metro': atenuacion_cable_por_metro,
        'atenuacion_conector': atenuacion_conector,
        'atenuacion_conexion_tu': atenuacion_conexion_tu,
        'largo_cable_entre_pisos': largo_cable_entre_pisos,
        'conectores_por_union': conectores_por_union,
        'p_troncal': p_troncal,
        'largo_cable_amplificador_ultimo_piso':largo_cable_amplificador_ultimo_piso,
        'largo_cable_feeder_bloque': largo_cable_feeder_bloque,
    }

# -------------------------
# Generar índices de TUs y validar
# -------------------------
def generar_indices_y_validar_datos(params):
    pisos = list(range(params['Piso_Maximo'], 0, -1))
    all_toma_indices = []
    for p in pisos:
        for a in range(1, params['apartamentos_por_piso'] + 1):
            num_tomas = params['tus_requeridos_por_apartamento'].get((p, a), 0)
            for tu_idx in range(1, num_tomas + 1):
                all_toma_indices.append((p, a, tu_idx))

    for (p, a), cnt in params['tus_requeridos_por_apartamento'].items():
        for tu_idx in range(1, cnt + 1):
            if (p, a, tu_idx) not in params['largo_cable_tu']:
                raise ValueError(f"Falta longitud de cable TU para (p={p}, a={a}, tu={tu_idx})")
        if (p, a) not in params['largo_cable_derivador_repartidor']:
            raise ValueError(f"Falta longitud derivador->repartidor para (p={p}, a={a})")
    return all_toma_indices

# -------------------------
# Aux: entrada (piso) y dirección de propagación por bloque (SIN 'both')
# -------------------------
def entrada_y_direccion_bloque(bloque, p_troncal):
    """
    Regla solicitada:
    - Si el troncal está por debajo del bloque => dirección 'up' y la entrada es el piso más bajo del bloque (min_b).
    - Si el troncal está en el piso del bloque o por encima => dirección 'down' y la entrada es el piso más alto del bloque (max_b).
    NOTA: no existe la opción 'both'.
    """
    max_b, min_b = max(bloque), min(bloque)
    if p_troncal < min_b:        # troncal por debajo del bloque
        return min_b, 'up'
    else:                        # troncal en el bloque o por encima
        return max_b, 'down'

# -------------------------
# Construir modelo MILP
# -------------------------
def construir_modelo_milp(params, all_toma_indices):
    modelo = LpProblem("Optimizacion_TDT_Troncal", LpMinimize)

    pisos = list(range(params['Piso_Maximo'], 0, -1))
    bloques_de_pisos = dividir_en_bloques(params['Piso_Maximo'])
    num_bloques = len(bloques_de_pisos)

    # ----------------- Variables de decisión -----------------
    x = {(p, d): LpVariable(f"x_deriv_{p}_{d}", cat=LpBinary)
         for p in pisos for d in params['derivadores_data']}
    y = {(p, a, r): LpVariable(f"y_rep_{p}_{a}_{r}", cat=LpBinary)
         for p in pisos for a in range(1, params['apartamentos_por_piso'] + 1)
         for r in params['repartidores_data']}
    z = {(p, a): LpVariable(f"z_rep_use_{p}_{a}", cat=LpBinary)
         for p in pisos for a in range(1, params['apartamentos_por_piso'] + 1)}
    nivel_tu = {(p, a, tu_idx): LpVariable(f"nivel_{p}_{a}_{tu_idx}", lowBound=0)
                for (p, a, tu_idx) in all_toma_indices}
    d_plus = {(p, a, tu_idx): LpVariable(f"dplus_{p}_{a}_{tu_idx}", lowBound=0)
              for (p, a, tu_idx) in all_toma_indices}
    d_minus = {(p, a, tu_idx): LpVariable(f"dminus_{p}_{a}_{tu_idx}", lowBound=0)
               for (p, a, tu_idx) in all_toma_indices}

    # Selección de 1 repartidor troncal
    r_troncal = {r: LpVariable(f"r_troncal_{r}", cat=LpBinary)
                 for r in params['repartidores_data']}

    # Potencia en el riser por piso y por bloque
    pot_in_riser_by_block = {(p, b_idx): LpVariable(f"pot_riser_p{p}_b{b_idx}", lowBound=0)
                             for b_idx, bloque in enumerate(bloques_de_pisos) for p in bloque}

    # ----------------- Función objetivo -----------------
    modelo += lpSum(d_plus[p, a, tu] + d_minus[p, a, tu] for (p, a, tu) in all_toma_indices), "min_desviacion_total"

    # ----------------- Restricciones -----------------
    # Un solo troncal y suficientes salidas
    modelo += lpSum(r_troncal[r] for r in r_troncal) == 1, "seleccion_un_troncal"
    for r, data in params['repartidores_data'].items():
        if data.get('salidas', 0) < num_bloques:
            modelo += r_troncal[r] == 0, f"troncal_{r}_no_suf_salidas"

    # Un derivador por piso y suficientes salidas por derivador
    for p in pisos:
        modelo += lpSum(x[p, d] for d in params['derivadores_data']) == 1, f"one_derivador_p{p}"
        for d, ddata in params['derivadores_data'].items():
            if ddata.get('salidas', 0) < params['apartamentos_por_piso']:
                modelo += x[p, d] == 0, f"deriv_{d}_no_salidas_p{p}"

    # Reglas de repartidor en apartamento
    for p in pisos:
        for a in range(1, params['apartamentos_por_piso'] + 1):
            tu_req = params['tus_requeridos_por_apartamento'].get((p, a), 0)
            if tu_req <= 1:
                modelo += z[p, a] == 0, f"no_rep_p{p}_a{a}"
                modelo += lpSum(y[p, a, r] for r in params['repartidores_data']) == 0, f"no_yvars_p{p}_a{a}"
            else:
                modelo += z[p, a] == 1, f"use_rep_p{p}_a{a}"
                modelo += lpSum(y[p, a, r] for r in params['repartidores_data']) == 1, f"one_y_p{p}_a{a}"
                for r, rdata in params['repartidores_data'].items():
                    if rdata.get('salidas', 0) < tu_req:
                        modelo += y[p, a, r] == 0, f"y_{r}_insuf_p{p}_a{a}"

    # ----------------- Pérdidas comunes Antena→Troncal -----------------
    p_troncal = params['p_troncal']
    # Distancia vertical estimada desde azotea al troncal
    long_ant_troncal = (params['Piso_Maximo'] - p_troncal + 1) * params['largo_cable_entre_pisos'] + params['largo_cable_amplificador_ultimo_piso']

    loss_ant_troncal = long_ant_troncal * params['atenuacion_cable_por_metro']
    loss_conns_ant_troncal = params['conectores_por_union'] * params['atenuacion_conector']

    # Pérdida de inserción del troncal seleccionado
    loss_troncal_ins = lpSum(r_troncal[r] * params['repartidores_data'][r]['perdida_insercion']
                             for r in params['repartidores_data'])

    # ----------------- Entrada de cada bloque por feeder VARIABLE -----------------
    for b_idx, bloque in enumerate(bloques_de_pisos):
        p_ent, direccion = entrada_y_direccion_bloque(bloque, p_troncal)

        # Longitud feeder: 3 m mínimos + tramo vertical hasta p_ent (si procede)
        long_vertical = abs(p_ent - p_troncal) * params['largo_cable_entre_pisos']
        long_feeder_bloque = params['largo_cable_feeder_bloque'] + long_vertical
        loss_feeder_bloque = long_feeder_bloque * params['atenuacion_cable_por_metro']
        loss_conns_feeder_bloque = params['conectores_por_union'] * params['atenuacion_conector']

        # Potencia inicial en el piso de entrada del bloque
        modelo += (
            pot_in_riser_by_block[p_ent, b_idx]
            == params['potencia_entrada']
            - loss_ant_troncal - loss_conns_ant_troncal
            - loss_troncal_ins
            - loss_feeder_bloque - loss_conns_feeder_bloque
        ), f"pot_block_init_b{b_idx}_p{p_ent}"

        # Propagación SOLO en la dirección indicada
        if direccion == 'up':
            # de menor a mayor piso iniciando en p_ent
            pisos_up = sorted([p for p in bloque if p >= p_ent])
            for i in range(len(pisos_up) - 1):
                p_act, p_sig = pisos_up[i], pisos_up[i + 1]
                paso_piso = lpSum(x[p_act, d] * params['derivadores_data'][d]['paso'] for d in params['derivadores_data'])
                loss_entre_pisos = params['largo_cable_entre_pisos'] * params['atenuacion_cable_por_metro']
                loss_conns_entre_pisos = params['conectores_por_union'] * params['atenuacion_conector']
                modelo += (
                    pot_in_riser_by_block[p_sig, b_idx]
                    == pot_in_riser_by_block[p_act, b_idx]
                    - paso_piso - loss_entre_pisos - loss_conns_entre_pisos
                ), f"prop_up_b{b_idx}_{p_act}_to_{p_sig}"

        elif direccion == 'down':
            # de mayor a menor piso iniciando en p_ent
            pisos_down = sorted([p for p in bloque if p <= p_ent], reverse=True)
            for i in range(len(pisos_down) - 1):
                p_act, p_sig = pisos_down[i], pisos_down[i + 1]
                paso_piso = lpSum(x[p_act, d] * params['derivadores_data'][d]['paso'] for d in params['derivadores_data'])
                loss_entre_pisos = params['largo_cable_entre_pisos'] * params['atenuacion_cable_por_metro']
                loss_conns_entre_pisos = params['conectores_por_union'] * params['atenuacion_conector']
                modelo += (
                    pot_in_riser_by_block[p_sig, b_idx]
                    == pot_in_riser_by_block[p_act, b_idx]
                    - paso_piso - loss_entre_pisos - loss_conns_entre_pisos
                ), f"prop_down_b{b_idx}_{p_act}_to_{p_sig}"

    # ----------------- Niveles en cada TU -----------------
    for (p, a, tu_idx) in all_toma_indices:
        b_idx_toma = next(b for b, bloque in enumerate(bloques_de_pisos) if p in bloque)
        deriv_loss = lpSum(x[p, d] * params['derivadores_data'][d]['derivacion'] for d in params['derivadores_data']) 
        cable_deriv_rep = params['largo_cable_derivador_repartidor'][(p, a)] * params['atenuacion_cable_por_metro']
        repartidor_loss = lpSum(y[p, a, r] * params['repartidores_data'][r]['perdida_insercion'] for r in params['repartidores_data'])
        cable_tu_loss = params['largo_cable_tu'][(p, a, tu_idx)] * params['atenuacion_cable_por_metro']
        conns_apto_loss = 4 * params['atenuacion_conector']  # 2 uniones deriv->rep y 2 rep->tu
        conn_tu_loss = params['atenuacion_conexion_tu']

        modelo += (
            nivel_tu[p, a, tu_idx] == pot_in_riser_by_block[p, b_idx_toma]
            - deriv_loss
            - cable_deriv_rep - conns_apto_loss
            - repartidor_loss
            - cable_tu_loss
            - conn_tu_loss
        ), f"nivel_tu_{p}_{a}_{tu_idx}"

        modelo += nivel_tu[p, a, tu_idx] >= params['Nivel_minimo'], f"nivel_min_{p}_{a}_{tu_idx}"
        modelo += nivel_tu[p, a, tu_idx] <= params['Nivel_maximo'], f"nivel_max_{p}_{a}_{tu_idx}"
        modelo += (
            nivel_tu[p, a, tu_idx] - params['Potencia_Objetivo_TU'] == d_plus[p, a, tu_idx] - d_minus[p, a, tu_idx]
        ), f"dev_abs_{p}_{a}_{tu_idx}"

    # Guardar estructuras auxiliares
    modelo._aux = {
        'x': x, 'y': y, 'z': z, 'nivel_tu': nivel_tu,
        'd_plus': d_plus, 'd_minus': d_minus,
        'r_troncal': r_troncal,
        'pot_in_riser_by_block': pot_in_riser_by_block,
        'bloques_de_pisos': bloques_de_pisos,
        'p_troncal': p_troncal,
        'long_ant_troncal': long_ant_troncal,
        'loss_ant_troncal': loss_ant_troncal,
        'loss_conns_ant_troncal': loss_conns_ant_troncal
    }
    return modelo

# -------------------------
# Resolver y exportar (Excel + gráficos)
# -------------------------
def resolver_y_exportar(modelo, params, all_toma_indices, output_excel_file=OUTPUT_XLSX):
    modelo.solve()

    if LpStatus[modelo.status] != 'Optimal':
        print("No se encontró solución óptima. Estado:", LpStatus[modelo.status])
        return None, None

    aux = modelo._aux
    bloques = aux['bloques_de_pisos']
    p_troncal = aux['p_troncal']
    long_ant_troncal = aux['long_ant_troncal']
    loss_ant_troncal = aux['loss_ant_troncal']
    loss_conns_ant_troncal = aux['loss_conns_ant_troncal']

    # Troncal seleccionado
    r_troncal_sel = [r for r in params['repartidores_data'] if value(aux['r_troncal'][r]) > 0.5][0]
    loss_troncal_ins_val = params['repartidores_data'][r_troncal_sel]['perdida_insercion']
    salidas_troncal = params['repartidores_data'][r_troncal_sel]['salidas']

    filas_detalle, filas_resumen = [], []

    for (p, a, tu_idx) in all_toma_indices:
        b_idx = next(b for b, bloque in enumerate(bloques) if p in bloque)
        bloque = bloques[b_idx]
        p_ent, direccion = entrada_y_direccion_bloque(bloque, p_troncal)

        # Feeder variable troncal->entrada bloque
        
        long_vertical = abs(p_ent - p_troncal) * params['largo_cable_entre_pisos']
        long_feeder_bloque = params['largo_cable_feeder_bloque'] + long_vertical
        loss_feeder_bloque = long_feeder_bloque * params['atenuacion_cable_por_metro']
        loss_conns_feeder_bloque = params['conectores_por_union'] * params['atenuacion_conector']
        print('***** ',long_feeder_bloque,loss_feeder_bloque)

        # Potencias de modelo
        pot_entry = value(aux['pot_in_riser_by_block'][p_ent, b_idx])
        pot_riser_p = value(aux['pot_in_riser_by_block'][p, b_idx])
        loss_riser_dentro_bloque = pot_entry - pot_riser_p  # caída positiva hacia p

        # Derivador y pérdidas locales
        d_sel = [d for d in params['derivadores_data'] if value(aux['x'][p, d]) > 0.5][0]
        loss_deriv = params['derivadores_data'][d_sel]['derivacion']

        r_apt_sel, loss_rep_apt = 'N/A', 0.0
        for r in params['repartidores_data']:
            if value(aux['y'][p, a, r]) > 0.5:
                r_apt_sel = r
                loss_rep_apt = params['repartidores_data'][r]['perdida_insercion']
                break

        loss_cable_deriv_rep = params['largo_cable_derivador_repartidor'][(p, a)] * params['atenuacion_cable_por_metro']
        loss_cable_tu = params['largo_cable_tu'][(p, a, tu_idx)] * params['atenuacion_cable_por_metro']
        loss_conns_apto = 4 * params['atenuacion_conector']
        loss_conn_tu = params['atenuacion_conexion_tu']

        nivel_tu_modelo = value(aux['nivel_tu'][p, a, tu_idx])

        # Pérdida total para reporte (balance)
        perdida_total = (
            loss_ant_troncal + loss_conns_ant_troncal
            + loss_troncal_ins_val
            + loss_feeder_bloque + loss_conns_feeder_bloque
            + loss_riser_dentro_bloque
            + loss_deriv + loss_cable_deriv_rep + loss_conns_apto
            + loss_rep_apt + loss_cable_tu + loss_conn_tu
        )
        nivel_balance = params['potencia_entrada'] - perdida_total

        # Distancias
        tramos_riser = abs(p - p_ent)
        dist_riser = tramos_riser * params['largo_cable_entre_pisos']
        dist_total = (
            long_ant_troncal
            + long_feeder_bloque
            + dist_riser
            + params['largo_cable_derivador_repartidor'][(p, a)]
            + params['largo_cable_tu'][(p, a, tu_idx)]
        )

        filas_detalle.append({
            'Toma': f"P{p:02d}A{a}TU{tu_idx}",
            'Bloque': b_idx + 1,
            'Piso Troncal': p_troncal,
            'Piso Entrada Riser Bloque': p_ent,
            'Direccion Propagacion': 'Arriba' if direccion == 'up' else 'Abajo',
            'Longitud Antena→Troncal (m)': round(long_ant_troncal, 2),
            'Pérdida Antena→Troncal (cable) (dB)': round(loss_ant_troncal, 3),
            'Pérdida Antena↔Troncal (conectores) (dB)': round(loss_conns_ant_troncal, 3),
            'Repartidor Troncal': r_troncal_sel,
            'Salidas Troncal': salidas_troncal,
            'Pérdida Repartidor Troncal (dB)': round(loss_troncal_ins_val, 3),
            'Feeder Troncal→Entrada Bloque (m)': round(long_feeder_bloque, 3),
            'Pérdida Feeder (cable) (dB)': round(loss_feeder_bloque, 3),
            'Pérdida Feeder (conectores) (dB)': round(loss_conns_feeder_bloque, 3),
            'Pérdida Riser dentro del Bloque (dB)': round(loss_riser_dentro_bloque, 3),
            'Derivador Piso': d_sel,
            'Pérdida Derivador Piso (dB)': round(loss_deriv, 3),
            'Pérdida Cable Deriv→Rep (dB)': round(loss_cable_deriv_rep, 3),
            'Pérdida Conectores Apto (dB)': round(loss_conns_apto, 3),
            'Repartidor Apt': r_apt_sel,
            'Pérdida Repartidor Apt (dB)': round(loss_rep_apt, 3),
            'Pérdida Cable Rep→TU (dB)': round(loss_cable_tu, 3),
            'Pérdida Conexión TU (dB)': round(loss_conn_tu, 3),
            'Pérdida Total (dB)': round(perdida_total, 3),
            'P_in (entrada) (dBµV)': round(params['potencia_entrada'], 2),
            'Nivel TU Final (dBµV)': round(nivel_tu_modelo, 3),
            'Nivel TU (balance) (dBµV)': round(nivel_balance, 3),
            'Distancia riser dentro bloque (m)': round(dist_riser, 2),
            'Distancia total hasta la toma (m)': round(dist_total, 2),
        })

    # Resumen por bloque
    for b_idx, bloque in enumerate(bloques):
        p_ent, direccion = entrada_y_direccion_bloque(bloque, p_troncal)
        pot_init_b = value(aux['pot_in_riser_by_block'][p_ent, b_idx])

        long_vertical = abs(p_ent - p_troncal) * params['largo_cable_entre_pisos']
        long_feeder_bloque = 3.0 + long_vertical

        filas_resumen.append({
            'Bloque': b_idx + 1,
            'Pisos del Bloque (desc)': f"{bloque[0]}..{bloque[-1]}",
            'Piso Entrada Riser': p_ent,
            'Direccion Propagacion': 'Arriba' if direccion == 'up' else 'Abajo',
            'Longitud feeder troncal→bloque (m)': round(long_feeder_bloque, 2),
            'Potencia en entrada de bloque (dBµV)': round(pot_init_b, 3),
        })

    df_detalle = pd.DataFrame(filas_detalle)
    df_resumen = pd.DataFrame(filas_resumen)

    # Export a Excel
    with pd.ExcelWriter(output_excel_file) as writer:
        df_detalle.sort_values(by="Toma", ascending=False).to_excel(writer, sheet_name="Detalle_Tomas", index=False)
        df_resumen.to_excel(writer, sheet_name="Resumen_Bloques", index=False)

    print(f"Resultados exportados a '{output_excel_file}'")

    # Gráficos
    try:
        plt.figure(figsize=(12, 6))
        df_sorted = df_detalle.sort_values("Nivel TU Final (dBµV)", ascending=False)
        plt.bar(df_sorted['Toma'], df_sorted['Nivel TU Final (dBµV)'])
        plt.xticks(rotation=90, fontsize=8)
        plt.ylabel('Nivel TU Final (dBµV)')
        plt.title('Niveles finales por TU')
        plt.tight_layout()
        plt.savefig(PLOT_LEVELS_PNG, dpi=150)
        plt.close()
        print(f"Gráfico niveles guardado: {PLOT_LEVELS_PNG}")

        plt.figure(figsize=(8, 5))
        plt.hist(df_detalle['Nivel TU Final (dBµV)'], bins=20)
        plt.xlabel('Nivel TU Final (dBµV)')
        plt.ylabel('Frecuencia')
        plt.title('Histograma de niveles TU')
        plt.tight_layout()
        plt.savefig(PLOT_HIST_PNG, dpi=150)
        plt.close()
        print(f"Histograma guardado: {PLOT_HIST_PNG}")
    except Exception as e:
        print("Error generando gráficos:", e)

    return df_detalle, df_resumen

# -------------------------
# Main
# -------------------------
def main():
    print("Cargando datos...")
    params = cargar_datos_y_parametros(INPUT_FILE)
    print("Generando índices de tomas y validando...")
    all_toma_indices = generar_indices_y_validar_datos(params)
    print("Construyendo modelo MILP...")
    modelo = construir_modelo_milp(params, all_toma_indices)
    print("Resolviendo y exportando resultados...")
    df_detalle, df_resumen = resolver_y_exportar(modelo, params, all_toma_indices, OUTPUT_XLSX)
    if df_detalle is not None:
        print("Listo.")

if __name__ == "__main__":
    main()
