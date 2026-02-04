<?php
/** @var array $derivadores */
?>

<h2 class="section-title">Derivadores</h2>

<a href="derivadores.php?action=create" class="btn-primary">
    Nuevo derivador
</a>

<div class="card">

    <table>
        <thead>
            <tr>
                <th>Modelo</th>
                <th>Derivación (dB)</th>
                <th>Paso (dB)</th>
                <th>Salidas</th>
                <th>Pérdida inserción (dB)</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($derivadores as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['modelo']) ?></td>
                    <td><?= (int)$d['derivacion'] ?></td>
                    <td><?= (int)$d['paso'] ?></td>
                    <td><?= (int)$d['salidas'] ?></td>
                    <td><?= number_format($d['perdida_insercion'], 3) ?></td>
                    <td>
                        <a class="btn-small"
                           href="derivadores.php?action=edit&id=<?= (int)$d['deriv_id'] ?>">
                            Editar
                        </a>

                        <a class="btn-small btn-secondary"
                           href="derivadores.php?action=delete&id=<?= (int)$d['deriv_id'] ?>"
                           onclick="return confirm('¿Eliminar derivador?')">
                            Eliminar
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>
