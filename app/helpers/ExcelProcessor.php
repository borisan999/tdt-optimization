<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelProcessor
{
    /**
     * Reads an Excel file and converts it into a canonical JSON array.
     *
     * @param string $filePath The path to the uploaded Excel file.
     * @return array The canonical JSON structure as a PHP array.
     * @throws Exception if the Excel processing fails or results in invalid data.
     */
    public static function readToCanonicalJson(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $canonical = [];

        /* =====================================================
        1️⃣ PARAMETROS_GENERALES
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName('Parametros_Generales');
        if (!$sheet) throw new Exception("Sheet 'Parametros_Generales' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $paramMap = [];
        foreach ($rows as $i => $row) {
            if ($i === 0 || empty($row[0])) continue;
            $paramMap[trim($row[0])] = self::castNumeric($row[1]);
        }

        // Map keys to canonical structure
        $canonical['Piso_Maximo'] = (int)self::getv($paramMap, 'Piso_Maximo', 0);
        $canonical['apartamentos_por_piso'] = (int)self::getv($paramMap, 'Apartamentos_Piso', 0);
        $canonical['atenuacion_cable_por_metro'] = (float)self::getv($paramMap, 'Atenuacion_Cable_dBporM', 0.2);
        $canonical['atenuacion_cable_470mhz'] = (float)self::getv($paramMap, 'Atenuacion_Cable_470MHz_dBporM', 0.127);
        $canonical['atenuacion_cable_698mhz'] = (float)self::getv($paramMap, 'Atenuacion_Cable_698MHz_dBporM', 0.1558);
        $canonical['atenuacion_conector'] = (float)self::getv($paramMap, 'Atenuacion_Conector_dB', 0.2);
        $canonical['largo_cable_entre_pisos'] = (float)self::getv($paramMap, 'Largo_Entre_Pisos_m', 3.0);
        $canonical['potencia_entrada'] = (float)self::getv($paramMap, 'Potencia_Entrada_dBuV', 110.0);
        $canonical['Nivel_minimo'] = (float)self::getv($paramMap, 'Nivel_Minimo_dBuV', 47.0);
        $canonical['Nivel_maximo'] = (float)self::getv($paramMap, 'Nivel_Maximo_dBuV', 70.0);
        $canonical['Potencia_Objetivo_TU'] = (float)self::getv($paramMap, 'Potencia_Objetivo_TU_dBuV', 60.0);
        $canonical['conectores_por_union'] = (int)self::getv($paramMap, 'Conectores_por_Union', 2);
        $canonical['atenuacion_conexion_tu'] = (float)self::getv($paramMap, 'Atenuacion_Conexion_TU_dB', 1.0);
        $canonical['largo_cable_amplificador_ultimo_piso'] = (float)self::getv($paramMap, 'Largo_Cable_Amplificador_Ultimo_Piso', 5.0);
        $canonical['largo_cable_feeder_bloque'] = (float)self::getv($paramMap, 'Largo_Feeder_Bloque_m (Mínimo)', 3.0);
        $canonical['p_troncal'] = (int)round($canonical['Piso_Maximo'] / 2, 0, PHP_ROUND_HALF_EVEN);

        /* =====================================================
        2️⃣ LARGO_CABLE_DERIVADOR_REPARTIDOR
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName('largo_cable_derivador_repartido');
        if (!$sheet) throw new Exception("Sheet 'largo_cable_derivador_repartido' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $lcd = [];
        foreach ($rows as $i => $row) {
            if ($i === 0 || empty($row[0]) || empty($row[1])) continue;
            $key = trim($row[0]) . "|" . trim($row[1]);
            $lcd[$key] = self::castNumeric($row[2]);
        }
        $canonical['largo_cable_derivador_repartidor'] = $lcd;

        /* =====================================================
        3️⃣ TUS REQUERIDOS
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName('tus_requeridos_por_apartamento');
        if (!$sheet) throw new Exception("Sheet 'tus_requeridos_por_apartamento' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $tus = [];
        foreach ($rows as $i => $row) {
            if ($i === 0 || empty($row[0])) continue;
            $key = trim($row[0]) . "|" . trim($row[1]);
            $tus[$key] = (int)$row[2];
        }
        $canonical['tus_requeridos_por_apartamento'] = $tus;

        /* =====================================================
        4️⃣ LARGO_CABLE_TU
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName('largo_cable_tu');
        if (!$sheet) throw new Exception("Sheet 'largo_cable_tu' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $lctu = [];
        foreach ($rows as $i => $row) {
            if ($i === 0 || empty($row[0])) continue;
            $key = trim($row[0]) . "|" . trim($row[1]) . "|" . trim($row[2]);
            $lctu[$key] = self::castNumeric($row[3]);
        }
        $canonical['largo_cable_tu'] = $lctu;

        /* =====================================================
        5️⃣ DERIVADORES_DATA
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName('derivadores_data');
        if (!$sheet) throw new Exception("Sheet 'derivadores_data' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $deriv = [];
        foreach ($rows as $i => $row) {
            if ($i === 0 || empty($row[0])) continue;
            $code = trim($row[0]);
            $deriv[$code] = [
                'derivacion' => (float)$row[1],
                'paso' => (float)$row[2],
                'salidas' => (int)$row[3],
            ];
        }
        $canonical['derivadores_data'] = $deriv;

        /* =====================================================
        6️⃣ REPARTIDORES_DATA
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName('repartidores_data');
        if (!$sheet) throw new Exception("Sheet 'repartidores_data' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $rep = [];
        foreach ($rows as $i => $row) {
            if ($i === 0 || empty($row[0])) continue;
            $code = trim($row[0]);
            $rep[$code] = [
                'perdida_insercion' => (float)$row[1],
                'salidas' => (int)$row[2],
            ];
        }
        $canonical['repartidores_data'] = $rep;

        return $canonical;
    }

    private static function castNumeric($value)
    {
        if (is_numeric($value)) {
            return (strpos((string)$value, '.') !== false) ? (float)$value : (int)$value;
        }
        return $value;
    }

    private static function getv($map, $key, $default = null)
    {
        return array_key_exists($key, $map) ? $map[$key] : $default;
    }
}
