<?php require_once __DIR__ . '/../app/auth/require_login.php'; ?>
<?php include __DIR__ . "/templates/header.php"; ?>
<?php include __DIR__ . "/templates/navbar.php"; ?>

<style>
    .dashboard-hero {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 1rem;
        margin-bottom: 2.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .hero-icon {
        font-size: 3.5rem;
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
        text-decoration: none;
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
    .icon-data { background-color: #eff6ff; color: #2563eb; }
    .icon-gen { background-color: #f0fdf4; color: #16a34a; }
    .icon-hist { background-color: #fef2f2; color: #dc2626; }
    .icon-conf { background-color: #faf5ff; color: #7c3aed; }
</style>

<main class="container py-5">

    <div class="dashboard-hero text-center animate__animated animate__fadeIn">
        <div class="hero-icon">
            <i class="fas fa-project-diagram"></i>
        </div>
        <h1 class="display-5 fw-bold mb-2">TDT Optimization</h1>
        <p class="lead opacity-75">
            <?= __('dash_subtitle') ?>
        </p>
    </div>

    <section class="row g-4">

        <div class="col-md-6 col-xl-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
            <a href="enter-data" class="dash-card">
                <div class="card-icon icon-data">
                    <i class="fas fa-edit"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('enter_data') ?></h3>
                <p class="text-muted small mb-0">
                    <?= __('dash_enter_data_desc') ?>
                </p>
            </a>
        </div>

        <div class="col-md-6 col-xl-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <a href="template-generator" class="dash-card">
                <div class="card-icon icon-gen">
                    <i class="fas fa-magic"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('template_generator') ?></h3>
                <p class="text-muted small mb-0">
                    <?= __('dash_gen_desc') ?>
                </p>
            </a>
        </div>

        <div class="col-md-6 col-xl-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
            <a href="history" class="dash-card">
                <div class="card-icon icon-hist">
                    <i class="fas fa-history"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('history') ?></h3>
                <p class="text-muted small mb-0">
                    <?= __('dash_history_desc') ?>
                </p>
            </a>
        </div>

        <?php if (isAdmin()): ?>
        <div class="col-md-6 col-xl-3 animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
            <a href="configurations" class="dash-card">
                <div class="card-icon icon-conf">
                    <i class="fas fa-cogs"></i>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= __('configurations') ?></h3>
                <p class="text-muted small mb-0">
                    <?= __('dash_config_desc') ?>
                </p>
            </a>
        </div>
        <?php endif; ?>

    </section>

</main>

<?php include __DIR__ . "/templates/footer.php"; ?>
