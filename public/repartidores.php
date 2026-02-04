<?php
require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

require_once __DIR__ . '/../app/controllers/RepartidorController.php';

$controller = new RepartidorController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'edit':
        $controller->edit((int)($_GET['id'] ?? 0));
        break;

    case 'delete':
        $controller->delete((int)($_GET['id'] ?? 0));
        break;

    case 'create':
        $controller->create();
        break;

    case 'save':
        $controller->save();
        break;

    default:
        $controller->index();
}
require_once __DIR__ . '/templates/footer.php';