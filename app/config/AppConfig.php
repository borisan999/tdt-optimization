<?php

namespace App\Config;

class AppConfig
{
    // Sheet Names (The Source of Truth for Excel)
    public const SHEET_PARAMS      = 'Parametros_Generales';
    public const SHEET_LC_DR       = 'largo_cable_derivador_repartido';
    public const SHEET_TUS_REQ     = 'tus_requeridos_por_apartamento';
    public const SHEET_LC_TU       = 'largo_cable_tu';
    public const SHEET_DERIVADORES = 'derivadores_data';
    public const SHEET_REPARTIDORES = 'repartidores_data';

    // Canonical JSON Metadata
    public const CONTRACT_VERSION = 2;
}
