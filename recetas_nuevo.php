<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

$modo_edicion = false;
$mensaje = '';

// Inicializar estructura por defecto para evitar errores si faltan claves
$receta = [
    'id' => '',
    'nombre' => '',
    'categoria_id' => '',
    'rendimiento' => '1',
    'tipo_unidad' => 'Porción',
    'conservacion' => '',
    'descartables' => '0.00',
    'imagen' => ''
];

// Verificar si estamos editando
if (isset($_GET['id'])) {
    $modo_edicion = true;
    $id_receta = intval($_GET['id']);
    $stmt = $conexion->prepare("SELECT * FROM recetas WHERE id = ?");
    $stmt->execute([$id_receta]);
    $receta_db = $stmt->fetch();
    if ($receta_db) {
        $receta = $receta_db;
    }
}

// Consultar categorías para el select
$stmt_cat = $conexion->query("SELECT * FROM categorias ORDER BY nombre ASC");
$categorias = $stmt_cat->fetchAll();

// Procesar Formulario al enviar (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $rendimiento = floatval($_POST['rendimiento']);
    $tipo_unidad = trim($_POST['tipo_unidad']);
    $conservacion = trim($_POST['conservacion']);
    // Capturamos el valor de descartables (si está vacío o en blanco, toma 0.00)
    $descartables = !empty($_POST['descartables']) ? floatval($_POST['descartables']) : 0.00;
    
    $imagen_actual = $_POST['imagen_actual'] ?? '';
    $nombre_imagen = $imagen_actual;

    // Manejo de la subida de imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = 'receta_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['imagen']['tmp_name'], 'uploads/' . $nombre_imagen);
    }

    if (!empty($nombre)) {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Actualizar
            $id_act = intval($_POST['id']);
            $stmt_up = $conexion->prepare("UPDATE recetas SET nombre = ?, categoria_id = ?, rendimiento = ?, tipo_unidad = ?, conservacion = ?, descartables = ?, imagen = ? WHERE id = ?");
            $stmt_up->execute([$nombre, $categoria_id, $rendimiento, $tipo_unidad, $conservacion, $descartables, $nombre_imagen, $id_act]);
            
            header("Location: index.php?seccion=recetas_ver&id=" . $id_act);
            exit;
        } else {
            // Insertar nueva receta
            $stmt_ins = $conexion->prepare("INSERT INTO recetas (nombre, categoria_id, rendimiento, tipo_unidad, conservacion, descartables, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins->execute([$nombre, $categoria_id, $rendimiento, $tipo_unidad, $conservacion, $descartables, $nombre_imagen]);
            $nuevo_id = $conexion->lastInsertId();

            header("Location: index.php?seccion=recetas_ver&id=" . $nuevo_id);
            exit;
        }
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> El nombre del plato es obligatorio.</div>";
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0 0 5px 0;">
            <i class="fa-solid <?= $modo_edicion ? 'fa-pen-to-square' : 'fa-utensils' ?>" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> 
            <?= $modo_edicion ? 'Editar Receta #' . $receta['id'] : 'Crear Nueva Receta' ?>
        </h2>
        <p style="color: var(--label-color); margin: 0;">Configura los datos generales, rendimiento, conservación, envases y la fotografía de tu plato.</p>
    </div>
    <div>
        <a href="index.php?seccion=recetas" class="btn btn-secondary" style="padding: 10px 16px;">
            <i class="fa-solid fa-arrow-left"></i> Volver a Recetas
        </a>
    </div>
</div>

<?= $mensaje ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form method="POST" enctype="multipart/form-data">
        <?php if ($modo_edicion): ?>
            <input type="hidden" name="id" value="<?= $receta['id'] ?>">
            <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($receta['imagen'] ?? '') ?>">
        <?php endif; ?>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Nombre del Plato:</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($receta['nombre']) ?>" required placeholder="Ej. Lomo Saltado" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Categoría:</label>
            <select name="categoria_id" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                <option value="">-- Selecciona una categoría --</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($receta['categoria_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Rendimiento (Número de unidades/porciones que rinde):</label>
            <input type="number" step="any" name="rendimiento" value="<?= $receta['rendimiento'] ?>" required placeholder="Ej. 1" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>

        <!-- Tipo de Unidad, Conservación y Costo de Descartables -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Tipo de Unidad / Presentación:</label>
                <select name="tipo_unidad" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                    <?php $t_actual = $receta['tipo_unidad'] ?? 'Porción'; ?>
                    <option value="Porción" <?= ($t_actual == 'Porción') ? 'selected' : '' ?>>Porción</option>
                    <option value="Plato" <?= ($t_actual == 'Plato') ? 'selected' : '' ?>>Plato</option>
                    <option value="Vaso" <?= ($t_actual == 'Vaso') ? 'selected' : '' ?>>Vaso</option>
                    <option value="Copa" <?= ($t_actual == 'Copa') ? 'selected' : '' ?>>Copa</option>
                    <option value="Bowl" <?= ($t_actual == 'Bowl') ? 'selected' : '' ?>>Bowl</option>
                    <option value="Jarra" <?= ($t_actual == 'Jarra') ? 'selected' : '' ?>>Jarra</option>
                    <option value="Unidad" <?= ($t_actual == 'Unidad') ? 'selected' : '' ?>>Unidad</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Método de Conservación:</label>
                <select name="conservacion" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                    <?php $c_actual = $receta['conservacion'] ?? 'No aplica'; ?>
                    <option value="No aplica" <?= ($c_actual == 'No aplica') ? 'selected' : '' ?>>No aplica</option>
                    <option value="Temperatura ambiente" <?= ($c_actual == 'Temperatura ambiente') ? 'selected' : '' ?>>Temperatura ambiente</option>
                    <option value="Refrigeración (2°C a 4°C)" <?= ($c_actual == 'Refrigeración (2°C a 4°C)') ? 'selected' : '' ?>>Refrigeración (2°C a 4°C)</option>
                    <option value="Congelación (-18°C)" <?= ($c_actual == 'Congelación (-18°C)') ? 'selected' : '' ?>>Congelación (-18°C)</option>
                    <option value="Lugar fresco y seco" <?= ($c_actual == 'Lugar fresco y seco') ? 'selected' : '' ?>>Lugar fresco y seco</option>
                </select>
            </div>
        </div>

        <!-- Campo de Descartables Variable -->
        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">
                Costo de Descartables / Envases (S/):
            </label>
            <input type="number" step="0.01" name="descartables" value="<?= ($receta['descartables'] > 0) ? $receta['descartables'] : '' ?>" placeholder="Ej. 2.60 (opcional)" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
            <small style="color: var(--label-color); font-size: 11px; display: block; margin-top: 4px;">Déjalo vacío o en 0 si el plato no lleva envase para llevar.</small>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Foto del Plato:</label>
            <?php if (!empty($receta['imagen']) && file_exists('uploads/' . $receta['imagen'])): ?>
                <div style="margin-bottom: 10px;">
                    <img src="uploads/<?= htmlspecialchars($receta['imagen']) ?>" alt="Actual" style="width: 100px; height: 100px; border-radius: 6px; object-fit: cover; margin-bottom: 5px;">
                    <p style="font-size: 12px; color: var(--label-color); margin: 0;">Imagen actual (si subes otra, se reemplazará)</p>
                </div>
            <?php endif; ?>
            <input type="file" name="imagen" accept="image/*" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>

        <div style="margin-top: 20px;">
            <?php if ($modo_edicion): ?>
                <button type="submit" class="btn btn-warning" style="padding: 10px 16px;">
                    <i class="fa-solid fa-floppy-disk"></i> Actualizar Receta
                </button>
                <a href="index.php?seccion=recetas" class="btn btn-secondary" style="padding: 10px 16px; margin-left: 10px;">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </a>
            <?php else: ?>
                <button type="submit" class="btn btn-success" style="padding: 10px 16px;">
                    <i class="fa-solid fa-arrow-right"></i> Guardar y Continuar
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>