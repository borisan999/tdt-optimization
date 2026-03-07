#!/bin/bash

# Este script crea un paquete de despliegue comprimido (tdt_optimization_deployment.zip)
# para el proyecto TDT Optimization.
# Excluye archivos y directorios no necesarios para la ejecución.

set -e # Salir inmediatamente si un comando falla.

echo "Creando el paquete de despliegue en formato ZIP..."

# Definir el nombre del archivo de salida
OUTPUT_FILE="tdt_optimization.zip"

# Eliminar el archivo si ya existe para evitar que se añada a sí mismo
rm -f "$OUTPUT_FILE"

# Comando de compresión ZIP. El flag -x se usa para cada exclusión.
zip -r "$OUTPUT_FILE" . \
-x './.git/*' \
-x 'tdt-optimization/vendor/*' \
-x 'tdt-optimization/storage/logs/*' \
-x 'tdt-optimization/storage/temp/*' \
-x 'tdt-optimization/storage/debug/*' \
-x 'tdt-optimization/storage/cache/*' \
-x 'tdt-optimization/storage/optimization_logs/*' \
-x 'optimization_result.json' \
-x 'test_output.json' \
-x 'tdt-optimization/output.json' \
-x 'session-*.md' \
-x '*.tar.gz' \
-x '*.zip' \
-x '*.sql' \
-x 'tdt-optimization/.~lock.*' \
-x 'tdt-optimization/.vscode/*' \
-x 'tdt-optimization/docs/*.odt' \
-x 'tdt-optimization/docs/*.pdf' \
-x 'tdt-optimization/docs/*.png'

echo ""
echo "Paquete de despliegue creado exitosamente: $OUTPUT_FILE"
