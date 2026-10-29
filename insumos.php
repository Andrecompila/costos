<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

$mensaje = '';
$modo_edicion = false;
$ins_a_editar = [
    'id' => '', 'nombre' => '', 'unidad_medida' => 'GRAMOS', 
    'cantidad_empaque' => '', 'precio_compra' => '', 'proveedor' => '', 'afecto_igv' => 1, 'imagen' => ''
];

// Procesar Eliminación Masiva de Insumos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_seleccionados'])) {
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = $_POST['ids'];
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conexion->prepare("DELETE FROM insumos WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Los insumos seleccionados fueron eliminados correctamente!</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error: Algunos insumos están vinculados a recetas y no se pueden eliminar.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> No seleccionaste ningún insumo para eliminar.</div>";
    }
}

// Procesar Eliminar Insumo Individual
if (isset($_GET['eliminar_ins'])) {
    $id_eliminar = intval($_GET['eliminar_ins']);
    try {
        $stmt = $conexion->prepare("DELETE FROM insumos WHERE id = ?");
        $stmt->execute([$id_eliminar]);
        $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Insumo eliminado correctamente!</div>";
    } catch (PDOException $e) {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error: No se puede eliminar porque está vinculado a una receta.</div>";
    }
}

// Cargar Insumo para Editar
if (isset($_GET['editar_ins'])) {
    $id_editar = intval($_GET['editar_ins']);
    $stmt = $conexion->prepare("SELECT * FROM insumos WHERE id = ?");
    $stmt->execute([$id_editar]);
    $resultado = $stmt->fetch();
    if ($resultado) {
        $modo_edicion = true;
        $ins_a_editar = $resultado;
    }
}

// Procesar Guardar (Crear)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['guardar_insumo']))) {
    $nombre = trim($_POST['nombre']);
    $tipo_unidad = $_POST['tipo_unidad']; 
    $cantidad_input = floatval($_POST['cantidad_empaque']);
    $precio_ingresado = floatval($_POST['precio_compra']);
    $proveedor = trim($_POST['proveedor']);
    $afecto_igv = isset($_POST['afecto_igv']) ? 1 : 0;

    // Si incluye IGV, el precio real de costo (neto) se calcula dividiendo entre 1.18
    $precio_compra = $afecto_igv ? ($precio_ingresado / 1.18) : $precio_ingresado;

    $nombre_imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = uniqid('insumo_') . '.' . $extension;
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        move_uploaded_file($_FILES['imagen']['tmp_name'], 'uploads/' . $nombre_imagen);
    }

    $unidad_medida = 'GRAMOS';
    $cantidad_empaque = $cantidad_input;

    if ($tipo_unidad == 'KG') {
        $unidad_medida = 'GRAMOS';
        $cantidad_empaque = $cantidad_input * 1000;
    } elseif ($tipo_unidad == 'LITRO') {
        $unidad_medida = 'MILILITRO';
        $cantidad_empaque = $cantidad_input * 1000;
    } elseif ($tipo_unidad == 'GRAMOS') {
        $unidad_medida = 'GRAMOS';
        $cantidad_empaque = $cantidad_input;
    } elseif ($tipo_unidad == 'MILILITRO') {
        $unidad_medida = 'MILILITRO';
        $cantidad_empaque = $cantidad_input;
    } else {
        $unidad_medida = 'UNIDAD';
        $cantidad_empaque = $cantidad_input;
    }

    if (!empty($nombre) && $cantidad_empaque > 0) {
        try {
            $stmt = $conexion->prepare("INSERT INTO insumos (nombre, unidad_medida, cantidad_empaque, precio_compra, proveedor, afecto_igv, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $unidad_medida, $cantidad_empaque, $precio_compra, $proveedor, $afecto_igv, $nombre_imagen]);
            $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Insumo registrado correctamente!</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error: El insumo ya existe.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Por favor completa los campos obligatorios.</div>";
    }
}

// Procesar Actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_insumo'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $tipo_unidad = $_POST['tipo_unidad'];
    $cantidad_input = floatval($_POST['cantidad_empaque']);
    $precio_ingresado = floatval($_POST['precio_compra']);
    $proveedor = trim($_POST['proveedor']);
    $afecto_igv = isset($_POST['afecto_igv']) ? 1 : 0;

    // Ajuste de precio neto según IGV
    $precio_compra = $afecto_igv ? ($precio_ingresado / 1.18) : $precio_ingresado;

    $nombre_imagen = $_POST['imagen_actual'] ?? null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = uniqid('insumo_') . '.' . $extension;
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        move_uploaded_file($_FILES['imagen']['tmp_name'], 'uploads/' . $nombre_imagen);
    }

    $unidad_medida = 'GRAMOS';
    $cantidad_empaque = $cantidad_input;

    if ($tipo_unidad == 'KG') {
        $unidad_medida = 'GRAMOS';
        $cantidad_empaque = $cantidad_input * 1000;
    } elseif ($tipo_unidad == 'LITRO') {
        $unidad_medida = 'MILILITRO';
        $cantidad_empaque = $cantidad_input * 1000;
    } elseif ($tipo_unidad == 'GRAMOS') {
        $unidad_medida = 'GRAMOS';
        $cantidad_empaque = $cantidad_input;
    } elseif ($tipo_unidad == 'MILILITRO') {
        $unidad_medida = 'MILILITRO';
        $cantidad_empaque = $cantidad_input;
    } else {
        $unidad_medida = 'UNIDAD';
        $cantidad_empaque = $cantidad_input;
    }

    if (!empty($nombre) && $cantidad_empaque > 0) {
        $stmt = $conexion->prepare("UPDATE insumos SET nombre = ?, unidad_medida = ?, cantidad_empaque = ?, precio_compra = ?, proveedor = ?, afecto_igv = ?, imagen = ? WHERE id = ?");
        $stmt->execute([$nombre, $unidad_medida, $cantidad_empaque, $precio_compra, $proveedor, $afecto_igv, $nombre_imagen, $id]);
        $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Insumo actualizado correctamente!</div>";
        $modo_edicion = false;
        $ins_a_editar = ['id' => '', 'nombre' => '', 'unidad_medida' => 'GRAMOS', 'cantidad_empaque' => '', 'precio_compra' => '', 'proveedor' => '', 'afecto_igv' => 1, 'imagen' => ''];
    }
}

// Consultar los insumos con búsqueda opcional
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

if (!empty($busqueda)) {
    $stmt = $conexion->prepare("SELECT * FROM insumos WHERE nombre LIKE ? OR proveedor LIKE ? ORDER BY nombre ASC");
    $stmt->execute(['%' . $busqueda . '%', '%' . $busqueda . '%']);
} else {
    $stmt = $conexion->query("SELECT * FROM insumos ORDER BY nombre ASC");
}
$insumos = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0 0 5px 0;"><i class="fa-solid fa-carrot" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> Maestro de Insumos</h2>
        <p style="color: var(--label-color); margin: 0;">Registra tus productos indicando si incluyen IGV y su respectiva foto.</p>
    </div>
    
    <!-- Formulario de Buscador -->
    <form method="GET" action="index.php" style="display: flex; gap: 8px; margin: 0;">
        <input type="hidden" name="seccion" value="insumos">
        <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar insumo o proveedor..." style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a); width: 220px; font-size: 13px;">
        <button type="submit" class="btn btn-secondary" style="padding: 8px 12px; font-size: 13px;" title="Buscar">
            <i class="fa-solid fa-search"></i>
        </button>
        <?php if (!empty($busqueda)): ?>
            <a href="index.php?seccion=insumos" class="btn btn-secondary" style="padding: 8px 10px; font-size: 13px; background: #64748b; color: white;" title="Limpiar filtro">
                <i class="fa-solid fa-xmark"></i>
            </a>
        <?php endif; ?>
    </form>
</div>

<?= $mensaje ?>

<!-- Formulario Dinámico de Creación / Edición -->
<div class="card" style="margin-bottom: 30px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
        <i class="fa-solid <?= $modo_edicion ? 'fa-pen-to-square' : 'fa-circle-plus' ?>" style="margin-right: 6px;"></i> 
        <?= $modo_edicion ? 'Editar Insumo #' . $ins_a_editar['id'] : 'Agregar Nuevo Insumo' ?>
    </h3>
    
    <form method="POST" enctype="multipart/form-data" class="insumos-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <?php if ($modo_edicion): ?>
            <input type="hidden" name="id" value="<?= $ins_a_editar['id'] ?>">
            <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($ins_a_editar['imagen'] ?? '') ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Nombre del Insumo:</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($ins_a_editar['nombre']) ?>" required placeholder="Ej. Papa Blanca / Aceite" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>
        
        <div class="form-group">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Proveedor:</label>
            <input type="text" name="proveedor" value="<?= htmlspecialchars($ins_a_editar['proveedor']) ?>" placeholder="Ej. Mercado / Makro" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>
        
        <div class="form-group">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">¿Cómo compras este producto?:</label>
            <select name="tipo_unidad" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                <?php 
                $tipo_actual = 'KG';
                if ($modo_edicion) {
                    if ($ins_a_editar['unidad_medida'] == 'GRAMOS') {
                        $tipo_actual = ($ins_a_editar['cantidad_empaque'] >= 1000 && fmod($ins_a_editar['cantidad_empaque'], 1000) == 0) ? 'KG' : 'GRAMOS';
                    } elseif ($ins_a_editar['unidad_medida'] == 'MILILITRO') {
                        $tipo_actual = ($ins_a_editar['cantidad_empaque'] >= 1000 && fmod($ins_a_editar['cantidad_empaque'], 1000) == 0) ? 'LITRO' : 'MILILITRO';
                    } else {
                        $tipo_actual = 'UNIDAD';
                    }
                }
                $val_mostrar = $modo_edicion ? ($tipo_actual == 'KG' || $tipo_actual == 'LITRO' ? $ins_a_editar['cantidad_empaque'] / 1000 : $ins_a_editar['cantidad_empaque']) : '';
                ?>
                <option value="KG" <?= $tipo_actual == 'KG' ? 'selected' : '' ?>>Kilogramos (KG)</option>
                <option value="LITRO" <?= $tipo_actual == 'LITRO' ? 'selected' : '' ?>>Litros (LT)</option>
                <option value="GRAMOS" <?= $tipo_actual == 'GRAMOS' ? 'selected' : '' ?>>Gramos exactos (g)</option>
                <option value="MILILITRO" <?= $tipo_actual == 'MILILITRO' ? 'selected' : '' ?>>Mililitros exactos (ml)</option>
                <option value="UNIDAD" <?= $tipo_actual == 'UNIDAD' ? 'selected' : '' ?>>Unidad (Piezas / Paquetes)</option>
                <option value="ONZAS">Onzas (oz)</option>
            </select>
        </div>
        
        <div class="form-group">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Cantidad en el empaque:</label>
            <input type="number" step="0.0001" name="cantidad_empaque" value="<?= $val_mostrar ?>" required placeholder="Ej. 1" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>
        
        <div class="form-group">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Precio de Compra Total (S/):</label>
            <?php 
                // Al editar, si afecta IGV mostramos el precio original bruto (multiplicado por 1.18 aprox si ya estaba neto, o recuperando el valor original guardado)
                $precio_form_val = '';
                if ($modo_edicion) {
                    $precio_form_val = $ins_a_editar['afecto_igv'] ? ($ins_a_editar['precio_compra'] * 1.18) : $ins_a_editar['precio_compra'];
                }
            ?>
            <input type="number" step="0.0001" name="precio_compra" id="input-precio-compra" value="<?= $precio_form_val ?>" required placeholder="Ej. 5.00" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>

        <div class="form-group">
            <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Foto del Insumo:</label>
            <input type="file" name="imagen" accept="image/*" style="width: 100%; padding: 7px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
        </div>
        
        <div class="insumo-checkbox-container" style="grid-column: 1 / -1; display: flex; align-items: center; margin-top: 5px;">
            <label style="color: var(--label-color); font-weight: 500; cursor: pointer;">
                <input type="checkbox" name="afecto_igv" value="1" <?= ($modo_edicion ? $ins_a_editar['afecto_igv'] : 1) ? 'checked' : '' ?> style="margin-right: 5px;"> El precio incluye IGV (18%) — *Se descontará automáticamente para las recetas*
            </label>
        </div>
        
        <div style="grid-column: 1 / -1; margin-top: 10px;">
            <?php if ($modo_edicion): ?>
                <button type="submit" name="actualizar_insumo" class="btn btn-warning" style="padding: 10px 16px;">
                    <i class="fa-solid fa-floppy-disk"></i> Actualizar Insumo
                </button>
                <a href="index.php?seccion=insumos" class="btn btn-secondary" style="padding: 10px 16px; margin-left: 10px;">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </a>
            <?php else: ?>
                <button type="submit" name="guardar_insumo" class="btn btn-success" style="padding: 10px 16px;">
                    <i class="fa-solid fa-plus"></i> Guardar Insumo
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Formulario para el Borrado Masivo con Checkboxes -->
<form method="POST" id="form-masivo-insumos">
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <button type="submit" name="eliminar_seleccionados" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar todos los insumos seleccionados?');" style="padding: 8px 14px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-trash"></i> Eliminar Seleccionados
        </button>
        <span style="font-size: 12px; color: var(--label-color);">Total insumos: <strong><?= count($insumos) ?></strong></span>
    </div>

    <!-- Tabla de Insumos -->
    <div class="card" style="margin-bottom: 0; padding: 0; overflow-x: auto;">
        <table style="width: 100%; margin-top: 0;">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="seleccionar-todo" onclick="toggleSelectAll(this)" title="Seleccionar todo">
                    </th>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Empaque Registrado</th>
                    <th>Precio Compra (Factura)</th>
                    <th>Valor Neto (Sin IGV)</th>
                    <th>Proveedor</th>
                    <th>¿Incluye IGV?</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($insumos) > 0): ?>
                    <?php foreach ($insumos as $ins): ?>
                        <?php 
                            if ($ins['unidad_medida'] == 'GRAMOS' && $ins['cantidad_empaque'] >= 1000 && fmod($ins['cantidad_empaque'], 1000) == 0) {
                                $texto_empaque = (intval($ins['cantidad_empaque'] / 1000)) . " Kilogramo(s)";
                            } elseif ($ins['unidad_medida'] == 'MILILITRO' && $ins['cantidad_empaque'] >= 1000 && fmod($ins['cantidad_empaque'], 1000) == 0) {
                                $texto_empaque = (intval($ins['cantidad_empaque'] / 1000)) . " Litro(s)";
                            } else {
                                $cant_formateada = (fmod($ins['cantidad_empaque'], 1) == 0) ? intval($ins['cantidad_empaque']) : number_format($ins['cantidad_empaque'], 2);
                                $texto_empaque = $cant_formateada . " " . strtolower($ins['unidad_medida']);
                            }

                            // Precio bruto visual en tabla
                            $precio_factura = $ins['afecto_igv'] ? ($ins['precio_compra'] * 1.18) : $ins['precio_compra'];
                            $precio_neto = $ins['precio_compra'];
                        ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" name="ids[]" value="<?= $ins['id'] ?>" class="item-checkbox">
                            </td>
                            <td><?= $ins['id'] ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($ins['imagen']) && file_exists('uploads/' . $ins['imagen'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($ins['imagen']) ?>" alt="Insumo" style="width: 35px; height: 35px; border-radius: 6px; object-fit: cover;">
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($ins['nombre']) ?></strong>
                                </div>
                            </td>
                            <td><?= $texto_empaque ?></td>
                            <td>S/ <?= number_format($precio_factura, 2) ?></td>
                            <td style="color: var(--primary-color, #2563eb); font-weight: 600;">S/ <?= number_format($precio_neto, 2) ?></td>
                            <td><?= htmlspecialchars($ins['proveedor']) ?></td>
                            <td><?= $ins['afecto_igv'] ? '<span style="color: #16a34a; font-weight: 600;">Sí (18%)</span>' : 'No' ?></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="index.php?seccion=insumos&editar_ins=<?= $ins['id'] ?>" class="btn btn-warning" style="padding: 6px 10px; font-size: 12px;" title="Editar">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="index.php?seccion=insumos&eliminar_ins=<?= $ins['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este insumo?');" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--label-color); padding: 30px;">No hay insumos registrados todavía.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Script JavaScript para marcar/desmarcar todos los checkboxes de la tabla -->
<script>
function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach((cb) => {
        cb.checked = source.checked;
    });
}
</script>