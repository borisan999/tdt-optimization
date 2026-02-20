import pandas as pd
import math
import os
import sys
import numpy as np
from pulp import LpVariable, LpBinary, lpSum, value

# Definición de constantes para nombres de archivo de salida de gráficos.
PLOT_LEVELS_PNG = "niveles_por_tu.png"
PLOT_HIST_PNG = "histograma_niveles.png"

def _crear_variables(params, pisos, all_toma_indices, bloques_de_pisos):
    """
    Crea y devuelve todas las variables de decisión para el modelo MILP.

    Define variables binarias para la selección de componentes (derivadores, repartidores)
    y variables continuas para los niveles de señal y las desviaciones.
    """
    # x: Variable binaria que indica si se selecciona el derivador 'd' en el piso 'p'.
    x = {(p, d): LpVariable(f"x_deriv_{p}_{d}", cat=LpBinary)
         for p in pisos for d in params['derivadores_data']}
    
    # y: Variable binaria que indica si se selecciona el repartidor 'r' para el apartamento 'a' en el piso 'p'.
    y = {(p, a, r): LpVariable(f"y_rep_{p}_{a}_{r}", cat=LpBinary)
         for p in pisos for a in range(1, params['apartamentos_por_piso'] + 1)
         for r in params['repartidores_data']}
    
    # z: Variable binaria que indica si se USA un repartidor en el apartamento 'a' del piso 'p'.
    z = {(p, a): LpVariable(f"z_rep_use_{p}_{a}", cat=LpBinary)
         for p in pisos for a in range(1, params['apartamentos_por_piso'] + 1)}
    
    # nivel_tu: Nivel de señal (variable continua) en una toma de usuario (TU) específica.
    nivel_tu = {(p, a, tu_idx): LpVariable(f"nivel_{p}_{a}_{tu_idx}", lowBound=0)
                for (p, a, tu_idx) in all_toma_indices}
    
    # d_plus/d_minus: Variables de desviación para penalizar la diferencia entre el nivel de señal y el objetivo.
    d_plus = {(p, a, tu_idx): LpVariable(f"dplus_{p}_{a}_{tu_idx}", lowBound=0)
              for (p, a, tu_idx) in all_toma_indices}
    d_minus = {(p, a, tu_idx): LpVariable(f"dminus_{p}_{a}_{tu_idx}", lowBound=0)
               for (p, a, tu_idx) in all_toma_indices}
    
    # r_troncal: Variable binaria para la selección del repartidor troncal.
    r_troncal = {r: LpVariable(f"r_troncal_{r}", cat=LpBinary)
                 for r in params['repartidores_data']}
    
    # pot_in_riser_by_block: Potencia de entrada a la línea principal (riser) en un piso y bloque específicos.
    pot_in_riser_by_block = {(p, b_idx): LpVariable(f"pot_riser_p{p}_b{b_idx}", lowBound=0)
                             for b_idx, bloque in enumerate(bloques_de_pisos) for p in bloque}
    
    return x, y, z, nivel_tu, d_plus, d_minus, r_troncal, pot_in_riser_by_block

def _restriccion_troncal(modelo, params, r_troncal, num_bloques):
    """Añade las restricciones para la selección del repartidor troncal."""
    # Asegura que se seleccione exactamente un repartidor troncal.
    modelo += lpSum(r_troncal[r] for r in r_troncal) == 1, "seleccion_un_troncal"
    # Asegura que el repartidor troncal seleccionado tenga suficientes salidas para todos los bloques.
    for r, data in params['repartidores_data'].items():
        if data.get('salidas', 0) < num_bloques:
            modelo += r_troncal[r] == 0, f"troncal_{r}_no_suf_salidas"

def _restriccion_derivador(modelo, params, x, pisos):
    """Añade las restricciones para la selección de derivadores en cada piso."""
    for p in pisos:
        # Asegura que se seleccione exactamente un derivador por piso.
        modelo += lpSum(x[p, d] for d in params['derivadores_data']) == 1, f"one_derivador_p{p}"
        # Asegura que el derivador seleccionado tenga suficientes salidas para los apartamentos del piso.
        for d, ddata in params['derivadores_data'].items():
            if ddata.get('salidas', 0) < params['apartamentos_por_piso']:
                modelo += x[p, d] == 0, f"deriv_{d}_no_salidas_p{p}"

def _restriccion_repartidor_apto(modelo, params, y, z, pisos):
    """Añade las restricciones para la selección de repartidores en cada apartamento."""
    for p in pisos:
        for a in range(1, params['apartamentos_por_piso'] + 1):
            tu_req = params['tus_requeridos_por_apartamento'].get((p, a), 0)
            # Si el apartamento requiere 1 o 0 tomas, no se necesita repartidor.
            if tu_req <= 1:
                modelo += z[p, a] == 0, f"no_rep_p{p}_a{a}"
                modelo += lpSum(y[p, a, r] for r in params['repartidores_data']) == 0, f"no_yvars_p{p}_a{a}"
            # Si se requieren más de 1 toma, se debe usar un repartidor.
            else:
                modelo += z[p, a] == 1, f"use_rep_p{p}_a{a}"
                # Asegura que se seleccione un solo repartidor para el apartamento.
                modelo += lpSum(y[p, a, r] for r in params['repartidores_data']) == 1, f"one_y_p{p}_a{a}"
                # El repartidor seleccionado debe tener suficientes salidas.
                for r, rdata in params['repartidores_data'].items():
                    if rdata.get('salidas', 0) < tu_req:
                        modelo += y[p, a, r] == 0, f"y_{r}_insuf_p{p}_a{a}"

def _perdidas_comunes(params, r_troncal):
    """Calculates common losses from the antenna to the trunk distributor.

    This function computes the vertical distance, cable loss, connector loss, and trunk insertion loss for the main signal path.

    Args:
        params: Dictionary containing all required parameters for the model.
        r_troncal: Decision variables for trunk distributor selection.

    Returns:
        Tuple containing the trunk floor, antenna-to-trunk length, cable loss, connector loss, and trunk insertion loss.
    """

    p_troncal = params['p_troncal']
    # Distancia vertical estimada desde azotea al troncal: (pisos arriba del troncal + 1)*entre_pisos
    long_ant_troncal = (params['Piso_Maximo'] - p_troncal + 1) * params['largo_cable_entre_pisos'] + params['largo_cable_amplificador_ultimo_piso']
    loss_ant_troncal = long_ant_troncal * params['atenuacion_cable_por_metro']
    loss_conns_ant_troncal = params['conectores_por_union'] * params['atenuacion_conector']
    # Pérdida de inserción del troncal seleccionado    
    loss_troncal_ins = lpSum(r_troncal[r] * params['repartidores_data'][r]['perdida_insercion']
                             for r in params['repartidores_data'])
    return p_troncal, long_ant_troncal, loss_ant_troncal, loss_conns_ant_troncal, loss_troncal_ins

def _restriccion_bloques(modelo, params, bloques_de_pisos, p_troncal, x, pot_in_riser_by_block, loss_ant_troncal, loss_conns_ant_troncal, loss_troncal_ins):
    """Añade las restricciones para la propagación de la señal a través de los bloques del edificio."""
    for b_idx, bloque in enumerate(bloques_de_pisos):
        p_ent, direccion = entrada_y_direccion_bloque(bloque, p_troncal)
        
        # Calcula las pérdidas en el cable 'feeder' que conecta el troncal con la entrada del bloque.
        long_vertical = abs(p_ent - p_troncal) * params['largo_cable_entre_pisos']
        long_feeder_bloque = params['largo_cable_feeder_bloque'] + long_vertical
        loss_feeder_bloque = long_feeder_bloque * params['atenuacion_cable_por_metro']
        loss_conns_feeder_bloque = params['conectores_por_union'] * params['atenuacion_conector']

        # Define la potencia de entrada al 'riser' del bloque.
        modelo += (
            pot_in_riser_by_block[p_ent, b_idx]
            == params['potencia_entrada']
            - loss_ant_troncal - loss_conns_ant_troncal
            - loss_troncal_ins
            - loss_feeder_bloque - loss_conns_feeder_bloque
        ), f"pot_block_init_b{b_idx}_p{p_ent}"

        # Modela la propagación de la señal (pérdidas) a través del 'riser' del bloque, piso por piso.
        if direccion == 'up':
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

def _restriccion_niveles_tu(modelo, params, all_toma_indices, bloques_de_pisos, x, y, nivel_tu, d_plus, d_minus, pot_in_riser_by_block):
    """Añade las restricciones para los niveles de señal en cada toma de usuario (TU)."""
    for (p, a, tu_idx) in all_toma_indices:
        b_idx_toma = next(b for b, bloque in enumerate(bloques_de_pisos) if p in bloque)
        
        # Calcula las pérdidas desde la entrada del 'riser' en el piso hasta la toma.
        deriv_loss = lpSum(x[p, d] * params['derivadores_data'][d]['derivacion'] for d in params['derivadores_data']) 
        cable_deriv_rep = params['largo_cable_derivador_repartidor'][(p, a)] * params['atenuacion_cable_por_metro']
        repartidor_loss = lpSum(y[p, a, r] * params['repartidores_data'][r]['perdida_insercion'] for r in params['repartidores_data'])
        cable_tu_loss = params['largo_cable_tu'][(p, a, tu_idx)] * params['atenuacion_cable_por_metro']
        conns_apto_loss = 4 * params['atenuacion_conector']
        conn_tu_loss = params['atenuacion_conexion_tu']

        # Define el nivel de señal final en la toma como la potencia en el 'riser' menos todas las pérdidas.
        modelo += (
            nivel_tu[p, a, tu_idx] == pot_in_riser_by_block[p, b_idx_toma]
            - deriv_loss
            - cable_deriv_rep - conns_apto_loss
            - repartidor_loss
            - cable_tu_loss
            - conn_tu_loss
        ), f"nivel_tu_{p}_{a}_{tu_idx}"

        # Asegura que el nivel de señal esté dentro de los límites mínimo y máximo permitidos.
        modelo += nivel_tu[p, a, tu_idx] >= params['Nivel_minimo'], f"nivel_min_{p}_{a}_{tu_idx}"
        modelo += nivel_tu[p, a, tu_idx] <= params['Nivel_maximo'], f"nivel_max_{p}_{a}_{tu_idx}"
        
        # Relaciona el nivel de señal con la desviación (positiva y negativa) respecto al objetivo.
        modelo += (
            nivel_tu[p, a, tu_idx] - params['Potencia_Objetivo_TU'] == d_plus[p, a, tu_idx] - d_minus[p, a, tu_idx]
        ), f"dev_abs_{p}_{a}_{tu_idx}"

# -------------------------
# Funciones auxiliares del modelo
# -------------------------

def entrada_y_direccion_bloque(bloque, p_troncal):
    """
    Determina el piso de entrada y la dirección de propagación para un bloque.
    - Si el troncal está por debajo del bloque, la señal sube ('up').
    - Si el troncal está en el bloque o por encima, la señal baja ('down').
    """
    max_b, min_b = max(bloque), min(bloque)
    if p_troncal < min_b:
        return min_b, 'up'
    else:
        return max_b, 'down'

def _seleccionar_troncal(params, aux):
    """Extrae del resultado del modelo el repartidor troncal seleccionado y sus propiedades."""
    r_troncal_sel = [r for r in params['repartidores_data'] if value(aux['r_troncal'][r]) > 0.5][0]
    loss_troncal_ins_val = params['repartidores_data'][r_troncal_sel]['perdida_insercion']
    salidas_troncal = params['repartidores_data'][r_troncal_sel]['salidas']
    return r_troncal_sel, loss_troncal_ins_val, salidas_troncal

# -------------------------
# Generación de resultados
# -------------------------

def _generar_filas_detalle(
    all_toma_indices, bloques, p_troncal, long_ant_troncal, loss_ant_troncal,
    loss_conns_ant_troncal, r_troncal_sel, loss_troncal_ins_val, salidas_troncal, aux, params
):
    """ This guarantees:

    detalle is never empty

    The failure is explicit and reportable """
    if not all_toma_indices:
        return [{
            'Toma': 'N/A',
            'Piso': None,
            'Apto': None,
            'Bloque': None,
            'P_in (entrada) (dBµV)': round(params['potencia_entrada'], 2),
            'Nivel TU Final (dBµV)': None,
            'Estado': 'INFEASIBLE',
            'Motivo': 'Modelo sin solución factible'
        }]
    """
    Genera las filas de resultados detallados para cada toma (TU) a partir del modelo resuelto.
    
    Calcula todas las pérdidas, distancias y niveles de señal para cada toma individualmente,
    creando un registro completo para el informe final.
    """
    filas_detalle = []
    for (p, a, tu_idx) in all_toma_indices:
        # Identifica el bloque al que pertenece la toma.
        b_idx = next(b for b, bloque in enumerate(bloques) if p in bloque)
        bloque = bloques[b_idx]
        p_ent, direccion = entrada_y_direccion_bloque(bloque, p_troncal)

        # Calcula longitudes y pérdidas del feeder del bloque.
        long_vertical = abs(p_ent - p_troncal) * params['largo_cable_entre_pisos']
        long_feeder_bloque = params['largo_cable_feeder_bloque'] + long_vertical
        loss_feeder_bloque = long_feeder_bloque * params['atenuacion_cable_por_metro']
        loss_conns_feeder_bloque = params['conectores_por_union'] * params['atenuacion_conector']

        # Obtiene potencias y pérdidas del riser del modelo.
        pot_entry = value(aux['pot_in_riser_by_block'][p_ent, b_idx])
        pot_riser_p = value(aux['pot_in_riser_by_block'][p, b_idx])
        loss_riser_dentro_bloque = pot_entry - pot_riser_p

        # --- Desglose de pérdidas en el riser ---
        dist_riser_cable = abs(p - p_ent) * params['largo_cable_entre_pisos']
        loss_cable_riser = dist_riser_cable * params['atenuacion_cable_por_metro']
        num_conns_riser = abs(p - p_ent) * params['conectores_por_union']
        loss_conns_riser = num_conns_riser * params['atenuacion_conector']
        
        loss_paso_acumulada = 0
        pisos_atravesados = []
        if direccion == 'up':
            pisos_atravesados = sorted([pi for pi in bloque if p_ent <= pi < p])
        elif direccion == 'down':
            pisos_atravesados = sorted([pi for pi in bloque if p < pi <= p_ent], reverse=True)

        for pi in pisos_atravesados:
            d_sel_paso = [d for d in params['derivadores_data'] if value(aux['x'][pi, d]) > 0.5][0]
            loss_paso_acumulada += params['derivadores_data'][d_sel_paso]['paso']
        
        # Obtiene el derivador seleccionado y su pérdida.
        d_sel = [d for d in params['derivadores_data'] if value(aux['x'][p, d]) > 0.5][0]
        loss_deriv = params['derivadores_data'][d_sel]['derivacion']

        # Obtiene el repartidor del apartamento (si se usa) y su pérdida.
        r_apt_sel, loss_rep_apt = 'N/A', 0.0
        for r in params['repartidores_data']:
            if value(aux['y'][p, a, r]) > 0.5:
                r_apt_sel = r
                loss_rep_apt = params['repartidores_data'][r]['perdida_insercion']
                break

        # Calcula pérdidas en los tramos finales de cable y conectores.
        loss_cable_deriv_rep = params['largo_cable_derivador_repartidor'][(p, a)] * params['atenuacion_cable_por_metro']
        loss_cable_tu = params['largo_cable_tu'][(p, a, tu_idx)] * params['atenuacion_cable_por_metro']
        loss_conns_apto = 4 * params['atenuacion_conector']
        loss_conn_tu = params['atenuacion_conexion_tu']

        # Obtiene el nivel de señal final de la variable del modelo.
        nivel_tu_modelo = value(aux['nivel_tu'][p, a, tu_idx])

        # Calcula la pérdida total como suma de todas las pérdidas parciales (para verificación).
        perdida_total = (
            loss_ant_troncal + loss_conns_ant_troncal
            + loss_troncal_ins_val
            + loss_feeder_bloque + loss_conns_feeder_bloque
            + loss_riser_dentro_bloque
            + loss_deriv + loss_cable_deriv_rep + loss_conns_apto
            + loss_rep_apt + loss_cable_tu + loss_conn_tu
        )
        # Calcula el nivel de señal por balance de potencias (para verificación).
        nivel_balance = params['potencia_entrada'] - perdida_total

        # Calcula la distancia total de cable hasta la toma.
        tramos_riser = abs(p - p_ent)
        dist_riser = tramos_riser * params['largo_cable_entre_pisos']
        dist_total = (
            long_ant_troncal
            + long_feeder_bloque
            + dist_riser
            + params['largo_cable_derivador_repartidor'][(p, a)]
            + params['largo_cable_tu'][(p, a, tu_idx)]
        )

        # Crea un diccionario con todos los resultados detallados para esta toma.
        filas_detalle.append({
            'Toma': f"P{p:02d}A{a}TU{tu_idx}",
            'Piso': p,
            'Apto': a,
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
            # 'Distancia riser dentro bloque Cable (m)': round(dist_riser_cable, 2),
            'Distancia riser dentro bloque (m)': round(dist_riser, 2),
            'Riser Atenuacion Cable (dB)': round(loss_cable_riser, 3),
            'Riser Conectores (uds)': num_conns_riser,
            'Riser Atenuacion Conectores (dB)': round(loss_conns_riser, 3),
            'Riser Atenuación Taps (dB)': round(loss_paso_acumulada, 3),
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
            # 'Nivel TU (balance) (dBµV)': round(nivel_balance, 3),
            'Distancia total hasta la toma (m)': round(dist_total, 2),
        })
    return filas_detalle

