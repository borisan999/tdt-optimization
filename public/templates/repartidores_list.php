<?php
/** @var array $repartidores */
?>

<h2 class="section-title"><?= __('repartidores') ?></h2>

<a href="repartidores.php?action=create" class="btn-primary">
    <?= __('new_repartidor') ?>
</a>

<table class="table">
    <thead>
        <tr>
            <th><?= __('col_model') ?></th>
            <th><?= __('col_outs') ?></th>
            <th><?= __('col_ins_loss') ?></th>
            <th><?= __('tech_params') ?></th>
            <th><?= __('action') ?></th>
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
                        <?= __('edit') ?>
                    </a>

                    <a class="btn-small"
                       href="repartidores.php?action=delete&id=<?= (int)$r['rep_id'] ?>"
                       onclick="return confirm('<?= __('confirm_delete') ?>')">
                        <?= __('delete') ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
