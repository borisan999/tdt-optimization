#!/usr/bin/env python3
# Script completo: carga, MILP (troncal + feeder variable a cada bloque), export y visualización.

from pulp import LpProblem, LpMinimize, lpSum, LpStatus
import pandas as pd
from Funciones_apoyo_datos_entrada import dividir_en_bloques, cargar_datos_y_parametros, generar_indices_y_validar_datos
from Funciones_apoyo_salida import (_exportar_a_excel, _generar_graficos, 
                                _generar_df_inventario, _dibujar_esquema_conexiones_ascii, 
                                _generar_df_resumen_por_piso,  
                                _generar_df_detalle_resumido)
from Funciones_apoyo_optimizacion import (
    _crear_variables, _restriccion_troncal, _restriccion_derivador,
    _restriccion_repartidor_apto, _perdidas_comunes,
    _restriccion_bloques, _restriccion_niveles_tu,
    _seleccionar_troncal, _generar_filas_detalle
)
from Funciones_apoyo_visualizacion import _generar_arbol_completo_con_specs
import json
import hashlib
from decimal import Decimal
# -------------------------
# Config / Defaults
# -------------------------
INPUT_FILE = "datos_entrada.xlsx"
OUTPUT_XLSX = "Resultados_Optimizacion_TDT_Troncal.xlsx"
esquema_out_file = "esquema_conexiones.txt"

# -------------------------
# Construir modelo MILP
# -------------------------

def construir_modelo_milp(params, all_toma_indices):
    """Builds and returns a MILP model for the TDT trunk optimization problem.

    This function creates the decision variables, objective function, and constraints for the MILP model
    based on the provided parameters and indices. The model aims to optimize signal levels and component selection
    in a multi-floor building distribution system.

    Args:
        params: Dictionary containing all required parameters for the model.
        all_toma_indices: List of tuples representing all (floor, apartment, TU) indices.

    Returns:
        A PuLP LpProblem object representing the constructed MILP model.
    """
    # Inicializa el modelo de PuLP para minimización.
    modelo = LpProblem("Optimizacion_TDT_Troncal", LpMinimize)

    # Define las listas de pisos, bloques y su cantidad.
    pisos = list(range(params['Piso_Maximo'], 0, -1))
    bloques_de_pisos = dividir_en_bloques(params['Piso_Maximo'])
    num_bloques = len(bloques_de_pisos)

    # Crea las variables de decisión del modelo (selección de equipos, niveles de señal, etc.).
    x, y, z, nivel_tu, d_plus, d_minus, r_troncal, pot_in_riser_by_block = _crear_variables(params, pisos, all_toma_indices, bloques_de_pisos)

    # Define la función objetivo: minimizar la desviación total respecto al nivel de señal objetivo.
    total_deviation = lpSum(d_plus[p, a, tu] + d_minus[p, a, tu] for (p, a, tu) in all_toma_indices)
    modelo += total_deviation, "min_desviacion_total"

    # Añade las restricciones al modelo.
    # 1. Selección de equipos (troncal, derivadores, repartidores de apartamento).
    _restriccion_troncal(modelo, params, r_troncal, num_bloques)
    _restriccion_derivador(modelo, params, x, pisos)
    _restriccion_repartidor_apto(modelo, params, y, z, pisos)
    
    # 2. Cálculo de pérdidas comunes en el tramo principal.
    p_troncal, long_ant_troncal, loss_ant_troncal, loss_conns_ant_troncal, loss_troncal_ins = _perdidas_comunes(params, r_troncal)
    
    # 3. Agrupa los argumentos para las funciones de restricciones más complejas.
    bloques_args = (
        modelo, params, bloques_de_pisos, p_troncal, x, pot_in_riser_by_block,
        loss_ant_troncal, loss_conns_ant_troncal, loss_troncal_ins
    )
    niveles_tu_args = (
        modelo, params, all_toma_indices, bloques_de_pisos, x, y, nivel_tu, d_plus, d_minus, pot_in_riser_by_block
    )

    # 4. Añade las restricciones de propagación de señal por bloques y los niveles en tomas.
    _restriccion_bloques(*bloques_args)
    _restriccion_niveles_tu(*niveles_tu_args)

    # Almacena variables y parámetros importantes en un diccionario auxiliar dentro del modelo.
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
# Canonical Serializer
def make_json_safe(obj):
    if isinstance(obj, dict):
        new_dict = {}
        for k, v in obj.items():
            if isinstance(k, tuple):
                key = "|".join(map(str, k))
            else:
                key = str(k)
            new_dict[key] = make_json_safe(v)
        return new_dict
    elif isinstance(obj, list):
        return [make_json_safe(i) for i in obj]
    else:
        return obj


# ----------------------------------------
# 1. Normalize numbers (avoid float drift)
# ----------------------------------------
def normalize_numbers(obj):
    if isinstance(obj, float):
        # Convert to Decimal via string to avoid FP noise
        d = Decimal(str(obj))
        # If it's mathematically an integer, return as int to match PHP json_encode
        if d == d.to_integral_value():
            return int(d)
        return float(d)
    elif isinstance(obj, dict):
        return {k: normalize_numbers(v) for k, v in obj.items()}
    elif isinstance(obj, list):
        return [normalize_numbers(v) for v in obj]
    else:
        return obj


# ----------------------------------------
# 2. Make JSON safe (convert tuple keys)
# ----------------------------------------
def make_json_safe(obj):
    if isinstance(obj, dict):
        new_dict = {}
        for k, v in obj.items():
            if isinstance(k, tuple):
                # Canonical tuple key encoding
                key_str = "|".join(map(str, k))
            else:
                key_str = str(k)
            new_dict[key_str] = make_json_safe(v)
        return new_dict
    elif isinstance(obj, list):
        return [make_json_safe(v) for v in obj]
    else:
        return obj


# ----------------------------------------
# 3. Canonical JSON serializer
# ----------------------------------------
def canonical_json_dumps(obj):
    return json.dumps(
        obj,
        sort_keys=True,
        separators=(",", ":"),   # remove whitespace
        ensure_ascii=False
    )


# ----------------------------------------
# 4. SHA256 hash generator
# ----------------------------------------
def compute_canonical_hash(obj):
    canonical_str = canonical_json_dumps(obj)
    return hashlib.sha256(canonical_str.encode("utf-8")).hexdigest()

# -------------------------
# Resolver y exportar (Excel + gráficos)
# -------------------------

def resolver_y_exportar(modelo, params, all_toma_indices, output_excel_file=OUTPUT_XLSX):
    """Solves the MILP model and exports the results to Excel and plots.

    This function solves the provided MILP model, processes the results, exports detailed and summary data to an Excel file, and generates plots of the TU levels. It returns the detailed and summary DataFrames for further analysis.

    Args:
        modelo: The PuLP LpProblem object representing the MILP model.
        params: Dictionary containing all required parameters for the model.
        all_toma_indices: List of tuples representing all (floor, apartment, TU) indices.
        output_excel_file: Path to the output Excel file.

    Returns:
        Tuple of (df_detalle, df_resumen) DataFrames with detailed and summary results.
    """    
    # Resuelve el modelo MILP.
    modelo.solve()

    # Verifica si se encontró una solución óptima.
    if LpStatus[modelo.status] != 'Optimal':
        print("No se encontró solución óptima. Estado:", LpStatus[modelo.status])
        return None, None

    # Extrae los resultados y variables auxiliares del modelo resuelto.
    aux = modelo._aux
    bloques = aux['bloques_de_pisos']
    p_troncal = aux['p_troncal']
    long_ant_troncal = aux['long_ant_troncal']
    loss_ant_troncal = aux['loss_ant_troncal']
    loss_conns_ant_troncal = aux['loss_conns_ant_troncal']

    # Procesa los resultados para generar filas de datos detallados y de resumen.
    r_troncal_sel, loss_troncal_ins_val, salidas_troncal = _seleccionar_troncal(params, aux)
    filas_detalle = _generar_filas_detalle(
        all_toma_indices, bloques, p_troncal, long_ant_troncal, loss_ant_troncal,
        loss_conns_ant_troncal, r_troncal_sel, loss_troncal_ins_val, salidas_troncal, aux, params
    )
    # filas_resumen = _generar_filas_resumen(bloques, p_troncal, aux, params)

    # Convierte las listas de resultados en DataFrames de pandas.
    df_detalle = pd.DataFrame(filas_detalle)
    # df_resumen = pd.DataFrame(filas_resumen)
    
    # Genera DataFrames adicionales para el inventario, resumen por piso y análisis de frecuencias.
    df_inventario = _generar_df_inventario(df_detalle, params, bloques)
    df_resumen_piso = _generar_df_resumen_por_piso(df_detalle)
    df_detalle_resumido = _generar_df_detalle_resumido(df_detalle, params)

    # Exporta todos los DataFrames a un único archivo Excel con múltiples hojas.
    _exportar_a_excel(df_detalle, df_inventario, df_resumen_piso, df_detalle_resumido, output_excel_file)
    
    # Genera y guarda los gráficos de niveles de señal.
    _generar_graficos(df_detalle)

    # Genera y exporta un esquema de la red en formato de texto.
    _dibujar_esquema_conexiones_ascii(df_detalle, params=params, aux=aux, output_file=esquema_out_file)
        
    # Devuelve los DataFrames principales para posible uso posterior.
    return df_detalle

# -------------------------
# Main
# -------------------------
def main():
    # 1. Cargar datos de entrada desde el archivo Excel.
    print("Cargando datos...")
    params = cargar_datos_y_parametros(INPUT_FILE)
    
    # 2. Generar la lista de todas las tomas del edificio y validar datos.
    print("Generando índices de tomas y validando...")
    all_toma_indices = generar_indices_y_validar_datos(params)
    
    # 3. Construir el modelo de optimización lineal (MILP).
    print("Construyendo modelo MILP...")
    modelo = construir_modelo_milp(params, all_toma_indices)
    
    # ---- TEMP DIAGNOSTIC BLOCK ----
    print("Vars:", len(modelo.variables()))
    print("Cons:", len(modelo.constraints))
    print("Nivel_min:", params['Nivel_minimo'])
    print("Nivel_max:", params['Nivel_maximo'])
    # --------------------------------

    # 4. Resolver el modelo y exportar los resultados a Excel, gráficos y texto.
    print("Resolviendo y exportando resultados...")
    df_detalle = resolver_y_exportar(modelo, params, all_toma_indices, OUTPUT_XLSX)

    # 1️⃣ Make JSON-safe
    safe_params = make_json_safe(params)

    # 2️⃣ Normalize numeric precision
    normalized_params = normalize_numbers(safe_params)

    # 3️⃣ Generate canonical string
    canonical_string = canonical_json_dumps(normalized_params)

    # 4️⃣ Compute hash
    canonical_hash = hashlib.sha256(canonical_string.encode("utf-8")).hexdigest()

    # 5️⃣ Store golden reference
    reference_payload = {
        "canonical_hash": canonical_hash,
        "canonical_json": json.loads(canonical_string)
    }

    with open("excel_params_reference.json", "w", encoding="utf-8") as f:
        json.dump(
            reference_payload,
            f,  # <-- REQUIRED
            indent=2,                # pretty file
            sort_keys=True,
            ensure_ascii=False
        )
    print(f"Canonical SHA256: {canonical_hash}")

    # 5. Generar una visualización interactiva HTML del árbol de distribución.
    print("Generando visualización interactiva...")
    _generar_arbol_completo_con_specs(OUTPUT_XLSX, INPUT_FILE)
    
    if df_detalle is not None:
        print("Listo.")

if __name__ == "__main__":
    main()