import pandas as pd
import random
import numpy as np
import sys

# --- Manejo de argumentos de entrada ---
if len(sys.argv) > 1:
    seed = int(sys.argv[1])
else:
    seed = 11
if len(sys.argv) > 2:
    Piso_Maximo = int(sys.argv[2])
else:
    Piso_Maximo = 15
if len(sys.argv) > 3:
    apartamentos_por_piso = int(sys.argv[3])
else:
    apartamentos_por_piso = 4
    
distancia_cabecera_ultimo_piso = 7
potencia_entrada = 110 
nivel_minimo = 48
nivel_maximo = 69
potencia_objetivo = 58
    
random.seed(seed)
print(f"Semilla utilizada: {seed}")
print(f"Generando datos para un edificio de {Piso_Maximo} pisos con {apartamentos_por_piso} apartamentos por piso...")

pisos = list(range(Piso_Maximo, 0, -1))

# --- Generar parámetros aleatorios ---
tus_requeridos_por_apartamento = {
    (p, a): random.randint(1, 5)
    for p in pisos
    for a in range(1, apartamentos_por_piso + 1)
}
largo_cable_derivador_repartidor = {
    (p, a): random.randint(5, 15)
    for p in pisos
    for a in range(1, apartamentos_por_piso + 1)
}
largo_cable_tu = {
    (p, a, tu_idx): random.randint(5, 20)
    for (p, a), num_tomas in tus_requeridos_por_apartamento.items()
    for tu_idx in range(1, num_tomas + 1)
}

largo_cable_feeder_bloque = 3 # distancia mínima entre el feeder y el bloque
# --- Datos de equipos (sin cambios) ---
derivadores_data = {
    'TLV519325': {'derivacion': 24.0, 'paso': 1.5, 'salidas': 2},
    'TLV519324': {'derivacion': 20.0, 'paso': 1.5, 'salidas': 2},
    'TLV519323': {'derivacion': 16.0, 'paso': 2.0, 'salidas': 2},
    'TLV519322': {'derivacion': 12.0, 'paso': 2.3, 'salidas': 2},
    'TLV519345': {'derivacion': 23.2, 'paso': 2.3, 'salidas': 4},
    'TLV519344': {'derivacion': 20.5, 'paso': 2.2, 'salidas': 4},
    'TLV519343': {'derivacion': 17.0, 'paso': 2.5, 'salidas': 4},
    'TLV519342': {'derivacion': 13.0, 'paso': 2.5, 'salidas': 4},
    # 'TLV519341': {'derivacion': 8.0, 'paso': 0.0, 'salidas': 4},
    'TLV519365': {'derivacion': 24.0, 'paso': 2.0, 'salidas': 6},
    'TLV519364': {'derivacion': 20.0, 'paso': 3.0, 'salidas': 6},
    'TLV519363': {'derivacion': 17.0, 'paso': 5.0, 'salidas': 6},
    # 'TLV519362': {'derivacion': 12.0, 'paso': 0.0, 'salidas': 6},
    'TLV519385': {'derivacion': 24.5, 'paso': 2.2, 'salidas': 8},
    'TLV519384': {'derivacion': 20.0, 'paso': 4.5, 'salidas': 8},
    'TLV519383': {'derivacion': 17.5, 'paso': 5.5, 'salidas': 8},
    # 'TLV519382': {'derivacion': 15.0, 'paso': 0.0, 'salidas': 8},
}
repartidores_data = {
    'TLV453003': {'perdida_insercion': 4.0, 'salidas': 2},
    'TLV519502': {'perdida_insercion': 5.0, 'salidas': 2},
    'TLV519503': {'perdida_insercion': 8.0, 'salidas': 3},
    'TLV519504': {'perdida_insercion': 9.0, 'salidas': 4},
    'TLV519505': {'perdida_insercion': 11.0, 'salidas': 5},
    'TLV519506': {'perdida_insercion': 12.0, 'salidas': 6},
    'TLV519508': {'perdida_insercion': 15.0, 'salidas': 8},
}

# --- Exportar todos los datos a un solo archivo de Excel con múltiples hojas ---
output_file = 'datos_entrada.xlsx'
with pd.ExcelWriter(output_file) as writer:
    # 1. Parámetros generales
    parametros_generales_data = {
        'Parametro': ['Piso_Maximo', 
                      'Apartamentos_Piso',
                      'Largo_Cable_Amplificador_Ultimo_Piso',
                      'Potencia_Entrada_dBuV',
                      'Nivel_Minimo_dBuV',
                      'Nivel_Maximo_dBuV',
                      'Potencia_Objetivo_TU_dBuV',
                      'Largo_Feeder_Bloque_m (Mínimo)'],
        'Valor': [Piso_Maximo, 
                  apartamentos_por_piso, 
                  distancia_cabecera_ultimo_piso, 
                  potencia_entrada,
                  nivel_minimo,
                  nivel_maximo,
                  potencia_objetivo,
                  largo_cable_feeder_bloque,]
    }
    df_parametros_generales = pd.DataFrame(parametros_generales_data)
    df_parametros_generales.to_excel(writer, sheet_name='Parametros_Generales', index=False)
    print("Hoja 'Parametros_Generales' creada.")

    # 2. largo_cable_derivador_repartido
    data_lc_dr = [{'Piso': k[0], 'Apartamento': k[1], 'Longitud_m': v} for k, v in largo_cable_derivador_repartidor.items()]
    df_lc_dr = pd.DataFrame(data_lc_dr).set_index(['Piso', 'Apartamento'])
    df_lc_dr.to_excel(writer, sheet_name='largo_cable_derivador_repartido', merge_cells=False)
    print("Hoja 'largo_cable_derivador_repartido' creada.")

    # 3. tus_requeridos_por_apartamento
    data_tr_pa = [{'Piso': k[0], 'Apartamento': k[1], 'Cantidad_Tomas': v} for k, v in tus_requeridos_por_apartamento.items()]
    df_tr_pa = pd.DataFrame(data_tr_pa).set_index(['Piso', 'Apartamento'])
    df_tr_pa.to_excel(writer, sheet_name='tus_requeridos_por_apartamento', merge_cells=False)
    print("Hoja 'tus_requeridos_por_apartamento' creada.")

    # 4. largo_cable_tu
    data_lc_tu = [{'Piso': k[0], 'Apartamento': k[1], 'TU_Idx': k[2], 'Longitud_m': v} for k, v in largo_cable_tu.items()]
    df_lc_tu = pd.DataFrame(data_lc_tu).set_index(['Piso', 'Apartamento', 'TU_Idx'])
    df_lc_tu.to_excel(writer, sheet_name='largo_cable_tu', merge_cells=False)
    print("Hoja 'largo_cable_tu' creada.")

    # 5. derivadores_data
    df_derivadores = pd.DataFrame.from_dict(derivadores_data, orient='index')
    df_derivadores.index.name = 'Modelo'
    df_derivadores.to_excel(writer, sheet_name='derivadores_data')
    print("Hoja 'derivadores_data' creada.")

    # 6. repartidores_data
    df_repartidores = pd.DataFrame.from_dict(repartidores_data, orient='index')
    df_repartidores.index.name = 'Modelo'
    df_repartidores.to_excel(writer, sheet_name='repartidores_data')
    print("Hoja 'repartidores_data' creada.")

print(f"\n¡Todos los datos se han guardado en el archivo '{output_file}'!")