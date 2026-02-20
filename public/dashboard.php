<?php require_once __DIR__ . '/../app/auth/require_login.php'; ?>
<?php include __DIR__ . "/templates/header.php"; ?>
<?php include __DIR__ . "/templates/navbar.php"; ?>

<main class="container py-4">

    <div class="mb-4">
        <h1 class="page-title">TDT Optimization</h1>
        <p class="text-muted mb-0">
            Engineering dashboard for distribution network optimization
        </p>
    </div>

    <section class="dashboard-grid">

        <a href="enter-data" class="dash-card">
            <h2 class="dash-title">Enter Data</h2>
            <p class="dash-desc">
                Upload Excel datasets or enter building data manually.
            </p>
        </a>

        <a href="results-history" class="dash-card">
            <h2 class="dash-title">History</h2>
            <p class="dash-desc">
                Browse previous datasets and optimization runs.
            </p>
        </a>

        <a href="configurations" class="dash-card">
            <h2 class="dash-title">Configurations</h2>
            <p class="dash-desc">
                Manage derivadores, repartidores, and system parameters.
            </p>
        </a>

    </section>

</main>

<?php include __DIR__ . "/templates/footer.php"; ?>
