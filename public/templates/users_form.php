<h2 class="section-title">
    <?= $user ? 'Editar usuario' : 'Nuevo usuario' ?>
</h2>

<form method="post" action="users.php?action=save">

    <input type="hidden" name="mode" value="<?= $user ? 'edit' : 'create' ?>">

    <?php if ($user): ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
    <?php endif; ?>

    <div class="form-group">
        <label>Username</label>
        <input type="text"
               name="username"
               required
               value="<?= htmlspecialchars($user['username'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Email</label>
        <input type="email"
               name="email"
               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>
            Password <?= $user ? '(leave blank to keep)' : '' ?>
        </label>
        <input type="password" name="password">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">
            Guardar
        </button>

        <a href="users.php" class="btn-secondary">
            Cancelar
        </a>
    </div>

</form>
