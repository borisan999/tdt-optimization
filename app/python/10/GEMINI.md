# Project Overview

This project is a Python application for optimizing the design of a Terrestrial Digital Television (TDT) distribution network in a multi-story building. It uses Mixed-Integer Linear Programming (MILP) to determine the optimal selection of components (splitters, taps) and cable lengths to ensure adequate signal levels at all user outlets.

## Main Technologies

*   **Python:** The core programming language.
*   **PuLP:** A Python library for linear and integer programming.
*   **pandas:** Used for data manipulation and reading/writing Excel files.
*   **Matplotlib:** Used for generating plots of the results.

## Project Structure

The project is organized into several Python modules:

*   `Optimizacion_RITEL_10.py`: The main script that orchestrates the entire optimization process.
*   `Funciones_apoyo_datos_entrada.py`: Functions for loading input data and parameters from the `datos_entrada.xlsx` file.
*   `Funciones_apoyo_optimizacion.py`: Functions for building the MILP model, including variables, constraints, and the objective function.
*   `Funciones_apoyo_salida.py`: Functions for exporting the results to an Excel file, generating plots, and creating a textual representation of the network.
*   `Funciones_apoyo_visualizacion.py`: Functions for generating an interactive HTML visualization of the network topology.

## Building and Running

To run the project, execute the main script `Optimizacion_RITEL_10.py`. Make sure you have the required Python libraries installed. You can install them using pip:

```bash
pip install pulp pandas matplotlib openpyxl
```

Then, run the script from your terminal:

```bash
python Optimizacion_RITEL_10.py
```

The script will read the input data from `datos_entrada.xlsx` and generate the following output files:

*   `Resultados_Optimizacion_TDT_Troncal.xlsx`: An Excel file with the detailed optimization results.
*   `histograma_niveles.png` and `niveles_por_tu.png`: PNG plots of the signal levels.
*   `esquema_conexiones.txt`: A text file with a textual representation of the network topology.
*   `Arbol_TDT_Specs.html`: An interactive HTML file for visualizing the network topology.

## Project Flowchart (Directory Tree Style)

```
Optimizacion_RITEL_10/
├───datos_entrada.xlsx
│
├───Optimizacion_RITEL_10.py
│   │
│   ├───Funciones_apoyo_datos_entrada.py
│   │   │   (dividir_en_bloques)
│   │   │   (cargar_datos_y_parametros)
│   │   │   (generar_indices_y_validar_datos)
│   │   │
│   │   └─── (Loads data from datos_entrada.xlsx)
│   │
│   ├───Funciones_apoyo_optimizacion.py
│   │   │   (_crear_variables)
│   │   │   (_restriccion_troncal)
│   │   │   (_restriccion_derivador)
│   │   │   (_restriccion_repartidor_apto)
│   │   │   (_perdidas_comunes)
│   │   │   (_restriccion_bloques)
│   │   │   (_restriccion_niveles_tu)
│   │   │   (_seleccionar_troncal)
│   │   │   (_generar_filas_detalle)
│   │   │
│   │   └─── (Builds and solves the MILP model)
│   │
│   ├───Funciones_apoyo_salida.py
│   │   │   (Uses from Funciones_apoyo_optimizacion: entrada_y_direccion_bloque, dividir_en_bloques)
│   │   │
│   │   │   (_exportar_a_excel)
│   │   │   (_generar_graficos)
│   │   │   (_generar_filas_resumen)
│   │   │   (_generar_df_inventario)
│   │   │   (_dibujar_esquema_conexiones_ascii)
│   │   │
│   │   └─── (Exports results to Excel, plots, and text)
│   │
│   └───Funciones_apoyo_visualizacion.py
│       │   (_generar_arbol_completo_con_specs)
│       │
│       └─── (Generates Arbol_TDT_Specs.html)
│
└───[Outputs]
    ├───Resultados_Optimizacion_TDT_Troncal.xlsx
    ├───histograma_niveles.png
    ├───niveles_por_tu.png
    ├───esquema_conexiones.txt
    └───Arbol_TDT_Specs.html
```

## Attenuation cable formula
- The attenuation formula for a typical RG6 coaxial cable can be approximated as A(f) ≈ 0.00673 * √f, where A is attenuation in dB/m and f is frequency in MHz.
- Attenuation at 470 MHz is approximately 0.146 dB/m.
- Attenuation at 698 MHz is approximately 0.178 dB/m.
This data is derived from standard industry specifications.

La fórmula de Álvaro es:

- Attenuation at 470 MHz is approximately 0.127 dB/m.
- Attenuation at 698 MHz is approximately 0.1558 dB/m.

References:
1. https://en.wikipedia.org/wiki/RG-6

## Calculation for P15A4TU4

**1. Headend Power:** 110 dBµV

**2. Losses up to Floor 15:**
*   **Headend to Trunk (Floor 8):** 6.6 dB (31m cable + connectors)
*   **Trunk Splitter (TLV519503):** 8 dB (insertion loss)
*   **Trunk to Block 1 (Floor 11):** 2.8 dB (12m feeder + connectors)
*   **Riser (Block 1, F11 to F15):** 13.2 dB (cable + tap pass-through losses)
*   **Tap (Floor 15, TLV519342):** 13.0 dB (tap-off loss)

**3. Losses in Apartment 4, Floor 15:**
*   **Tap to Splitter:** 1.6 dB (6.0m cable + 2 connectors)
*   **Splitter (TLV519504):** 9 dB (insertion loss)
*   **Splitter to TU4:** 3.6 dB (16.0m cable + 2 connectors)
*   **TU Connection:** 1 dB

**4. Final Calculation:**
*   **Total Loss:** `6.6 + 8 + 2.8 + 13.2 + 13.0 + 1.6 + 9 + 3.6 + 1 = 58.8 dB`
*   **Signal Level at TU4:** `110 dBµV - 58.8 dB = 51.2 dBµV`
