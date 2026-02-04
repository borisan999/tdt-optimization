<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth/session.php';

$_SESSION = [];
session_destroy();

header('Location: /tdt-optimization/public/login.php');
exit;
