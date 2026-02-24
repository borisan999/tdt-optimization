<?php
require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>

<main class="container">

    <h1 class="page-title"><?= __('configurations') ?></h1>

    <div class="dashboard-grid">

        <!-- Derivadores -->
        <div class="dash-card">
            <h2 class="section-title"><?= __('derivadores') ?></h2>
            <p>
                <?= __('derivadores_desc') ?>
            </p>
            <a
                class="btn-primary"
                href="derivadores"
            >
                <?= __('manage_derivadores') ?>
            </a>
        </div>

        <!-- Repartidores -->
        <div class="dash-card">
            <h2 class="section-title"><?= __('repartidores') ?></h2>
            <p>
                <?= __('repartidores_desc') ?>
            </p>
            <a
                class="btn-primary"
                href="repartidores"
            >
                <?= __('manage_repartidores') ?>
            </a>
        </div>

        <!-- General Parameters -->
        <div class="dash-card">
            <h2 class="section-title"><?= __('tech_params') ?></h2>
            <p>
                <?= __('params_desc') ?>
            </p>
            <a
                class="btn-primary"
                href="general-params"
            >
                <?= __('manage_params') ?>
            </a>
        </div>

        <!-- Users -->
        <div class="dash-card">
            <h2 class="section-title"><?= __('manage_users') ?></h2>
            <p>
                <?= __('users_desc') ?>
            </p>
            <a
                class="btn-primary"
                href="users"
            >
                <?= __('manage_users') ?>
            </a>
        </div>

    </div>

</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
