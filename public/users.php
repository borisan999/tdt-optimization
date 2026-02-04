<?php
require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
require_once __DIR__ . '/../app/controllers/UserController.php';


$controller = new UserController();

$action = $_GET['action'] ?? 'index';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($action) {
    case 'create':
        $controller->create();
        break;
    case 'edit':
        $controller->edit($id);
        break;
    case 'save':
        $controller->save();
        break;
    case 'disable':
        $controller->disable($id);
        break;
/*    case 'delete':
        $controller->delete($id);
        break;*/
    default:
        $controller->index();
}
require_once __DIR__ . '/templates/footer.php';