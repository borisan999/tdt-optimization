<?php
// $repartidor may be null (create) or array (edit)
$isEdit = isset($repartidor) && is_array($repartidor);
?>

<h2 class="section-title">
    <?= $isEdit ? 'Editar repartidor' : 'Nuevo repartidor' ?>
</h2>

<form method="post" action="repartidores.php?action=save">

    <?php if ($isEdit): ?>
        <input type="hidden" name="rep_id" value="<?= (int)$repartidor['rep_id'] ?>">
    <?php endif; ?>

    <div class="form-group">
        <label>Modelo</label>
        <input type="text"
               name="modelo"
               required
               value="<?= htmlspecialchars($repartidor['modelo'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Salidas</label>
        <input type="number"
               name="salidas"
               min="1"
               required
               value="<?= (int)($repartidor['salidas'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Pérdida de inserción (dB)</label>
        <input type="number"
               step="0.001"
               name="perdida_insercion"
               required
               value="<?= htmlspecialchars($repartidor['perdida_insercion'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Frecuencia</label>
        <input type="text"
               name="frecuencia"
               required
               value="<?= htmlspecialchars($repartidor['frecuencia'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Descripción</label>
        <textarea name="descripcion" rows="3"><?= htmlspecialchars($repartidor['descripcion'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">
            <?= $isEdit ? 'Actualizar' : 'Crear' ?>
        </button>

        <a href="repartidores.php" class="btn-secondary">
            Cancelar
        </a>
    </div>

</form>
