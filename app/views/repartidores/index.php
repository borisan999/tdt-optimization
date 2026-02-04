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
    <td><?= $r['salidas'] ?></td>
    <td><?= $r['perdida_insercion'] ?></td>
    <td><?= htmlspecialchars($r['frecuencia']) ?></td>
    <td>
        <a href="repartidores.php?action=edit&id=<?= $r['rep_id'] ?>">Editar</a>
        <form method="POST"
              action="repartidores.php?action=delete&id=<?= $r['rep_id'] ?>"
              style="display:inline">
            <button type="submit"
                    onclick="return confirm('¿Eliminar repartidor?')">
                Eliminar
            </button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
