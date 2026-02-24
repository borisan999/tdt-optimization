<?php require_once __DIR__ . '/../app/auth/require_login.php'; ?>
<?php include __DIR__ . "/templates/header.php"; ?>
<?php include __DIR__ . "/templates/navbar.php"; ?>

<main class="container py-4">

    <div class="mb-4">
        <h1 class="page-title">TDT Optimization</h1>
        <p class="text-muted mb-0">
            <?= __('dash_subtitle') ?>
        </p>
    </div>

    <section class="dashboard-grid">

        <a href="enter-data" class="dash-card">
            <h2 class="dash-title"><?= __('enter_data') ?></h2>
            <p class="dash-desc">
                <?= __('dash_enter_data_desc') ?>
            </p>
        </a>

        <a href="results-history" class="dash-card">
            <h2 class="dash-title"><?= __('history') ?></h2>
            <p class="dash-desc">
                <?= __('dash_history_desc') ?>
            </p>
        </a>

        <a href="configurations" class="dash-card">
            <h2 class="dash-title"><?= __('configurations') ?></h2>
            <p class="dash-desc">
                <?= __('dash_config_desc') ?>
            </p>
        </a>

    </section>

</main>

<?php include __DIR__ . "/templates/footer.php"; ?>
