# Importación de librerías necesarias
import pandas as pd  # Para manipulación de datos, especialmente con archivos Excel
import json          # Para trabajar con formato JSON, usado para pasar datos a JavaScript
import os            # Para interactuar con el sistema operativo, como obtener rutas de archivos
import re            # Para usar expresiones regulares, útil para extraer información de strings

def _generar_arbol_completo_con_specs(archivo_resultados, archivo_specs):
    """
    Genera una visualización HTML interactiva de la topología de red TDT.

    Lee los resultados de la optimización y las especificaciones de los componentes para
    construir un árbol jerárquico que se visualiza usando una plantilla HTML y la librería vis.js.

    Args:
        archivo_resultados (str): Ruta al archivo Excel con los resultados de la optimización (topología).
        archivo_specs (str): Ruta al archivo Excel con las especificaciones técnicas de los componentes.
    """
    # 1. CARGAR DATOS
    try:
        # Carga los datos desde los archivos Excel.
        # 'Detalle_Tomas' contiene la información de cada toma de usuario final.
        df_tomas = pd.read_excel(archivo_resultados, sheet_name='Detalle_Tomas')
        # 'Resumen_Bloques' tiene información agregada por bloque de edificio.
        #df_bloques = pd.read_excel(archivo_resultados, sheet_name='Resumen_Bloques')
        df_bloques =df_tomas[['Bloque', 'Piso Entrada Riser Bloque']].drop_duplicates().reset_index(drop=True)
        # Carga las especificaciones técnicas de los componentes.
        df_deriv_specs = pd.read_excel(archivo_specs, sheet_name='derivadores_data')
        df_repar_specs = pd.read_excel(archivo_specs, sheet_name='repartidores_data')
        
    except Exception as e:
        print(f"Error cargando archivos: {e}")
        return

    # --- 2. CREAR DICCIONARIOS DE ESPECIFICACIONES (LOOKUP) ---
    # Se crean diccionarios para un acceso rápido a las especificaciones de los componentes
    # por su modelo, evitando búsquedas repetitivas en los DataFrames.
    specs_derivadores = {row['Modelo']: {'derivacion': row['derivacion'], 'paso': row['paso']} for _, row in df_deriv_specs.iterrows()}
    specs_repartidores = {row['Modelo']: {'insercion': row['perdida_insercion']} for _, row in df_repar_specs.iterrows()}

    # --- 3. CONFIGURACIÓN DE COLORES Y ESTRUCTURA DE DATOS ---
    # Define códigos de color para los diferentes elementos de la red para una mejor visualización.
    C_CABECERA, C_TRONCAL, C_BLOQUE, C_PISO, C_APTO, C_OK, C_BAD = "#E74C3C", "#8C8C8C", "#8E44AD", "#2980B9", "#F39C12", "#27AE60", "#C0392B"

    # 'hierarchy_map' almacenará la estructura de árbol: {id_padre: [hijos]}
    # Cada hijo es un diccionario con la información del nodo y la conexión (edge).
    hierarchy_map = {} 
    def add_child(parent_id, child_node, edge_data):
        """Función auxiliar para construir la jerarquía del árbol."""
        hierarchy_map.setdefault(parent_id, []).append({'node': child_node, 'edge': edge_data})

    # --- 4. CONSTRUCCIÓN DEL ÁRBOL JERÁRQUICO ---
    
    # Se toma una fila de referencia para obtener datos comunes que se repiten.
    row_ref = df_tomas.iloc[0]
    max_piso = max(int(match.group(1)) for t in df_tomas['Toma'] if (match := re.search(r'P(\d+)', t)))

    # --- NIVEL 0: CABECERA (RAÍZ DEL ÁRBOL) ---
    potencia_cabecera = row_ref.get('P_in (entrada) (dBµV)', 0)    
    id_cabecera = "Cabecera_Master"
    node_cabecera = {
        "id": id_cabecera, "label": f"CABECERA \n (Piso {max_piso})\n{potencia_cabecera} dBµV ", 
        "title": "Origen Señal TDT", "color": C_CABECERA, "level": 0, 
        "shape": "diamond", "size": 55, "font": {"color": "black", "face": "arial", "size": 12, "vadjust": -75}
    }

    # --- NIVEL 1: TRONCAL ---
    id_troncal = "Troncal"
    piso_troncal = row_ref.get('Piso Troncal', 0)
    modelo_rep_troncal = row_ref.get('Repartidor Troncal', 'N/A')
    spec_troncal = specs_repartidores.get(modelo_rep_troncal, {'insercion': '?'})
    
    title_troncal = f"Repartidor Troncal, Modelo: {modelo_rep_troncal}, Pérdida Inserción: {spec_troncal['insercion']} dB"
    len_ant = row_ref.get('Longitud Antena→Troncal (m)', 0)
    loss_ant_total = row_ref.get('Pérdida Antena→Troncal (cable) (dB)', 0) + row_ref.get('Pérdida Antena↔Troncal (conectores) (dB)', 0)

    node_troncal = {"id": id_troncal, "label": f"TRONCAL\n(Piso {piso_troncal})", "title": title_troncal, "color": C_TRONCAL, "level": 1, "shape": "box", "font": {"color": "black"}}
    add_child(id_cabecera, node_troncal, {"from": id_cabecera, "to": id_troncal, "label": f"{len_ant}m\n-{loss_ant_total:.1f}dB"})

    # --- NIVEL 2: BLOQUES ---
    mapa_bloques = {} # Diccionario para mapear ID de bloque a ID de nodo.
    for _, row in df_bloques.iterrows():
        b_id, piso_in = row['Bloque'], row['Piso Entrada Riser Bloque']
        row_bloque_data = df_tomas[df_tomas['Bloque'] == b_id].iloc[0]
        len_feed = row_bloque_data.get('Feeder Troncal→Entrada Bloque (m)', 0)
        loss_feed_total = row_bloque_data.get('Pérdida Feeder (cable) (dB)', 0) + row_bloque_data.get('Pérdida Feeder (conectores) (dB)', 0)
        id_bloque = f"Bloque_{b_id}"
        
        node_bloque = {"id": id_bloque, "label": f"BLOQUE {b_id}\nPiso {piso_in}", "title": f"Entrada Riser: P{piso_in}", "color": C_BLOQUE, "level": 2, "shape": "box", "font": {"color": "black"}}
        add_child(id_troncal, node_bloque, {"from": id_troncal, "to": id_bloque, "label": f"{len_feed}m\n-{loss_feed_total:.1f}dB"})
        mapa_bloques[b_id] = id_bloque

    # --- NIVELES 3 (PISOS), 4 (APTOS), 5 (TOMAS) ---
    df_sorted = df_tomas.sort_values(by=['Bloque', 'Toma'], ascending=[True, False])
    pisos_creados, aptos_creados = set(), set()

    for _, row in df_sorted.iterrows():
        codigo, bloque = row['Toma'], row['Bloque']
        match = re.search(r'P(\d+)A(\d+)TU(\d+)', codigo)
        if not match: continue
        
        piso, apto, toma_num = match.groups()
        id_bloque_parent, id_piso, id_apto, id_toma = mapa_bloques.get(bloque), f"B{bloque}_P{piso}", f"B{bloque}_P{piso}_A{apto}", codigo
        if not id_bloque_parent: continue

        # --- CÁLCULO DE DISTANCIAS Y PÉRDIDAS PARA ETIQUETAS ---
        d_riser, l_riser = row.get('Distancia riser dentro bloque (m)', 0), row.get('Pérdida Riser dentro del Bloque (dB)', 0)
        d_total = row.get('Distancia total hasta la toma (m)', 0)
        d_upstream = row.get('Longitud Antena→Troncal (m)', 0) + row.get('Feeder Troncal→Entrada Bloque (m)', 0) + d_riser
        d_remaining = max(0, d_total - d_upstream)
        l_piso_apto, l_apto_toma = row.get('Pérdida Cable Deriv→Rep (dB)', 0), row.get('Pérdida Cable Rep→TU (dB)', 0)
        
        # Distribuye la distancia restante proporcionalmente a las pérdidas de los tramos locales.
        total_local_loss = l_piso_apto + l_apto_toma
        d_piso_apto = (l_piso_apto / total_local_loss) * d_remaining if total_local_loss > 0 else d_remaining / 2
        d_apto_toma = (l_apto_toma / total_local_loss) * d_remaining if total_local_loss > 0 else d_remaining / 2

        # --- NODO PISO (DERIVADOR) ---
        if id_piso not in pisos_creados:
            modelo_deriv = row.get('Derivador Piso', 'N/A')
            spec_deriv = specs_derivadores.get(modelo_deriv, {'derivacion': '?', 'paso': '?'})
            title_piso = f"Piso {piso}, Derivador: {modelo_deriv}, Pérdida Paso: {spec_deriv['paso']} dB, Pérdida Derivación: {spec_deriv['derivacion']} dB"
            
            node_piso = {"id": id_piso, "label": f"Piso {piso}", "title": title_piso, "color": C_PISO, "level": 3, "shape": "ellipse", "font": {"color": "black"}}
            add_child(id_bloque_parent, node_piso, {"from": id_bloque_parent, "to": id_piso, "label": f"{d_riser}m\n-{l_riser}dB"})
            pisos_creados.add(id_piso)

        # --- NODO APTO (REPARTIDOR) ---
        if id_apto not in aptos_creados:
            modelo_rep_apt = row.get('Repartidor Apt', 'N/A')
            spec_rep_apt = specs_repartidores.get(modelo_rep_apt, {'insercion': '?'})
            title_apto = f"Apto {apto}, Repartidor: {modelo_rep_apt}, Pérdida Inserción: {spec_rep_apt['insercion']} dB"
            
            node_apto = {"id": id_apto, "label": f"Apto {apto}", "title": title_apto, "color": C_APTO, "level": 4, "shape": "dot", "size": 15}
            l_piso_apto += 2 * 0.2  # Añade pérdida por conectores
            add_child(id_piso, node_apto, {"from": id_piso, "to": id_apto, "label": f"{d_piso_apto:.1f}m\n-{l_piso_apto:.1f}dB"})
            aptos_creados.add(id_apto)

        # --- NODO TOMA ---
        nivel, color_toma = row['Nivel TU Final (dBµV)'], C_OK if 47 <= row['Nivel TU Final (dBµV)'] <= 77 else C_BAD
        node_toma = {"id": id_toma, "label": f"TU{toma_num}\n{nivel}dB", "title": f"Toma: {codigo}, Pérdida TU: 1 dBµV", "color": color_toma, "level": 5, "shape": "dot", "size": 10}
        l_apto_toma += 2 * 0.2  # Añade pérdida por conectores
        add_child(id_apto, node_toma, {"from": id_apto, "to": id_toma, "label": f"{d_apto_toma:.1f}m\n-{l_apto_toma:1g}dB"})

    # --- 5. PREPARAR DATOS PARA LA PLANTILLA HTML ---
    # Se definen los nodos y conexiones que se mostrarán al cargar la página (vista inicial).
    initial_nodes, initial_edges = [node_cabecera], []
    if id_cabecera in hierarchy_map:
        for child in hierarchy_map[id_cabecera]: # Troncal
            initial_nodes.append(child['node'])
            initial_edges.append(child['edge'])
            if child['node']['id'] in hierarchy_map:
                for b in hierarchy_map[child['node']['id']]: # Bloques
                    initial_nodes.append(b['node'])
                    initial_edges.append(b['edge'])

    # --- 6. GENERAR ARCHIVO HTML FINAL ---
    with open('arbol_template.html', 'r', encoding='utf-8') as f:
        template_content = f.read()

    template_data = {
        'c_cabecera': C_CABECERA, 'c_troncal': C_TRONCAL, 'c_bloque': C_BLOQUE, 'c_piso': C_PISO, 'c_apto': C_APTO,
        'initial_nodes_json': json.dumps(initial_nodes, indent=2),
        'initial_edges_json': json.dumps(initial_edges, indent=2),
        'hierarchy_map_json': json.dumps(hierarchy_map, indent=2)
    }
    html_content = template_content.format(**template_data)

    filename = "Arbol_TDT_Specs.html"
    with open(filename, "w", encoding="utf-8") as f:
        f.write(html_content)
    
    print(f"✅ Archivo de visualización interactiva generado: {os.path.abspath(filename)}")

# --- BLOQUE DE EJECUCIÓN (COMENTADO) ---
# Este bloque permite ejecutar este script de forma independiente para regenerar la visualización.
# Descomentar solo si es necesario para pruebas.
# if __name__ == "__main__":
#     file_res = 'Resultados_Optimizacion_TDT_Troncal.xlsx'
#     file_dat = 'datos_entrada.xlsx'
#     if os.path.exists(file_res) and os.path.exists(file_dat):
#         _generar_arbol_completo_con_specs(file_res, file_dat)
#     else:
#         print("Error: Asegúrate de que 'Resultados_Optimizacion_TDT_Troncal.xlsx' y 'datos_entrada.xlsx' existan.")