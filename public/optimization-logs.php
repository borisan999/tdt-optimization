<?php
require_once __DIR__ . '/../app/auth/require_login.php';
if (!isAdmin()) {
    header('Location: /tdt-optimization/dashboard?error=admin_only');
    exit;
}
$logDir = '/var/www/html/storage/optimization_logs';
$files = [];

if (is_dir($logDir)) {
    $items = scandir($logDir);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && pathinfo($item, PATHINFO_EXTENSION) === 'log') {
            $filepath = $logDir . '/' . $item;
            $files[] = [
                'name' => $item,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath)
            ];
        }
    }
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Optimization Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">TDT Optimization - Solver Logs</span>
            <a href="dashboard" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fas fa-file-alt"></i> Solver Logs</h2>
            <?php if (!empty($files)): ?>
                <form method="POST" action="/api/logs/delete-all" onsubmit="return confirm('Are you sure you want to delete ALL log files? This cannot be undone.');">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete All Logs
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if (empty($files)): ?>
            <div class="alert alert-info">No optimization logs found.</div>
        <?php else: ?>
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td><i class="fas fa-file-code text-info"></i> <?= htmlspecialchars($file['name']) ?></td>
                            <td><?= number_format($file['size']) ?> bytes</td>
                            <td><?= date('Y-m-d H:i:s', $file['modified']) ?></td>
                            <td class="text-end">
                                <a href="/optimization-logs-view.php?file=<?= urlencode($file['name']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <form method="POST" action="/api/logs/delete" class="d-inline ms-1">
                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($file['name']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this log file?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
