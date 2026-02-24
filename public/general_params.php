<?php
// public/general_params.php
require_once __DIR__ . '/../app/auth/require_login.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/models/GeneralParams.php';

$genModel = new GeneralParams();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_defaults'])) {
    $params = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'param_') === 0) {
            $name = substr($key, 6);
            $params[$name] = $value;
        }
    }

    if ($genModel->saveDefaults($params)) {
        $message = __('defaults_updated_success');
        $messageType = 'success';
    } else {
        $message = __('defaults_updated_fail');
        $messageType = 'danger';
    }
}

$defaults = $genModel->getDefaults();

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

$categories = [
    [
        'title' => __('cat_geometry'),
        'icon' => 'fa-building',
        'fields' => ['Piso_Maximo', 'apartamentos_por_piso', 'largo_cable_entre_pisos', 'largo_cable_amplificador_ultimo_piso', 'largo_cable_feeder_bloque']
    ],
    [
        'title' => __('cat_constraints'),
        'icon' => 'fa-signal',
        'fields' => ['potencia_entrada', 'Nivel_minimo', 'Nivel_maximo', 'Potencia_Objetivo_TU', 'p_troncal']
    ],
    [
        'title' => __('cat_attenuation'),
        'icon' => 'fa-chart-line',
        'fields' => ['atenuacion_cable_por_metro', 'atenuacion_cable_470mhz', 'atenuacion_cable_698mhz', 'atenuacion_conector', 'atenuacion_conexion_tu', 'conectores_por_union']
    ]
];
?>

<main class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 text-primary"><i class="fas fa-tools me-2"></i><?= __('global_defaults_title') ?></h2>
        <a href="configurations" class="btn btn-outline-secondary">
            <i class="fas fa-chevron-left"></i> <?= __('back') ?>
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info shadow-sm">
        <i class="fas fa-info-circle me-2"></i> <?= __('defaults_info') ?>
    </div>

    <form method="POST" action="">
        <div class="row">
            <?php foreach ($categories as $cat): ?>
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary fw-bold">
                                <i class="fas <?= $cat['icon'] ?> me-2"></i><?= $cat['title'] ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($cat['fields'] as $name): ?>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold mb-1"><?= __('param_' . $name) ?></label>
                                    <input type="number" step="any" class="form-control form-control-sm" 
                                           name="param_<?= $name ?>" value="<?= htmlspecialchars($defaults[$name] ?? '') ?>" required>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm border-0 mt-2">
            <div class="card-body d-flex justify-content-end gap-3">
                <button type="submit" name="save_defaults" class="btn btn-primary px-5">
                    <i class="fas fa-save me-2"></i> <?= __('save_defaults_btn') ?>
                </button>
            </div>
        </div>
    </form>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
