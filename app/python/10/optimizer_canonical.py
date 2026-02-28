#!/usr/bin/env python3
import sys
import json
import os
import traceback
import io
import tempfile
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
    solver_log_content = ""
    log_path = ""

    try:
        raw_input = sys.stdin.read()
        if not raw_input:
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

        # 5. Solve with log capture via temp file
        tmp_log = tempfile.NamedTemporaryFile(delete=False, suffix='.log')
        log_path = tmp_log.name
        tmp_log.close()  # Close immediately so CBC can write to it freely

        original_solve = modelo.solve
        modelo.solve = lambda: original_solve(PULP_CBC_CMD(msg=False, timeLimit=60, gapRel=0.05, threads=4, logPath=log_path))

        # 6. Extract results (resolver_y_exportar will call modelo.solve() internally)
        result_data = Optimizacion_RITEL_10.resolver_y_exportar(modelo, params, all_toma_indices, "unused.xlsx")

        # Read solver log immediately after solve
        if os.path.exists(log_path):
            with open(log_path, 'r', encoding='utf-8') as f:
                solver_log_content = f.read()
            os.unlink(log_path)
            log_path = ""

        if isinstance(result_data, tuple):
            df_detalle = result_data[0]
        else:
            df_detalle = result_data

        if df_detalle is None:
            print(json.dumps({
                "success": False,
                "message": "Optimal solution not found or failed to generate details.",
                "solver_status": LpStatus[modelo.status],
                "solver_log": solver_log_content
            }))
            sys.exit(0)

        # 7. Map to ResultParser schema while PRESERVING original columns
        nivel_key = 'Nivel TU Final (dBµV)'
        min_n = float(params['Nivel_minimo'])
        max_n = float(params['Nivel_maximo'])

        detail = []
        df_detalle_clean = df_detalle.where(pd.notnull(df_detalle), None)

        sys.stderr.write(f"DF Columns: {list(df_detalle.columns)}\n")

        for _, row in df_detalle_clean.iterrows():
            row_dict = row.to_dict()

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

        # 8. Output final structured JSON
        print(json.dumps({
            "success": True,
            "summary": summary,
            "detail": detail,
            "solver_status": LpStatus[modelo.status],
            "solver_log": solver_log_content
        }))

    except Exception as e:
        sys.stderr.write(f"Exception in optimizer_canonical: {str(e)}\n")
        sys.stderr.write(traceback.format_exc())
        print(json.dumps({
            "success": False,
            "message": str(e),
            "solver_log": solver_log_content
        }))
        sys.exit(1)

    finally:
        # Clean up temp log file if still exists
        if log_path and os.path.exists(log_path):
            os.unlink(log_path)

if __name__ == "__main__":
    main()