<h2 class="section-title">Usuarios</h2>

<a href="users.php?action=create" class="btn-primary">
    Nuevo usuario
</a>

<table class="table">
    <thead>
        <tr>
            <th>Username</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td>
                    <a href="users.php?action=edit&id=<?= (int)$u['user_id'] ?>"
                       class="btn-small">
                        Editar
                    </a>

                    <a href="users.php?action=disable&id=<?= (int)$u['user_id'] ?>"
                       class="btn-small"
                       onclick="return confirm('¿Eliminar usuario?')">
                        Eliminar
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
