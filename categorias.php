<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

$mensaje = '';
$modo_edicion = false;
$cat_a_editar = ['id' => '', 'nombre' => '', 'tipo' => 'receta'];

// Procesar Eliminar Categoría
if (isset($_GET['eliminar_cat'])) {
    $id_eliminar = intval($_GET['eliminar_cat']);
    try {
        $stmt = $conexion->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->execute([$id_eliminar]);
        $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Categoría eliminada correctamente!</div>";
    } catch (PDOException $e) {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error: No se puede eliminar porque está asociada a insumos o recetas.</div>";
    }
}

// Cargar Categoría para Editar
if (isset($_GET['editar_cat'])) {
    $id_editar = intval($_GET['editar_cat']);
    $stmt = $conexion->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id_editar]);
    $resultado = $stmt->fetch();
    if ($resultado) {
        $modo_edicion = true;
        $cat_a_editar = $resultado;
    }
}

// Procesar Guardar (Crear o Actualizar)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $tipo = trim($_POST['tipo']);

    if (!empty($nombre) && !empty($tipo)) {
        if (isset($_POST['actualizar_categoria'])) {
            $id = intval($_POST['id']);
            $stmt = $conexion->prepare("UPDATE categorias SET nombre = ?, tipo = ? WHERE id = ?");
            $stmt->execute([$nombre, $tipo, $id]);
            $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Categoría actualizada correctamente!</div>";
            $modo_edicion = false;
            $cat_a_editar = ['id' => '', 'nombre' => '', 'tipo' => 'receta'];
        } elseif (isset($_POST['guardar_categoria'])) {
            try {
                $stmt = $conexion->prepare("INSERT INTO categorias (nombre, tipo) VALUES (?, ?)");
                $stmt->execute([$nombre, $tipo]);
                $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Categoría registrada correctamente!</div>";
            } catch (PDOException $e) {
                $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error: La categoría ya existe.</div>";
            }
        }
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Por favor completa todos los campos.</div>";
    }
}

// Consultar las categorías ordenadas por tipo
$stmt = $conexion->query("SELECT * FROM categorias ORDER BY tipo ASC, nombre ASC");
$categorias = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0 0 5px 0;"><i class="fa-solid fa-tags" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> Gestión de Categorías</h2>
        <p style="color: var(--label-color); margin: 0;">Organiza tus insumos y tus recetas por secciones (ej. Carnes, Abarrotes, Platos de Fondo, Bebidas).</p>
    </div>
</div>

<?= $mensaje ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; align-items: start;">
    
    <!-- Formulario Dinámico de Categorías -->
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
            <i class="fa-solid <?= $modo_edicion ? 'fa-pen-to-square' : 'fa-folder-plus' ?>" style="margin-right: 6px;"></i> 
            <?= $modo_edicion ? 'Editar Categoría #' . $cat_a_editar['id'] : 'Agregar Nueva Categoría' ?>
        </h3>
        
        <form method="POST">
            <?php if ($modo_edicion): ?>
                <input type="hidden" name="id" value="<?= $cat_a_editar['id'] ?>">
            <?php endif; ?>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Nombre de la Categoría:</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($cat_a_editar['nombre']) ?>" required placeholder="Ej. Frituras / Abarrotes / Bebidas" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">¿Para qué se usará?:</label>
                <select name="tipo" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                    <option value="receta" <?= $cat_a_editar['tipo'] == 'receta' ? 'selected' : '' ?>>Para Platos / Recetas</option>
                    <option value="insumo" <?= $cat_a_editar['tipo'] == 'insumo' ? 'selected' : '' ?>>Para Insumos / Almacén</option>
                </select>
            </div>
            
            <div>
                <?php if ($modo_edicion): ?>
                    <button type="submit" name="actualizar_categoria" class="btn btn-warning" style="padding: 10px 16px;">
                        <i class="fa-solid fa-floppy-disk"></i> Actualizar
                    </button>
                    <a href="index.php?seccion=categorias" class="btn btn-secondary" style="padding: 10px 16px; margin-left: 5px;">
                        <i class="fa-solid fa-xmark"></i> Cancelar
                    </a>
                <?php else: ?>
                    <button type="submit" name="guardar_categoria" class="btn btn-success" style="padding: 10px 16px;">
                        <i class="fa-solid fa-plus"></i> Guardar Categoría
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla de Categorías Registradas -->
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
            <i class="fa-solid fa-list" style="margin-right: 6px;"></i> Lista de Categorías
        </h3>
        
        <table style="margin-top: 0; width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Tipo Destinado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($categorias) > 0): ?>
                    <?php foreach ($categorias as $cat): ?>
                        <tr>
                            <td><?= $cat['id'] ?></td>
                            <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                            <td>
                                <?php if ($cat['tipo'] == 'receta'): ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: rgba(37, 99, 235, 0.1); color: #2563eb;"><i class="fa-solid fa-utensils"></i> Receta</span>
                                <?php else: ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: rgba(22, 163, 74, 0.1); color: #16a34a;"><i class="fa-solid fa-boxes-stacked"></i> Insumo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="index.php?seccion=categorias&editar_cat=<?= $cat['id'] ?>" class="btn btn-warning" style="padding: 6px 10px; font-size: 12px;" title="Editar">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="index.php?seccion=categorias&eliminar_cat=<?= $cat['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar esta categoría?');" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--label-color); padding: 20px;">No hay categorías registradas todavía.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>