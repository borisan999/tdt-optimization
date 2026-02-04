<?php
/** @var array $repartidores */
?>

<h2 class="section-title">Repartidores</h2>

<a href="repartidores.php?action=create" class="btn-primary">
    Nuevo repartidor
</a>

<table class="table">
    <thead>
        <tr>
            <th>Modelo</th>
            <th>Salidas</th>
            <th>Pérdida inserción (dB)</th>
            <th>Frecuencia</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($repartidores as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['modelo']) ?></td>
                <td><?= (int)$r['salidas'] ?></td>
                <td><?= number_format($r['perdida_insercion'], 3) ?></td>
                <td><?= htmlspecialchars($r['frecuencia']) ?></td>
                <td>
                    <a class="btn-small"
                       href="repartidores.php?action=edit&id=<?= (int)$r['rep_id'] ?>">
                        Editar
                    </a>

                    <a class="btn-small"
                       href="repartidores.php?action=delete&id=<?= (int)$r['rep_id'] ?>"
                       onclick="return confirm('¿Eliminar repartidor?')">
                        Eliminar
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
