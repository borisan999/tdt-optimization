<?php
// public/index.php - Central Dispatcher

// 0. Output Buffer Safety (Task C)
ob_start();

ini_set('display_errors', 0); // Hide raw errors from users
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . "/../app/auth/session.php";
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/controllers/DatasetController.php";
require_once __DIR__ . "/../app/controllers/ApiController.php";

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = strtok($requestUri, '?');

// 1. Detect and strip base path (subdirectory)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
    $path = substr($path, strlen($basePath));
}

// 2. Normalize: handle case where index.php is explicitly in the URL
if (str_starts_with($path, '/index.php')) {
    $path = substr($path, 10);
}

// 3. Normalize slashes: ensure it starts with / and has no trailing / (unless it is root)
$path = '/' . ltrim($path, '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

// 4. Global Exception Shield (Task B)
$isApiRoute = str_starts_with($path, '/api/') || str_starts_with($path, '/dataset/') || str_starts_with($path, '/result/');

function send_json_error($message, $code = 500) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
    }
    // Clear buffer to remove any stray output
    if (ob_get_length()) ob_clean();
    
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'SYSTEM_ERROR',
            'message' => $message
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(function ($exception) use ($isApiRoute) {
    error_log("Unhandled Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    if ($isApiRoute) {
        send_json_error($exception->getMessage());
    } else {
        http_response_code(500);
        echo "<h1>Internal Server Error</h1>";
        if (ini_get('display_errors')) {
            echo "<p>" . htmlspecialchars($exception->getMessage()) . "</p>";
        }
        exit;
    }
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($isApiRoute) {
    if (!(error_reporting() & $errno)) return false;
    
    $message = "Error [$errno]: $errstr in $errfile on line $errline";
    error_log($message);
    
    if ($isApiRoute) {
        send_json_error($errstr);
    }
    return false; // Continue to internal PHP error handler if not caught
});

// Final check for fatal errors
register_shutdown_function(function () use ($isApiRoute) {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        if ($isApiRoute) {
            send_json_error("Fatal Error: " . $error['message']);
        }
    }
    // Flush buffer at the very end
    if (ob_get_length()) ob_end_flush();
});

// API routes - Deterministic prefix matching
if ($path === '/api/test') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Routing is working'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (str_starts_with($path, '/api/')) {
    $controller = new ApiController();
    $controller->handleRequest($path);
} 
// Dataset and Result routes - trailing slashes avoid matching results.php
elseif (str_starts_with($path, '/dataset/') || str_starts_with($path, '/result/')) {
    $controller = new DatasetController();
    $controller->handleRequest($path);
}
// Page routes
elseif ($path === '/' || $path === '' || $path === '/dashboard') {
    require_once __DIR__ . '/dashboard.php';
}
elseif ($path === '/results' || $path === '/results.php') {
    require_once __DIR__ . '/results.php';
}
elseif ($path === '/login' || $path === '/login.php') {
    require_once __DIR__ . '/login.php';
}
elseif ($path === '/logout' || $path === '/logout.php') {
    require_once __DIR__ . '/logout.php';
}
elseif (preg_match('/^\/enter-data\/(\d+)$/', $path, $matches)) {
    $_GET['dataset_id'] = $matches[1];
    require_once __DIR__ . '/enter_data.php';
}
elseif ($path === '/enter-data' || $path === '/enter_data.php') {
    require_once __DIR__ . '/enter_data.php';
}
elseif ($path === '/history' || $path === '/history.php') {
    require_once __DIR__ . '/history.php';
}
elseif ($path === '/template-generator' || $path === '/template_generator.php') {
    require_once __DIR__ . '/template_generator.php';
}
elseif ($path === '/configurations' || $path === '/configurations.php') {
    require_once __DIR__ . '/configurations.php';
}
elseif ($path === '/general-params' || $path === '/general_params.php') {
    require_once __DIR__ . '/general_params.php';
}
elseif ($path === '/derivadores' || $path === '/derivadores.php') {
    require_once __DIR__ . '/derivadores.php';
}
elseif ($path === '/repartidores' || $path === '/repartidores.php') {
    require_once __DIR__ . '/repartidores.php';
}
elseif ($path === '/users' || $path === '/users.php') {
    require_once __DIR__ . '/users.php';
}
elseif (preg_match('/^\/view-dataset\/(\d+)$/', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require_once __DIR__ . '/view_dataset.php';
}
elseif (preg_match('/^\/view-result\/(\d+)$/', $path, $matches)) {
    $_GET['opt_id'] = $matches[1];
    require_once __DIR__ . '/view_result.php';
}
elseif (preg_match('/^\/results-tree\/(\d+)$/', $path, $matches)) {
    $_GET['opt_id'] = $matches[1];
    require_once __DIR__ . '/results_tree.php';
}
elseif ($path === '/results-tree' || $path === '/results_tree.php') {
    require_once __DIR__ . '/results_tree.php';
}
elseif ($path === '/results-history' || $path === '/results_history.php') {
    require_once __DIR__ . '/results_history.php';
}
elseif ($path === '/export_input_excel.php') {
    require_once __DIR__ . '/export_input_excel.php';
}
elseif ($path === '/export_excel.php') {
    require_once __DIR__ . '/export_excel.php';
}
elseif ($path === '/export_csv.php') {
    require_once __DIR__ . '/export_csv.php';
}
elseif ($path === '/export_docx.php') {
    require_once __DIR__ . '/export_docx.php';
}
else {
    http_response_code(404);
    echo "404 Not Found: " . htmlspecialchars($path);
}

exit;
?>