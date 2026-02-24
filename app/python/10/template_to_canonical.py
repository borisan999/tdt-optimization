import sys
import json
from typing import List, Dict, Any

DEFAULT_DERIVADORES = {
    'TLV519322': {'derivacion': 12.0, 'paso': 2.3, 'salidas': 2},
    'TLV519323': {'derivacion': 16.0, 'paso': 2.0, 'salidas': 2},
    'TLV519324': {'derivacion': 20.0, 'paso': 1.5, 'salidas': 2},
    'TLV519325': {'derivacion': 24.0, 'paso': 1.5, 'salidas': 2},
    'TLV519342': {'derivacion': 13.0, 'paso': 2.5, 'salidas': 4},
    'TLV519343': {'derivacion': 17.0, 'paso': 2.5, 'salidas': 4},
    'TLV519344': {'derivacion': 20.5, 'paso': 2.2, 'salidas': 4},
    'TLV519345': {'derivacion': 23.2, 'paso': 2.3, 'salidas': 4},
    'TLV519363': {'derivacion': 17.0, 'paso': 5.0, 'salidas': 6},
    'TLV519364': {'derivacion': 20.0, 'paso': 3.0, 'salidas': 6},
    'TLV519365': {'derivacion': 24.0, 'paso': 2.0, 'salidas': 6},
    'TLV519383': {'derivacion': 17.5, 'paso': 5.5, 'salidas': 8},
    'TLV519384': {'derivacion': 20.0, 'paso': 4.5, 'salidas': 8},
    'TLV519385': {'derivacion': 24.5, 'paso': 2.2, 'salidas': 8}
}

DEFAULT_REPARTIDORES = {
    'TLV453003': {'salidas': 2, 'perdida_insercion': 4.0},
    'TLV519502': {'salidas': 2, 'perdida_insercion': 5.0},
    'TLV519503': {'salidas': 3, 'perdida_insercion': 8.0},
    'TLV519504': {'salidas': 4, 'perdida_insercion': 9.0},
    'TLV519505': {'salidas': 5, 'perdida_insercion': 11.0},
    'TLV519506': {'salidas': 6, 'perdida_insercion': 12.0},
    'TLV519508': {'salidas': 8, 'perdida_insercion': 15.0}
}

def parse_ranges(range_str: str) -> List[int]:
    """Convierte un string como '1-5, 8, 11-12' en una lista de enteros."""
    if not range_str:
        return []
    result = set()
    parts = str(range_str).split(',')
    for part in parts:
        part = part.strip()
        if not part: continue
        if '-' in part:
            try:
                start, end = map(int, part.split('-'))
                result.update(range(start, end + 1))
            except ValueError:
                continue
        else:
            try:
                result.add(int(part))
            except ValueError:
                continue
    return sorted(list(result))

def generate_canonical(template_data: Dict[str, Any]) -> Dict[str, Any]:
    general_params = template_data['general_parameters']
    apartment_types_data = template_data['apartment_types']
    assignments_data = template_data['assignments']

    apartment_types = {apt_type['type_name']: apt_type for apt_type in apartment_types_data}

    tus_req_data = {}
    lc_dr_data = {}
    lc_tu_data = {}
    max_floor = 0
    max_apt = 0

    for assignment in assignments_data:
        floors = parse_ranges(assignment.get('floors', ''))
        if not floors:
            continue
        
        max_floor = max(max_floor, max(floors))

        for rule in assignment.get('rules', []):
            apt_type_name = rule.get('type_name')
            if not apt_type_name or apt_type_name not in apartment_types:
                continue

            apt_config = apartment_types[apt_type_name]
            apartments = parse_ranges(rule.get('apartments', ''))
            
            if not apartments:
                continue
            
            max_apt = max(max_apt, max(apartments))

            tomas_count = int(apt_config.get('tomas_count', 0))
            len_deriv_repart = float(apt_config.get('len_deriv_repart', 0))
            len_tu_cables = apt_config.get('len_tu_cables', [])

            for p in floors:
                for a in apartments:
                    key = f"{p}|{a}"
                    tus_req_data[key] = tomas_count
                    lc_dr_data[key] = len_deriv_repart
                    
                    for i in range(tomas_count):
                        tu_len = float(len_tu_cables[i]) if i < len(len_tu_cables) else 5.0
                        lc_tu_data[f"{key}|{i+1}"] = tu_len

    canonical = {
        "Piso_Maximo": int(general_params.get('Piso_Maximo', max_floor)),
        "apartamentos_por_piso": int(general_params.get('Apartamentos_Piso', max_apt)),
        "largo_cable_amplificador_ultimo_piso": float(general_params.get('Largo_Cable_Amplificador_Ultimo_Piso', 7)),
        "potencia_entrada": float(general_params.get('Potencia_Entrada_dBuV', 110)),
        "largo_cable_feeder_bloque": float(general_params.get('Largo_Feeder_Bloque_m (Mínimo)', 3.5)),
        "atenuacion_cable_por_metro": float(general_params.get('Atenuacion_Cable_dBporM', 0.2)),
        "atenuacion_cable_470mhz": float(general_params.get('Atenuacion_Cable_470MHz_dBporM', 0.127)),
        "atenuacion_cable_698mhz": float(general_params.get('Atenuacion_Cable_698MHz_dBporM', 0.1558)),
        "atenuacion_conector": float(general_params.get('Atenuacion_Conector_dB', 0.2)),
        "largo_cable_entre_pisos": float(general_params.get('Largo_Entre_Pisos_m', 3.5)),
        "Nivel_minimo": float(general_params.get('Nivel_Minimo_dBuV', 47)),
        "Nivel_maximo": float(general_params.get('Nivel_Maximo_dBuV', 70)),
        "Potencia_Objetivo_TU": float(general_params.get('Potencia_Objetivo_TU_dBuV', 58)),
        "conectores_por_union": int(general_params.get('Conectores_por_Union', 2)),
        "atenuacion_conexion_tu": float(general_params.get('Atenuacion_Conexion_TU_dB', 1)),
        "p_troncal": int(general_params.get('p_troncal', round(max_floor / 2))),
        "derivadores_data": DEFAULT_DERIVADORES,
        "repartidores_data": DEFAULT_REPARTIDORES,
        "tus_requeridos_por_apartamento": tus_req_data,
        "largo_cable_derivador_repartidor": lc_dr_data,
        "largo_cable_tu": lc_tu_data
    }

    return canonical

if __name__ == "__main__":
    try:
        raw_input = sys.stdin.read()
        if not raw_input:
            print(json.dumps({"success": False, "message": "No input received"}))
            sys.exit(1)
            
        data = json.loads(raw_input)
        result = generate_canonical(data)
        print(json.dumps(result))
    except Exception as e:
        print(json.dumps({"success": False, "message": str(e)}))
        sys.exit(1)
