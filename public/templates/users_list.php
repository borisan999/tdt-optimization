<h2 class="section-title"><?= __('manage_users') ?></h2>

<a href="/tdt-optimization/users?action=create" class="btn-primary">
    <?= __('new_user') ?>
</a>

<table class="table">
    <thead>
        <tr>
            <th><?= __('username') ?></th>
            <th><?= __('action') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td>
                    <a href="/tdt-optimization/users?action=edit&id=<?= (int)$u['user_id'] ?>"
                       class="btn-small">
                        <?= __('edit') ?>
                    </a>

                    <a href="/tdt-optimization/users?action=disable&id=<?= (int)$u['user_id'] ?>"
                       class="btn-small"
                       onclick="return confirm('<?= __('confirm_delete') ?>')">
                        <?= __('delete') ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
