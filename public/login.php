<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/auth/session.php';
require_once __DIR__ . '/../app/models/User.php';

// Redirect to dashboard if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /tdt-optimization/public/dashboard');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $user = User::findByUsername($pdo, $username);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];

            header('Location: /tdt-optimization/public/dashboard');
            exit;
        }
    }

    $error = __('invalid_credentials');
}

include __DIR__ . '/templates/header.php';
?>

<style>
    body {
        background-color: #f8fafc;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        margin: 0;
    }
    .login-container {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .login-card {
        max-width: 420px;
        width: 100%;
        padding: 2.5rem;
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 15px 35px rgba(0,0,0,0.05), 0 5px 15px rgba(0,0,0,0.03);
        background: #ffffff;
    }
    .login-logo {
        font-size: 3.5rem;
        color: #2563eb;
        margin-bottom: 1rem;
        text-shadow: 0 4px 10px rgba(37, 99, 235, 0.1);
    }
    .btn-login {
        padding: 0.8rem;
        font-weight: 600;
        border-radius: 0.75rem;
        background-color: #2563eb;
        border: none;
        transition: all 0.2s;
        font-size: 1rem;
    }
    .btn-login:hover {
        background-color: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }
    .lang-switcher-login {
        position: absolute;
        top: 30px;
        right: 30px;
        z-index: 1000;
    }
    .app-footer {
        background: transparent !important;
        border: none !important;
        margin-top: 0 !important;
        padding-bottom: 2rem !important;
    }
    .form-label {
        margin-bottom: 0.5rem;
        color: #4b5563;
    }
    .input-group-text {
        border-right: none;
        color: #9ca3af;
    }
    .form-control {
        border-left: none;
        padding: 0.75rem 1rem;
    }
    .form-control:focus {
        border-color: #cbd5e1;
        box-shadow: none;
    }
    .input-group:focus-within .input-group-text,
    .input-group:focus-within .form-control {
        border-color: #2563eb;
        background-color: #fff !important;
    }
</style>

<div class="lang-switcher-login">
    <div class="dropdown">
        <button class="btn btn-sm btn-white shadow-sm dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-globe me-1 text-primary"></i> <?= strtoupper(\app\helpers\Translation::getLang()) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
            <li><a class="dropdown-item py-2 <?= \app\helpers\Translation::getLang() === 'en' ? 'active' : '' ?>" href="api/lang/en">English</a></li>
            <li><a class="dropdown-item py-2 <?= \app\helpers\Translation::getLang() === 'es' ? 'active' : '' ?>" href="api/lang/es">Español</a></li>
        </ul>
    </div>
</div>

<div class="login-container">
    <div class="card login-card">
        <div class="text-center mb-5">
            <div class="login-logo">
                <i class="fas fa-broadcast-tower"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2"><?= __('login_title') ?></h3>
            <p class="text-muted small"><?= __('login_subtitle') ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-3 fs-5"></i>
                <div class="small fw-medium"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-4">
                <label for="username" class="form-label small fw-bold text-uppercase tracking-wider"><?= __('username') ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control bg-light" id="username" name="username" placeholder="<?= __('username_placeholder') ?>" required autofocus>
                </div>
            </div>

            <div class="mb-5">
                <label for="password" class="form-label small fw-bold text-uppercase tracking-wider"><?= __('password') ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control bg-light" id="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login shadow-sm text-uppercase">
                <?= __('sign_in') ?>
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
