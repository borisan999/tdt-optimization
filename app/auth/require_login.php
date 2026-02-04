<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /tdt-optimization/public/login.php');
    exit;
}
