<?php include "templates/header.php"; ?>
<?php include "templates/navbar.php"; ?>

<main class="container dashboard">

    <h1 class="page-title">TDT Optimization – Dashboard</h1>

    <section class="dashboard-grid">

        <a href="enter_data.php" class="dash-card">
            <h2>Enter Data</h2>
            <p>Upload Excel or enter manually.</p>
        </a>

        <a href="results.php" class="dash-card">
            <h2>View Results</h2>
            <p>See optimization outcomes.</p>
        </a>

        <a href="results_history.php" class="dash-card">
            <h2>History</h2>
            <p>Browse past datasets and runs.</p>
        </a>

        <a href="configurations.php" class="dash-card">
            <h2>Configurations</h2>
            <p>Manage derivadores, repartidores, parameters.</p>
        </a>

    </section>

</main>

<?php include "templates/footer.php"; ?>
