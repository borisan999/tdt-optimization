<?php
$isEdit = isset($derivador) && is_array($derivador);
?>

<h2 class="section-title">
    <?= $isEdit ? 'Editar derivador' : 'Nuevo derivador' ?>
</h2>

<form method="post" action="derivadores.php?action=save">

    <?php if ($isEdit): ?>
        <input type="hidden" name="deriv_id" value="<?= (int)$derivador['deriv_id'] ?>">
    <?php endif; ?>

    <div class="form-group">
        <label>Modelo</label>
        <input type="text" name="modelo" required
               value="<?= htmlspecialchars($derivador['modelo'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Derivación (dB)</label>
        <input type="number" name="derivacion" required step="any"
               value="<?= $derivador['derivacion'] ?? '' ?>">
    </div>

    <div class="form-group">
        <label>Paso (dB)</label>
        <input type="number" name="paso" required step="any"
               value="<?= $derivador['paso'] ?? '' ?>">
    </div>

    <div class="form-group">
        <label>Salidas</label>
        <input type="number" name="salidas" required
               value="<?= $derivador['salidas'] ?? '' ?>">
    </div>



    <div class="form-actions">
        <button type="submit" class="btn-primary">Guardar</button>
        <a href="derivadores.php" class="btn-secondary">Cancelar</a>
    </div>

</form>
