<?php


declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/auth/session.php';
require_once __DIR__ . '/../app/models/User.php';
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

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

    $error = 'Invalid credentials';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="/tdt-optimization/public/assets/css/style.css">

</head>
<body>

<h2>Login TDT - Optimization</h2>

<?php if ($error): ?>
    <p style="color:red"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post">
    <label>
        Username<br>
        <input type="text" name="username" required>
    </label><br><br>

    <label>
        Password<br>
        <input type="password" name="password" required>
    </label><br><br>

    <button type="submit">Login</button>
</form>

</body>
</html>
