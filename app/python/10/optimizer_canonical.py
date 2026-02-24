#!/usr/bin/env python3
import sys
import json
import os
import traceback
import io
import pandas as pd
import pulp
from pulp import LpStatus, PULP_CBC_CMD

# Import from existing project structure
sys.path.append(os.path.dirname(__file__))
import Optimizacion_RITEL_10
from Funciones_apoyo_datos_entrada import generar_indices_y_validar_datos

# Mock out export functions to avoid file I/O and permission issues
Optimizacion_RITEL_10._exportar_a_excel = lambda *args, **kwargs: None
Optimizacion_RITEL_10._generar_graficos = lambda *args, **kwargs: None
Optimizacion_RITEL_10._dibujar_esquema_conexiones_ascii = lambda *args, **kwargs: None
Optimizacion_RITEL_10._generar_arbol_completo_con_specs = lambda *args, **kwargs: None

def main():
    # --- LOW LEVEL REDIRECTION (to catch solver's direct C output) ---
    fd_stdout = sys.stdout.fileno()
    saved_stdout_fd = os.dup(fd_stdout)
    os.dup2(sys.stderr.fileno(), fd_stdout)

    try:
        raw_input = sys.stdin.read()
        if not raw_input:
            os.dup2(saved_stdout_fd, fd_stdout)
            print(json.dumps({"success": False, "message": "No input received via stdin"}))
            sys.exit(1)

        params = json.loads(raw_input)

        # Convert string keys back to tuples for Python logic
        if 'largo_cable_derivador_repartidor' in params:
            new_map = {}
            for k, v in params['largo_cable_derivador_repartidor'].items():
                parts = tuple(map(int, k.split('|')))
                new_map[parts] = v
            params['largo_cable_derivador_repartidor'] = new_map

        if 'tus_requeridos_por_apartamento' in params:
            new_map = {}
            for k, v in params['tus_requeridos_por_apartamento'].items():
                parts = tuple(map(int, k.split('|')))
                new_map[parts] = v
            params['tus_requeridos_por_apartamento'] = new_map

        if 'largo_cable_tu' in params:
            new_map = {}
            for k, v in params['largo_cable_tu'].items():
                parts = tuple(map(int, k.split('|')))
                new_map[parts] = v
            params['largo_cable_tu'] = new_map

        # 3. Generate indices and validate
        all_toma_indices = generar_indices_y_validar_datos(params)

        # 4. Construct model
        modelo = Optimizacion_RITEL_10.construir_modelo_milp(params, all_toma_indices)
        
        # 5. Solve and Extract results using the exact logic from Optimizacion_RITEL_10
        # We override solve to be silent
        original_solve = modelo.solve
        # Set a 20 second time limit and a 5% optimality gap for much faster responses
        modelo.solve = lambda: original_solve(PULP_CBC_CMD(msg=False, timeLimit=20, gapRel=0.05, threads=4))
        
        # NOTE: resolver_y_exportar returns a tuple (df_detalle, ...) or just df_detalle 
        # based on context. Let's handle both.
        result_data = Optimizacion_RITEL_10.resolver_y_exportar(modelo, params, all_toma_indices, "unused.xlsx")

        if isinstance(result_data, tuple):
            df_detalle = result_data[0]
        else:
            df_detalle = result_data

        if df_detalle is None:
             os.dup2(saved_stdout_fd, fd_stdout)
             print(json.dumps({
                 "success": False, 
                 "message": f"Optimal solution not found or failed to generate details. Status: {LpStatus[modelo.status]}"
             }))
             sys.exit(0)

        # 6. Map to ResultParser schema while PRESERVING original columns
        nivel_key = 'Nivel TU Final (dBµV)'
        min_n = float(params['Nivel_minimo'])
        max_n = float(params['Nivel_maximo'])
        
        detail = []
        # Replace NaN with None globally for JSON safety
        df_detalle_clean = df_detalle.where(pd.notnull(df_detalle), None)
        
        # Log columns for debugging in Apache logs
        sys.stderr.write(f"DF Columns: {list(df_detalle.columns)}\n")

        for _, row in df_detalle_clean.iterrows():
            row_dict = row.to_dict()
            
            # Standardize fields for ResultParser (using get to avoid KeyError)
            val = float(row_dict.get(nivel_key, 0) or 0)
            cumple = 1 if (min_n <= val <= max_n) else 0
            
            losses = [
                {"segment": "riser_dentro_del_bloque", "value": float(row_dict.get('Pérdida Riser dentro del Bloque (dB)', 0) or 0)},
                {"segment": "riser_atenuacion_conectores", "value": float(row_dict.get('Riser Atenuacion Conectores (dB)', 0) or 0)},
                {"segment": "riser_atenuacin_taps", "value": float(row_dict.get('Riser Atenuación Taps (dB)', 0) or 0)},
                {"segment": "feeder_cable", "value": float(row_dict.get('Pérdida Feeder (cable) (dB)', 0) or 0)},
                {"segment": "feeder_conectores", "value": float(row_dict.get('Pérdida Feeder (conectores) (dB)', 0) or 0)},
                {"segment": "derivador_piso", "value": float(row_dict.get('Pérdida Derivador Piso (dB)', 0) or 0)},
                {"segment": "cable_derivrep", "value": float(row_dict.get('Pérdida Cable Deriv→Rep (dB)', 0) or 0)},
                {"segment": "cable_reptu", "value": float(row_dict.get('Pérdida Cable Rep→TU (dB)', 0) or 0)},
                {"segment": "conexin_tu", "value": float(row_dict.get('Pérdida Conexión TU (dB)', 0) or 0)},
                {"segment": "total", "value": float(row_dict.get('Pérdida Total (dB)', 0) or 0)}
            ]

            row_dict.update({
                "tu_id": str(row_dict.get('Toma', '')),
                "piso": int(row_dict.get('Piso', 0)),
                "apto": int(row_dict.get('Apto', 0)),
                "bloque": int(row_dict.get('Bloque', 0)),
                "nivel_tu": val,
                "nivel_min": min_n,
                "nivel_max": max_n,
                "cumple": cumple,
                "losses": losses
            })
            detail.append(row_dict)

        # Summary JSON
        summary = {
            "contract_version": 2,
            "piso_max": int(params['Piso_Maximo']),
            "total_tus": len(all_toma_indices),
            "status": "Optimal",
            "avg_nivel_tu": float(df_detalle[nivel_key].mean()) if nivel_key in df_detalle.columns else 0,
            "min_nivel_tu": float(df_detalle[nivel_key].min()) if nivel_key in df_detalle.columns else 0,
            "max_nivel_tu": float(df_detalle[nivel_key].max()) if nivel_key in df_detalle.columns else 0
        }

        # 7. Output final structured JSON
        os.dup2(saved_stdout_fd, fd_stdout)
        os.close(saved_stdout_fd)

        print(json.dumps({
            "success": True,
            "summary": summary,
            "detail": detail
        }))

    except Exception as e:
        if 'saved_stdout_fd' in locals():
            os.dup2(saved_stdout_fd, fd_stdout)
        sys.stderr.write(f"Exception in optimizer_canonical: {str(e)}\n")
        sys.stderr.write(traceback.format_exc())
        print(json.dumps({"success": False, "message": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
