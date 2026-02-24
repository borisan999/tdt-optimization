#!/usr/bin/env python3
import sys
import json
import os
import time
import pandas as pd
import pulp
from pulp import LpStatus, PULP_CBC_CMD

# Import from existing project structure
sys.path.append(os.path.join(os.path.dirname(__file__), '../app/python/10'))
import Optimizacion_RITEL_10
from Funciones_apoyo_datos_entrada import generar_indices_y_validar_datos

def run_test(params, time_limit, gap):
    all_toma_indices = generar_indices_y_validar_datos(params)
    modelo = Optimizacion_RITEL_10.construir_modelo_milp(params, all_toma_indices)
    
    start_time = time.time()
    modelo.solve(PULP_CBC_CMD(msg=False, timeLimit=time_limit, gapRel=gap, threads=4))
    end_time = time.time()
    
    status = LpStatus[modelo.status]
    obj_value = pulp.value(modelo.objective)
    
    return {
        "time_limit": time_limit,
        "gap": gap,
        "status": status,
        "objective": obj_value,
        "duration": end_time - start_time
    }

def main():
    try:
        raw_input = sys.stdin.read()
        if not raw_input:
            print(json.dumps({"success": False, "message": "No input"}))
            return

        params = json.loads(raw_input)

        # Fix key encoding as in optimizer_canonical.py
        for key in ['largo_cable_derivador_repartidor', 'tus_requeridos_por_apartamento', 'largo_cable_tu']:
            if key in params:
                new_map = {}
                for k, v in params[key].items():
                    parts = tuple(map(int, k.split('|')))
                    new_map[parts] = v
                params[key] = new_map

        tests = [
            {"time": 2, "gap": 0.10},
            {"time": 5, "gap": 0.05},
            {"time": 20, "gap": 0.05}, # Current production settings
            {"time": 60, "gap": 0.01}, # High precision
            {"time": 120, "gap": 0.00}, # Optimal
        ]

        results = []
        for t in tests:
            res = run_test(params, t["time"], t["gap"])
            results.append(res)

        print(json.dumps({"success": True, "results": results}, indent=2))

    except Exception as e:
        print(json.dumps({"success": False, "message": str(e)}))

if __name__ == "__main__":
    main()
