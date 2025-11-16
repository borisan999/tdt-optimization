<?php include "templates/header.php"; ?>
<?php include "templates/navbar.php"; ?>

<main class="container">

    <h1 class="page-title">Dashboard Configurations</h1>

    <div class="tabs">
        <button class="tab-btn active" data-tab="derivadores">Derivadores</button>
        <button class="tab-btn" data-tab="repartidores">Repartidores</button>
        <button class="tab-btn" data-tab="generales">Parámetros Generales</button>
        <button class="tab-btn" data-tab="usuarios">Users</button>
    </div>

    <!-- DERIVADORES -->
    <div class="tab-content active" id="derivadores">
        <div class="card">
            <h2 class="section-title">Derivadores</h2>
            <button class="btn-primary">Add New</button>

            <table class="table">
                <thead>
                    <tr>
                        <th>Modelo</th>
                        <th>Derivación</th>
                        <th>Paso</th>
                        <th>Salidas</th>
                        <th>Inserción</th>
                        <th>...</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>TRIS-210E</td>
                        <td>20</td>
                        <td>3.5</td>
                        <td>1</td>
                        <td>4</td>
                        <td><a class="btn-small">Edit</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- REPARTIDORES -->
    <div class="tab-content" id="repartidores">
        <div class="card">
            <h2 class="section-title">Repartidores</h2>
            <button class="btn-primary">Add New</button>

            <table class="table">
                <thead>
                    <tr>
                        <th>Modelo</th>
                        <th>Salidas</th>
                        <th>Pérdida</th>
                        <th>Inserción</th>
                        <th>...</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Splitter-2</td>
                        <td>2</td>
                        <td>3.5</td>
                        <td>1</td>
                        <td><a class="btn-small">Edit</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- GENERAL PARAMETERS -->
    <div class="tab-content" id="generales">
        <div class="card">
            <h2 class="section-title">Parámetros Generales</h2>
            <button class="btn-primary">Add Parameter</button>

            <table class="table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>target_signal</td>
                        <td>60</td>
                        <td>dBµV</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- USERS -->
    <div class="tab-content" id="usuarios">
        <div class="card">
            <h2 class="section-title">Users</h2>
            <button class="btn-primary">Add User</button>

            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>admin</td>
                        <td>admin@system</td>
                        <td>Active</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php include "templates/footer.php"; ?>
