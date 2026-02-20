<?php
require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>

<main class="container">

    <h1 class="page-title">Configurations</h1>

    <div class="dashboard-grid">

        <!-- Derivadores -->
        <div class="dash-card">
            <h2 class="section-title">Derivadores</h2>
            <p>
                Manage signal derivators used in the distribution network.
            </p>
            <a
                class="btn-primary"
                href="derivadores"
            >
                Manage Derivadores
            </a>
        </div>

        <!-- Repartidores -->
        <div class="dash-card">
            <h2 class="section-title">Repartidores</h2>
            <p>
                Manage splitters and distribution devices.
            </p>
            <a
                class="btn-primary"
                href="repartidores"
            >
                Manage Repartidores
            </a>
        </div>

        <!-- Users -->
        <div class="dash-card">
            <h2 class="section-title">Users</h2>
            <p>
                Manage system users and access.
            </p>
            <a
                class="btn-primary"
                href="users"
            >
                Manage Users
            </a>
        </div>

    </div>

</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
