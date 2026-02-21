<?php

require_once __DIR__ . '/../config/AppConfig.php';
require_once __DIR__ . '/../models/DerivadorModel.php';
require_once __DIR__ . '/../models/RepartidorModel.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Config\AppConfig;

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
        $sheet = $spreadsheet->getSheetByName(AppConfig::SHEET_PARAMS);
        if (!$sheet) throw new Exception("Sheet '" . AppConfig::SHEET_PARAMS . "' not found.");
        
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
        $sheet = $spreadsheet->getSheetByName(AppConfig::SHEET_LC_DR);
        if (!$sheet) throw new Exception("Sheet '" . AppConfig::SHEET_LC_DR . "' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $lcd = [];
        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            
            $hasData = !empty($row[2]);
            $piso = trim((string)($row[0] ?? ''));
            $apto = trim((string)($row[1] ?? ''));

            if ($piso === '' && $apto === '' && !$hasData) continue; 

            if ($piso === '' || $apto === '') {
                throw new Exception("Explicit Topology Violation: Row " . ($i + 1) . " in '" . AppConfig::SHEET_LC_DR . "' is missing structural keys (Piso/Apto). Merged cells are forbidden.");
            }

            $key = "$piso|$apto";
            $lcd[$key] = self::castNumeric($row[2]);
        }
        $canonical['largo_cable_derivador_repartidor'] = $lcd;

        /* =====================================================
        3️⃣ TUS REQUERIDOS
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName(AppConfig::SHEET_TUS_REQ);
        if (!$sheet) throw new Exception("Sheet '" . AppConfig::SHEET_TUS_REQ . "' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $tus = [];
        foreach ($rows as $i => $row) {
            if ($i === 0) continue;

            $hasData = !empty($row[2]);
            $piso = trim((string)($row[0] ?? ''));
            $apto = trim((string)($row[1] ?? ''));

            if ($piso === '' && $apto === '' && !$hasData) continue;

            if ($piso === '' || $apto === '') {
                throw new Exception("Explicit Topology Violation: Row " . ($i + 1) . " in '" . AppConfig::SHEET_TUS_REQ . "' is missing structural keys (Piso/Apto). Merged cells are forbidden.");
            }

            $key = "$piso|$apto";
            $tus[$key] = (int)$row[2];
        }
        $canonical['tus_requeridos_por_apartamento'] = $tus;

        /* =====================================================
        4️⃣ LARGO_CABLE_TU
        ====================================================== */
        $sheet = $spreadsheet->getSheetByName(AppConfig::SHEET_LC_TU);
        if (!$sheet) throw new Exception("Sheet '" . AppConfig::SHEET_LC_TU . "' not found.");
        
        $rows = $sheet->toArray(null, true, true, false);
        $lctu = [];
        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            
            $hasData = !empty($row[3]);
            $piso = trim((string)($row[0] ?? ''));
            $apto = trim((string)($row[1] ?? ''));
            $tuIdx = trim((string)($row[2] ?? ''));

            if ($piso === '' && $apto === '' && $tuIdx === '' && !$hasData) continue;

            if ($piso === '' || $apto === '' || $tuIdx === '') {
                throw new Exception("Explicit Topology Violation: Row " . ($i + 1) . " in '" . AppConfig::SHEET_LC_TU . "' is missing structural keys (Piso/Apto/TU Index). Merged cells are forbidden.");
            }

            $key = "$piso|$apto|$tuIdx";
            $lctu[$key] = self::castNumeric($row[3]);
        }
        $canonical['largo_cable_tu'] = $lctu;

        /* =====================================================
        5️⃣ DERIVADORES_DATA (Source: Database)
        ====================================================== */
        $derivModel = new DerivadorModel();
        $allDeriv = $derivModel->getAll();
        $deriv = [];
        foreach ($allDeriv as $d) {
            $modelo = trim($d['modelo']);
            if ($modelo === '') continue;
            $deriv[$modelo] = [
                'derivacion' => (float)$d['derivacion'],
                'paso' => (float)$d['paso'],
                'salidas' => (int)$d['salidas'],
            ];
        }
        $canonical['derivadores_data'] = $deriv;

        /* =====================================================
        6️⃣ REPARTIDORES_DATA (Source: Database)
        ====================================================== */
        $repModel = new RepartidorModel();
        $allRep = $repModel->getAll();
        $rep = [];
        foreach ($allRep as $r) {
            $modelo = trim($r['modelo']);
            if ($modelo === '') continue;
            $rep[$modelo] = [
                'perdida_insercion' => (float)$r['perdida_insercion'],
                'salidas' => (int)$r['salidas'],
            ];
        }
        $canonical['repartidores_data'] = $rep;

        // Final Safeguard: Ensure we actually have TU data (Task: Irreversible Doctrine)
        if (empty($canonical['largo_cable_tu'])) {
            throw new Exception("Explicit Topology Violation: No TU rows detected. Data rows must be explicit and non-merged.");
        }

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
