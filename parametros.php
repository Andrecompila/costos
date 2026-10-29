<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

$mensaje = '';

// Procesar actualización de parámetros
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_parametros'])) {
    try {
        $conexion->beginTransaction();
        if (isset($_POST['valores']) && is_array($_POST['valores'])) {
            foreach ($_POST['valores'] as $clave => $valor) {
                $stmt = $conexion->prepare("UPDATE parametros SET valor = ? WHERE clave = ?");
                $stmt->execute([trim($valor), $clave]);
            }
        }
        $conexion->commit();
        $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Parámetros actualizados correctamente!</div>";
    } catch (Exception $e) {
        $conexion->rollBack();
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error al actualizar los parámetros.</div>";
    }
}

// Consultamos los parámetros
$stmt = $conexion->query("SELECT * FROM parametros ORDER BY clave ASC");
$parametros = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0 0 5px 0;"><i class="fa-solid fa-gears" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> Parámetros del Sistema</h2>
        <p style="color: var(--label-color); margin: 0;">Configura los valores globales que afectan los cálculos de costos y márgenes de tu restaurante.</p>
    </div>
</div>

<?= $mensaje ?>

<div class="card">
    <form method="POST">
        <table style="width: 100%; margin-top: 0;">
            <thead>
                <tr>
                    <th>Clave</th>
                    <th>Valor Configurado</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($parametros) > 0): ?>
                    <?php foreach ($parametros as $param): ?>
                        <?php 
                            // Limpia los ceros decimales excesivos si es un número
                            $val_mostrar = $param['valor'];
                            if (is_numeric($val_mostrar)) {
                                $val_mostrar = 0 + $val_mostrar; 
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($param['clave']) ?></strong></td>
                            <td>
                                <input type="text" name="valores[<?= htmlspecialchars($param['clave']) ?>]" value="<?= htmlspecialchars($val_mostrar) ?>" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                            </td>
                            <td style="color: var(--label-color);"><?= htmlspecialchars($param['descripcion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--label-color); padding: 30px;">No hay parámetros configurados en el sistema.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <button type="submit" name="actualizar_parametros" class="btn btn-success" style="padding: 10px 18px;">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>