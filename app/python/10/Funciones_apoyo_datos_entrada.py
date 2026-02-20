import os
import sys
import math
import pandas as pd
import numpy as np

# -------------------------
# División en bloques (3 a 5 pisos por bloque, lo más uniforme posible)
# -------------------------
def dividir_en_bloques(pisos_max):
    """
    Divide el número total de pisos en bloques de 3 a 5 pisos cada uno, de la forma más uniforme posible.
    Esta función es clave para segmentar el edificio en zonas de distribución manejables.

    Args:
        pisos_max (int): El número total de pisos del edificio.

    Returns:
        List[List[int]]: Una lista de bloques, donde cada bloque es una lista de números de piso en orden descendente.
    """
    # Si el edificio es pequeño (6 pisos o menos), se considera un único bloque.
    if pisos_max <= 6:
        return [list(range(pisos_max, 0, -1))]

    # Determina el rango de número de bloques posibles según las reglas (3 a 5 pisos por bloque).
    min_blocks = max(math.ceil(pisos_max / 5), 1)
    max_blocks = max(math.floor(pisos_max / 3), 1)

    best_partition, best_balance = None, None
    # Itera para encontrar la partición con el tamaño de bloques más balanceado.
    for nb in range(min_blocks, max_blocks + 1):
        base = pisos_max // nb
        extra = pisos_max % nb
        sizes = [base + (1 if i < extra else 0) for i in range(nb)]
        # Si la partición es válida (todos los bloques entre 3 y 5 pisos), calcula su "balance" (varianza).
        if all(3 <= s <= 5 for s in sizes):
            balance = np.var(sizes)
            # Guarda la partición si es la más balanceada encontrada hasta ahora.
            if best_balance is None or balance < best_balance:
                best_balance = balance
                best_partition = sizes

    # Si no se encontró una partición ideal, se fuerza una con un número fijo de bloques (ej. 3).
    if best_partition is None:
        nb = 3
        base = pisos_max // nb
        extra = pisos_max % nb
        best_partition = [base + (1 if i < extra else 0) for i in range(nb)]

    # Construye la lista de bloques a partir de la partición de tamaños seleccionada.
    bloques, piso_actual = [], pisos_max
    for tam in best_partition:
        bloque = list(range(piso_actual, piso_actual - tam, -1))  # descendente
        bloques.append(bloque)
        piso_actual -= tam
    return bloques


# -------------------------
# Util: leer hojas de Excel
# -------------------------
def leer_datos(sheet_name, INPUT_FILE, index_col=None):
    """
    Lee una hoja específica de un archivo Excel y la devuelve como un DataFrame de pandas.
    Esta función centraliza la lectura de datos para facilitar el manejo de errores.

    Args:
        sheet_name (str): Nombre de la hoja de Excel a leer.
        INPUT_FILE (str): Ruta al archivo Excel.
        index_col (list, optional): Columna(s) a usar como índice del DataFrame.

    Returns:
        pd.DataFrame: Los datos cargados de la hoja de Excel.

    Raises:
        FileNotFoundError: Si el archivo `INPUT_FILE` no se encuentra.
        RuntimeError: Si ocurre cualquier otro error durante la lectura.
    """
    try:
        return pd.read_excel(INPUT_FILE, sheet_name=sheet_name, index_col=index_col)
    except FileNotFoundError:
        raise
    except Exception as e:
        raise RuntimeError(f"Error leyendo hoja '{sheet_name}': {e}") from e


# -------------------------
# Cargar parámetros y datos desde Excel
# -------------------------
def cargar_datos_y_parametros(INPUT_FILE):
    """
    Carga los parámetros generales y las tablas de datos desde el archivo Excel.
    
    Lee la hoja 'Parametros_Generales' y otras hojas de datos (longitudes de cable,
    componentes, etc.) para construir un diccionario de parámetros completo que
    alimentará el modelo de optimización.

    Args:
        INPUT_FILE (str): Ruta al archivo de entrada de Excel.
    
    Returns:
        dict: Un diccionario que contiene todos los parámetros y datos necesarios para el modelo.
    
    Raises:
        SystemExit: Si el archivo de entrada no existe.
    """
    if not os.path.exists(INPUT_FILE):
        print(f"Error: no se encuentra el archivo '{INPUT_FILE}'.")
        sys.exit(1)

    # Lee la hoja de parámetros generales.
    df_param = leer_datos("Parametros_Generales", INPUT_FILE,index_col=0)
    # Función auxiliar para extraer un valor de forma segura.
    def getv(key, default=None, cast=float):
        return cast(df_param.loc[key, 'Valor']) if key in df_param.index else default

    # Extrae parámetros generales del edificio y la red.
    Piso_Maximo = getv('Piso_Maximo', cast=int)
    apartamentos_por_piso = getv('Apartamentos_Piso', cast=int)

    # Extrae parámetros de atenuación y niveles de señal.
    atenuacion_cable_por_metro = getv('Atenuacion_Cable_dBporM', 0.2)
    atenuacion_cable_470mhz = getv('Atenuacion_Cable_470MHz_dBporM', 0.127)
    atenuacion_cable_698mhz = getv('Atenuacion_Cable_698MHz_dBporM', 0.1558)
    atenuacion_conector = getv('Atenuacion_Conector_dB', 0.2)
    largo_cable_entre_pisos = getv('Largo_Entre_Pisos_m', 3.0)
    potencia_entrada = getv('Potencia_Entrada_dBuV', 110.0)
    Nivel_minimo = getv('Nivel_Minimo_dBuV', 47.0)
    Nivel_maximo = getv('Nivel_Maximo_dBuV', 70.0)
    Potencia_Objetivo_TU = getv('Potencia_Objetivo_TU_dBuV', 60.0)
    conectores_por_union = int(getv('Conectores_por_Union', 2, cast=float))
    atenuacion_conexion_tu = getv('Atenuacion_Conexion_TU_dB', 1.0)
    largo_cable_amplificador_ultimo_piso = getv('Largo_Cable_Amplificador_Ultimo_Piso',5)
    largo_cable_feeder_bloque = getv('Largo_Feeder_Bloque_m (Mínimo)', 3.0)
    
    # Lee las tablas de datos y las convierte en diccionarios para fácil acceso.
    df_lc_dr = leer_datos('largo_cable_derivador_repartido', INPUT_FILE, index_col=[0, 1])
    largo_cable_derivador_repartidor = df_lc_dr['Longitud_m'].to_dict()

    df_tr_pa = leer_datos('tus_requeridos_por_apartamento', INPUT_FILE,  index_col=[0, 1])
    tus_requeridos_por_apartamento = df_tr_pa['Cantidad_Tomas'].to_dict()

    df_lc_tu = leer_datos('largo_cable_tu', INPUT_FILE, index_col=[0, 1, 2])
    largo_cable_tu = df_lc_tu['Longitud_m'].to_dict()

    df_deriv = leer_datos('derivadores_data', INPUT_FILE, index_col=0)
    derivadores_data = df_deriv.to_dict(orient='index')

    df_rep = leer_datos('repartidores_data', INPUT_FILE, index_col=0)
    repartidores_data = df_rep.to_dict(orient='index')

    # Calcula la ubicación del repartidor troncal (aproximadamente a mitad del edificio).
    p_troncal = int(round(Piso_Maximo / 2))

    # Devuelve todos los parámetros en un único diccionario.
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
        'atenuacion_cable_470mhz': atenuacion_cable_470mhz,
        'atenuacion_cable_698mhz': atenuacion_cable_698mhz,
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
    """
    Genera y valida los índices de las Tomas de Usuario (TU) basados en la configuración.

    Crea una lista completa de todas las TUs del edificio (piso, apartamento, índice de toma)
    y realiza validaciones para asegurar que todos los datos necesarios (como longitudes de
    cable) estén definidos en los parámetros.

    Args:
        params (dict): El diccionario de parámetros cargado desde el archivo Excel.

    Returns:
        list: Una lista de tuplas (piso, apartamento, tu_idx) que representa todas las TUs.

    Raises:
        ValueError: Si falta alguna especificación de longitud de cable para una TU o un apartamento.
    """
    
    pisos = list(range(params['Piso_Maximo'], 0, -1))
    all_toma_indices = []
    # Genera la lista de todas las tomas (TUs) del edificio.
    for p in pisos:
        for a in range(1, params['apartamentos_por_piso'] + 1):
            num_tomas = params['tus_requeridos_por_apartamento'].get((p, a), 0)
            for tu_idx in range(1, num_tomas + 1):
                all_toma_indices.append((p, a, tu_idx))

    # Realiza validaciones cruzadas para asegurar la integridad de los datos.
    # Verifica que cada TU definida tenga una longitud de cable asociada.
    for (p, a), cnt in params['tus_requeridos_por_apartamento'].items():
        for tu_idx in range(1, cnt + 1):
            if (p, a, tu_idx) not in params['largo_cable_tu']:
                raise ValueError(f"Falta longitud de cable TU para (p={p}, a={a}, tu={tu_idx})")
        # Verifica que cada apartamento tenga una longitud de cable desde el derivador.
        if (p, a) not in params['largo_cable_derivador_repartidor']:
            raise ValueError(f"Falta longitud derivador->repartidor para (p={p}, a={a})")
            
    return all_toma_indices
