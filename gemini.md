# Análisis del Proyecto: TDT Optimization

Este documento proporciona un resumen técnico del sistema de optimización TDT, basado en el análisis de la documentación y la estructura del proyecto.

## 1. Resumen del Proyecto

El sistema "TDT Optimization" es una aplicación de ingeniería web diseñada para el diseño y la optimización matemática de redes de distribución de Televisión Digital Terrestre (TDT).

El objetivo principal es seleccionar automáticamente la combinación óptima de equipos (derivadores y repartidores) mediante Programación Lineal Entera Mixta (MILP) para garantizar que los niveles de señal en todas las tomas de usuario cumplan con la normativa RITEL (entre 47 dBuV y 70 dBuV).

El sistema se centra en la inmutabilidad y la trazabilidad, tratando cada resultado de optimización como una "instantánea canónica" para asegurar la coherencia entre las visualizaciones y los reportes exportados.

## 2. Arquitectura

La arquitectura se basa en un modelo estricto de **Autoridad de Datos Canónica** (`Canonical Data Authority`). Esto significa que un único objeto JSON, validado y estructurado, sirve como la fuente de verdad para todas las operaciones.

Los componentes principales son:

*   **Backend (PHP):** Gestiona la lógica de negocio, la autenticación de usuarios, el acceso a la base de datos y la orquestación del proceso de optimización. Sigue una estructura similar a MVC (`models`, `controllers`, `views`).
*   **Solver de Optimización (Python):** Un script de Python (`optimizer_canonical.py`) que recibe el modelo canónico del edificio, ejecuta el algoritmo de optimización MILP utilizando la librería `pulp`, y devuelve el diseño de red óptimo.
*   **Frontend:** Una interfaz web que permite a los usuarios modelar topologías de edificios, lanzar optimizaciones, visualizar los resultados en forma de árbol jerárquico (`vis-network`) y exportar informes.
*   **Base de Datos (MySQL):** Almacena la configuración de los edificios (`datasets`), los catálogos de equipos, los usuarios y, fundamentalmente, las "instantáneas" inmutables de las entradas y salidas de cada optimización (`optimizations` y `results`).

El flujo de datos es lineal y garantiza la integridad: Ingesta de datos -> Normalización a JSON Canónico -> Ejecución y guardado de instantánea -> Visualización y exportación desde la instantánea.

## 3. Stack Tecnológico

*   **Backend:** PHP 8.1+
*   **Base de Datos:** MySQL 8.0+ o MariaDB 10.6+
*   **Servidor Web:** Apache 2.4+
*   **Solver:** Python 3.10+ con las librerías `pulp`, `pandas` y `openpyxl`.
*   **Contenerización:** **Podman**. El entorno completo (base de datos y aplicación) está diseñado para ser desplegado y gestionado a través de contenedores.
*   **Dependencias PHP:** Gestionadas con `Composer` (ej. `phpoffice/phpspreadsheet`).

## 4. Flujo de Datos y Casos de Uso Principales

1.  **UC-01: Modelar Topología:** El ingeniero define la estructura del edificio (pisos, apartamentos, tomas) a través de un generador de plantillas o subiendo un archivo Excel.
2.  **UC-02: Lanzar Optimización:** El usuario solicita una optimización para un modelo de edificio. El sistema bloquea el `dataset`, invoca al solver de Python con el JSON canónico y espera el resultado.
3.  **UC-03 & 04: Visualizar y Validar:** El resultado, una vez guardado como instantánea inmutable, se usa para renderizar un árbol de distribución interactivo y para validar automáticamente qué tomas cumplen con la normativa.
4.  **UC-05: Generar Informes:** El ingeniero exporta el diseño final a formatos profesionales (XLSX, CSV, DOCX), garantizando una paridad del 100% con lo que se ve en la pantalla.

## 5. Base de Datos

Las tablas clave reflejan la arquitectura de instantáneas:

*   `datasets`: Almacena la configuración del edificio en un campo `canonical_json`.
*   `optimizations`: Registra cada intento de optimización sobre un `dataset`.
*   `results`: Guarda el resultado inmutable de una optimización exitosa, incluyendo el `detail_json` (la salida canónica del solver) y el `inputs_json` (la entrada que se usó).
*   `derivadores` / `repartidores`: Catálogos globales de equipamiento.

## 6. Despliegue y Ejecución

El proyecto está completamente contenerizado con **Podman**, lo que simplifica enormemente el despliegue. El repositorio incluye una serie de scripts de ayuda:

*   `podman-install.sh`: Realiza la instalación completa por primera vez (crea red, volúmenes, levanta la BBDD, construye la imagen de la app y la inicia).
*   `podman-start.sh`: Inicia los contenedores existentes.
*   `podman-stop.sh`: Detiene los contenedores.
*   `podman-cleanup.sh`: Elimina por completo el entorno (contenedores, volúmenes, red).

La aplicación se accede a través de `http://localhost:8080`.

## 7. Archivos y Carpetas Clave

*   `Containerfile`: Define la imagen del contenedor de la aplicación, instalando Apache, PHP, Python y todas las dependencias.
*   `tdt-optimization/`: Carpeta raíz del código fuente de la aplicación PHP.
*   `tdt-optimization/app/`: Contiene la lógica de negocio principal (controladores, modelos, servicios).
*   `tdt-optimization/app/python/10/optimizer_canonical.py`: El script del solver de optimización.
*   `tdt-optimization/public/`: El "Document Root" del servidor web, con los puntos de entrada de la aplicación (`index.php`) y los assets.
*   `tdt-optimization/storage/`: Directorios para logs, caché y archivos temporales. Debe tener permisos de escritura.
*   `*.sh`: Scripts para la gestión del ciclo de vida de los contenedores Podman.
*   `tdt_optimization.sql`: Script para la creación del esquema de la base de datos.
*   `docs/`: Documentación detallada del proyecto.

## 8. Montaje de Volúmenes para Desarrollo

Para facilitar el desarrollo y evitar tener que reconstruir la imagen cada vez que se modifica el código, el script `podman-install.sh` monta directorios del host directamente en el contenedor mediante volúmenes:

### Volúmenes configurados

| Directorio Host | Directorio Contenedor | Modo |
|-----------------|----------------------|------|
| `tdt-optimization/app` | `/var/www/html/app` | solo lectura (ro) |
| `tdt-optimization/public` | `/var/www/html/public` | solo lectura (ro) |
| `tdt-optimization/.env` | `/var/www/html/.env` | solo lectura (ro) |
| `tdt-optimization/scripts` | `/var/www/html/scripts` | solo lectura (ro) |
| `tdt-optimization/storage` | `/var/www/html/storage` | lectura-escritura (rw) |

### Beneficios

1. **Cambios instantáneos**: Los cambios en archivos del código fuente (PHP, CSS, JS) se reflejan inmediatamente en la aplicación sin necesidad de reconstruir la imagen ni reiniciar el contenedor.
2. **Persistencia de datos**: El directorio `storage` se monta con permisos de escritura para mantener los logs y archivos generados entre ejecuciones.
3. **Separación de responsabilidades**: La imagen del contenedor contiene las dependencias (PHP, Python, Apache, librerías Composer) mientras que el código fuente reside en el host.

### Cómo funciona

1. La imagen se construye una sola vez con todas las dependencias del sistema.
2. Al iniciar el contenedor, se montan los directorios del código fuente desde el host.
3. Los archivos que no cambian frecuentemente (vendor de Composer) permanecen dentro de la imagen.
4. Solo es necesario reconstruir la imagen si cambian las dependencias del sistema (PHP, Python, extensiones, librerías).
