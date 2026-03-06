<?php
require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>

<style>
    .config-hero {
        background: linear-gradient(135deg, #475569 0%, #1e293b 100%);
        color: white;
        padding: 2.5rem 2rem;
        border-radius: 1rem;
        margin-bottom: 2.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .hero-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.9;
    }
    .dash-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid #e2e8f0;
        height: 100%;
        display: flex;
        flex-direction: column;
        background: white;
        padding: 2rem;
        border-radius: 1rem;
        text-decoration: none;
        color: inherit;
    }
    .dash-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #2563eb;
    }
    .card-icon {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
        font-size: 1.5rem;
    }
    .icon-blue { background-color: #eff6ff; color: #2563eb; }
    .icon-green { background-color: #f0fdf4; color: #16a34a; }
    .icon-red { background-color: #fef2f2; color: #dc2626; }
    .icon-purple { background-color: #faf5ff; color: #7c3aed; }
</style>

<main class="container py-5">

    <div class="config-hero text-center animate__animated animate__fadeIn">
        <div class="hero-icon">
            <i class="fas fa-cogs"></i>
        </div>
        <h1 class="display-6 fw-bold mb-2"><?= __('configurations') ?></h1>
        <p class="lead opacity-75 small">
            <?= __('dash_config_desc') ?>
        </p>
    </div>

    <section class="row g-4">

        <!-- Derivadores -->
        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
            <a href="derivadores" class="dash-card">
                <div class="card-icon icon-purple">
                    <i class="fas fa-layer-group"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('derivadores') ?></h3>
                <p class="text-muted small mb-4 flex-grow-1">
                    <?= __('derivadores_desc') ?>
                </p>
                <span class="btn btn-outline-primary btn-sm w-100 fw-bold">
                    <?= __('manage_derivadores') ?>
                </span>
            </a>
        </div>

        <!-- Repartidores -->
        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <a href="repartidores" class="dash-card">
                <div class="card-icon icon-red">
                    <i class="fas fa-share-alt"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('repartidores') ?></h3>
                <p class="text-muted small mb-4 flex-grow-1">
                    <?= __('repartidores_desc') ?>
                </p>
                <span class="btn btn-outline-primary btn-sm w-100 fw-bold">
                    <?= __('manage_repartidores') ?>
                </span>
            </a>
        </div>

        <!-- General Parameters -->
        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
            <a href="general-params" class="dash-card">
                <div class="card-icon icon-blue">
                    <i class="fas fa-sliders-h"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('tech_params') ?></h3>
                <p class="text-muted small mb-4 flex-grow-1">
                    <?= __('params_desc') ?>
                </p>
                <span class="btn btn-outline-primary btn-sm w-100 fw-bold">
                    <?= __('manage_params') ?>
                </span>
            </a>
        </div>

        <!-- Users -->
        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
            <a href="users" class="dash-card">
                <div class="card-icon icon-green">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('manage_users') ?></h3>
                <p class="text-muted small mb-4 flex-grow-1">
                    <?= __('users_desc') ?>
                </p>
                <span class="btn btn-outline-primary btn-sm w-100 fw-bold">
                    <?= __('manage_users') ?>
                </span>
            </a>
        </div>

    </section>

</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
