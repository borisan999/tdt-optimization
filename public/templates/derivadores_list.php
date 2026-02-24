<?php
/** @var array $derivadores */
?>

<h2 class="section-title"><?= __('derivadores') ?></h2>

<a href="derivadores.php?action=create" class="btn-primary">
    <?= __('new_derivador') ?>
</a>

<div class="card">

    <table>
        <thead>
            <tr>
                <th><?= __('col_model') ?></th>
                <th><?= __('col_deriv') ?></th>
                <th><?= __('col_pass') ?></th>
                <th><?= __('col_outs') ?></th>
                <th><?= __('col_ins_loss') ?></th>
                <th><?= __('action') ?></th>
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
                            <?= __('edit') ?>
                        </a>

                        <a class="btn-small btn-secondary"
                           href="derivadores.php?action=delete&id=<?= (int)$d['deriv_id'] ?>"
                           onclick="return confirm('<?= __('confirm_delete') ?>')">
                            <?= __('delete') ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>
