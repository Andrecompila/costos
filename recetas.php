<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

$mensaje = '';

// Procesar Eliminación Masiva de Recetas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_seleccionados'])) {
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = $_POST['ids'];
        try {
            $conexion->beginTransaction();
            
            // Creamos placeholders dinámicos (?, ?, ?) por seguridad
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // 1. Eliminar los detalles de todas las recetas seleccionadas
            $stmt_det = $conexion->prepare("DELETE FROM receta_detalle WHERE receta_id IN ($placeholders)");
            $stmt_det->execute($ids);
            
            // 2. Eliminar las recetas seleccionadas
            $stmt_rec = $conexion->prepare("DELETE FROM recetas WHERE id IN ($placeholders)");
            $stmt_rec->execute($ids);
            
            $conexion->commit();
            $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Las recetas seleccionadas fueron eliminadas correctamente!</div>";
        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error al eliminar las recetas seleccionadas.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> No seleccionaste ninguna receta para eliminar.</div>";
    }
}

// Procesar Eliminación Individual (Por si acaso se mantiene el enlace directo)
if (isset($_GET['eliminar_receta'])) {
    $id_eliminar = intval($_GET['eliminar_receta']);
    try {
        $conexion->beginTransaction();
        $stmt = $conexion->prepare("DELETE FROM receta_detalle WHERE receta_id = ?");
        $stmt->execute([$id_eliminar]);
        
        $stmt2 = $conexion->prepare("DELETE FROM recetas WHERE id = ?");
        $stmt2->execute([$id_eliminar]);
        
        $conexion->commit();
        $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Receta eliminada correctamente!</div>";
    } catch (Exception $e) {
        $conexion->rollBack();
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error al eliminar la receta.</div>";
    }
}

// Procesar Búsqueda y Consultar las recetas registradas
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

if (!empty($busqueda)) {
    $stmt = $conexion->prepare("SELECT r.*, c.nombre as categoria_nombre FROM recetas r LEFT JOIN categorias c ON r.categoria_id = c.id WHERE r.nombre LIKE ? OR c.nombre LIKE ? ORDER BY r.nombre ASC");
    $stmt->execute(['%' . $busqueda . '%', '%' . $busqueda . '%']);
} else {
    $stmt = $conexion->query("SELECT r.*, c.nombre as categoria_nombre FROM recetas r LEFT JOIN categorias c ON r.categoria_id = c.id ORDER BY r.nombre ASC");
}
$recetas = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0 0 5px 0;"><i class="fa-solid fa-book-open" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> Gestión de Recetas y Platos</h2>
        <p style="color: var(--label-color); margin: 0;">Crea tus platos, asocia sus ingredientes y calcula sus costos de producción automáticamente.</p>
    </div>
    
    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <!-- Formulario de Buscador -->
        <form method="GET" action="index.php" style="display: flex; gap: 8px; margin: 0;">
            <input type="hidden" name="seccion" value="recetas">
            <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar plato o categoría..." style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a); width: 200px; font-size: 13px;">
            <button type="submit" class="btn btn-secondary" style="padding: 8px 12px; font-size: 13px;" title="Buscar">
                <i class="fa-solid fa-search"></i>
            </button>
            <?php if (!empty($busqueda)): ?>
                <a href="index.php?seccion=recetas" class="btn btn-secondary" style="padding: 8px 10px; font-size: 13px; background: #64748b; color: white;" title="Limpiar filtro">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </form>
        
        <!-- Botón para ver el Reporte Maestro General en PDF directamente -->
        <div>
            <a href="exportar_reporte_general_pdf.php" target="_blank" class="btn btn-warning" style="padding: 10px 16px; background-color: #d97706; color: white;" title="Generar Reporte Maestro General PDF">
                <i class="fa-solid fa-file-pdf"></i> Reporte General PDF
            </a>
        </div>

        <div>
            <a href="index.php?seccion=recetas_nuevo" class="btn btn-success" style="padding: 10px 16px;">
                <i class="fa-solid fa-plus"></i> Crear Nueva Receta
            </a>
        </div>
    </div>
</div>

<?= $mensaje ?>

<!-- Formulario único para el borrado masivo por checkboxes -->
<form method="POST" id="form-masivo-recetas">
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <button type="submit" name="eliminar_seleccionados" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar todas las recetas seleccionadas?');" style="padding: 8px 14px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-trash"></i> Eliminar Seleccionadas
        </button>
        <span style="font-size: 12px; color: var(--label-color);">Total registradas: <strong><?= count($recetas) ?></strong></span>
    </div>

    <!-- Tabla de Recetas -->
    <div class="card" style="margin-bottom: 0; padding: 0; overflow-x: auto;">
        <table style="width: 100%; margin-top: 0;">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="seleccionar-todo" onclick="toggleSelectAll(this)" title="Seleccionar todo">
                    </th>
                    <th>ID</th>
                    <th>Imagen</th>
                    <th>Nombre del Plato</th>
                    <th>Categoría</th>
                    <th>Rendimiento (Porciones)</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recetas) > 0): ?>
                    <?php foreach ($recetas as $rec): ?>
                        <?php 
                            $rendimiento_limpio = (fmod($rec['rendimiento'], 1) == 0) ? intval($rec['rendimiento']) : number_format($rec['rendimiento'], 2);
                        ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" name="ids[]" value="<?= $rec['id'] ?>" class="item-checkbox">
                            </td>
                            <td><?= $rec['id'] ?></td>
                            <td>
                                <?php if (!empty($rec['imagen']) && file_exists('uploads/' . $rec['imagen'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($rec['imagen']) ?>" alt="Plato" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover;">
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--label-color);">Sin foto</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($rec['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($rec['categoria_nombre'] ?? 'Sin Categoría') ?></td>
                            <td><?= $rendimiento_limpio ?> porción(es)</td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="index.php?seccion=recetas_ver&id=<?= $rec['id'] ?>" class="btn btn-warning" style="padding: 6px 10px; font-size: 12px;" title="Ver Detalle">
                                    <i class="fa-solid fa-eye"></i> Ver
                                </a>
                                <a href="exportar_receta_pdf.php?id=<?= $rec['id'] ?>" target="_blank" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px; background-color: #dc2626;" title="Imprimir PDF">
                                    <i class="fa-solid fa-file-pdf"></i> PDF
                                </a>
                                <a href="exportar_receta_excel.php?id=<?= $rec['id'] ?>" class="btn btn-success" style="padding: 6px 10px; font-size: 12px;" title="Descargar Excel">
                                    <i class="fa-solid fa-file-excel"></i> Excel
                                </a>
                                <a href="index.php?seccion=recetas_nuevo&id=<?= $rec['id'] ?>" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" title="Editar">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="index.php?seccion=recetas&eliminar_receta=<?= $rec['id'] ?>" onclick="return confirm('¿Estás seguro de eliminar esta receta?');" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--label-color); padding: 30px;">
                            <?= !empty($busqueda) ? 'No se encontraron recetas con ese criterio de búsqueda.' : 'No hay recetas registradas todavía.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Script de JavaScript para la selección masiva -->
<script>
function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach((cb) => {
        cb.checked = source.checked;
    });
}
</script>