<?php include "templates/header.php"; ?>
<?php include "templates/navbar.php"; ?>

<main class="container">

    <h1 class="page-title">Optimization Results</h1>

    <div class="card">

        <h2 class="section-title">Summary</h2>

        <div class="summary-grid">
            <div><strong>Dataset:</strong> #123</div>
            <div><strong>Run Time:</strong> 2.4 seconds</div>
            <div><strong>Target Level:</strong> 60 dBµV</div>
            <div><strong>Deviation:</strong> ±3 dBµV</div>
        </div>

    </div>

    <div class="card">
        <h2 class="section-title">Signal Levels</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>Floor</th>
                    <th>Apt</th>
                    <th>Final dBµV</th>
                    <th>Deviation</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>3</td><td>302</td><td>59.8</td><td>-0.2</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 class="section-title">Components Used</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Model</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Derivador</td>
                    <td>TRIS-210E</td>
                    <td>4</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="export-area">
        <a class="btn-primary" href="export.php?format=excel">Download Excel</a>
        <a class="btn-secondary" href="export.php?format=pdf">Download PDF</a>
    </div>

</main>

<?php include "templates/footer.php"; ?>
