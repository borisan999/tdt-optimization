<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Harden session against fixation
if (!isset($_SESSION['_initiated'])) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
}
