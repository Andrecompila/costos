<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

$id_receta = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensaje = '';

// Procesar actualización rápida de rendimiento (porciones)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_rendimiento'])) {
    $nuevo_rendimiento = floatval($_POST['rendimiento']);
    if ($nuevo_rendimiento > 0) {
        $stmt_upd_rend = $conexion->prepare("UPDATE recetas SET rendimiento = ? WHERE id = ?");
        $stmt_upd_rend->execute([$nuevo_rendimiento, $id_receta]);
        $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Rendimiento actualizado correctamente!</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Ingresa un número de porciones válido.</div>";
    }
}

// Procesar actualización del Procedimiento / Preparación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_procedimiento'])) {
    $preparacion = trim($_POST['preparacion']);
    $stmt_proc = $conexion->prepare("UPDATE recetas SET preparacion = ? WHERE id = ?");
    $stmt_proc->execute([$preparacion, $id_receta]);
    $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Procedimiento actualizado correctamente!</div>";
}

// Procesar cuando se agrega un ingrediente a la receta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_ingrediente'])) {
    $insumo_id = intval($_POST['insumo_id']);
    $cantidad_input = floatval($_POST['cantidad']);
    $tipo_medida_usada = $_POST['tipo_medida_usada'];
    $observacion = trim($_POST['observacion']);

    if ($insumo_id > 0 && $cantidad_input > 0) {
        try {
            $cantidad_final = $cantidad_input;
            if ($tipo_medida_usada == 'KG' || $tipo_medida_usada == 'LITRO') {
                $cantidad_final = $cantidad_input * 1000;
            } elseif ($tipo_medida_usada == 'ONZAS') {
                $cantidad_final = $cantidad_input * 28.35; // Conversión de Onzas a Gramos
            }

            $stmt_ins = $conexion->prepare("INSERT INTO receta_detalle (receta_id, insumo_id, cantidad, observacion) VALUES (?, ?, ?, ?)");
            $stmt_ins->execute([$id_receta, $insumo_id, $cantidad_final, $observacion]);
            
            $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> ¡Ingrediente agregado correctamente!</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Error al agregar ingrediente.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> Selecciona un ingrediente y una cantidad válida.</div>";
    }
}

// Procesar cuando se elimina un ingrediente
if (isset($_GET['eliminar_det'])) {
    $id_det = intval($_GET['eliminar_det']);
    $stmt_del = $conexion->prepare("DELETE FROM receta_detalle WHERE id = ? AND receta_id = ?");
    $stmt_del->execute([$id_det, $id_receta]);
    $mensaje = "<div class='alert alert-success'><i class='fa-solid fa-circle-check'></i> Ingrediente retirado de la receta.</div>";
}

// Consultar datos de la receta
$stmt_rec = $conexion->prepare("SELECT r.*, c.nombre as categoria_nombre FROM recetas r LEFT JOIN categorias c ON r.categoria_id = c.id WHERE r.id = ?");
$stmt_rec->execute([$id_receta]);
$receta = $stmt_rec->fetch();

if (!$receta) {
    echo "<div class='receta-detalle-container'><div class='card' style='text-align: center; padding: 40px;'><h2>Receta no encontrada</h2><a href='index.php?seccion=recetas' class='btn btn-secondary' style='margin-top: 15px;'><i class='fa-solid fa-arrow-left'></i> Volver a recetas</a></div></div>";
    exit;
}

// Consultar insumos disponibles para el selector
$insumos_db = $conexion->query("SELECT * FROM insumos ORDER BY nombre ASC")->fetchAll();

// Consultar detalle de ingredientes
$stmt_det = $conexion->prepare("
    SELECT rd.id as detalle_id, rd.cantidad, rd.observacion, i.nombre as insumo_nombre, i.unidad_medida, i.cantidad_empaque, i.precio_compra 
    FROM receta_detalle rd
    INNER JOIN insumos i ON rd.insumo_id = i.id
    WHERE rd.receta_id = ?
");
$stmt_det->execute([$id_receta]);
$detalles = $stmt_det->fetchAll();

// ==========================================
// 1. CALCULO DE COSTOS Y UNIDADES DE DETALLE
// ==========================================
$costo_total_receta = 0;

foreach ($detalles as &$det) {
    $costo_por_unidad_base = ($det['cantidad_empaque'] > 0) ? ($det['precio_compra'] / $det['cantidad_empaque']) : 0;
    
    $det['costo_subtotal'] = $costo_por_unidad_base * $det['cantidad'];
    $costo_total_receta += $det['costo_subtotal'];

    if ($det['unidad_medida'] == 'GRAMOS' || $det['unidad_medida'] == 'MILILITRO' || $det['unidad_medida'] == 'ONZAS') {
        $det['costo_unitario_mostrar'] = $costo_por_unidad_base * 1000; 
    } else {
        $det['costo_unitario_mostrar'] = $costo_por_unidad_base;
    }
}
unset($det);

// ==========================================
// 2. CALCULO DE PARTICIPACIÓN (Con 1 decimal exacto)
// ==========================================
$porcentaje_acumulado = 0;
$total_detalles = count($detalles);
$contador = 0;

foreach ($detalles as &$det) {
    $contador++;
    if ($costo_total_receta > 0) {
        $participacion_exacta = ($det['costo_subtotal'] / $costo_total_receta) * 100;
        
        if ($contador == $total_detalles) {
            $det['participacion'] = 100 - $porcentaje_acumulado;
        } else {
            $det['participacion'] = round($participacion_exacta, 1);
            $porcentaje_acumulado += $det['participacion'];
        }
    } else {
        $det['participacion'] = 0;
    }
}
unset($det);

$costo_por_porcion = $receta['rendimiento'] > 0 ? ($costo_total_receta / $receta['rendimiento']) : $costo_total_receta;

// ==========================================
// 3. MODELO FINANCIERO SECUENCIAL (Excel)
// ==========================================
$costo_alimentos = $costo_por_porcion;
$valor_venta = ($costo_alimentos > 0) ? ($costo_alimentos / 0.45) : 0;

$mano_obra = $valor_venta * 0.18;         // 18%
$gastos_operativos = $valor_venta * 0.18;   // 18%
$utilidad_neta = $valor_venta * 0.19;       // 19%

$descartables = floatval($receta['descartables'] ?? 0); 
$rac = $valor_venta * 0.10;                 // 10% RAC
$igv = $valor_venta * 0.10;                 // 10% IGV

$precio_sugerido = $valor_venta + $descartables + $rac + $igv;
$utilidad_por_porcion = $utilidad_neta;

$rendimiento_limpio = (fmod($receta['rendimiento'], 1) == 0) ? intval($receta['rendimiento']) : number_format($receta['rendimiento'], 2);
?>

<div class="receta-detalle-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0 0 5px 0;"><i class="fa-solid fa-book-open" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> Receta: <?= htmlspecialchars($receta['nombre']) ?></h2>
            <p style="color: var(--label-color); margin: 0;"><strong>Categoría:</strong> <?= htmlspecialchars($receta['categoria_nombre'] ?? 'Sin Categoría') ?></p>
        </div>
        <!-- Botones de Exportación (Excel y PDF) -->
        <div style="display: flex; gap: 10px;">
            <a href="exportar_receta_excel.php?id=<?= $id_receta ?>" class="btn btn-success" style="padding: 10px 16px;">
                <i class="fa-solid fa-file-excel"></i> Descargar Excel
            </a>
            <a href="exportar_receta_pdf.php?id=<?= $id_receta ?>" target="_blank" class="btn btn-warning" style="background-color: #d97706; color: white; padding: 10px 16px;" title="Imprimir PDF">
                <i class="fa-solid fa-file-pdf"></i> Imprimir / Ver PDF
            </a>
        </div>
    </div>

    <?php if (!empty($receta['imagen']) && file_exists('uploads/' . $receta['imagen'])): ?>
        <div style="margin-bottom: 20px;">
            <img src="uploads/<?= htmlspecialchars($receta['imagen']) ?>" alt="Foto del plato" style="max-width: 100%; height: auto; max-height: 350px; border-radius: 8px; display: block; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        </div>
    <?php endif; ?>

    <?= $mensaje ?>

    <?php 
        $val_rendimiento_input = (fmod($receta['rendimiento'], 1) == 0) ? intval($receta['rendimiento']) : $receta['rendimiento'];
    ?>

    <!-- Formulario rápido para cambiar porciones -->
    <div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div style="font-size: 15px; font-weight: 500; color: var(--text-heading);">
            <i class="fa-solid fa-chart-pie" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> Rendimiento Actual: <strong style="color: var(--primary-color, #2563eb);"><?= $rendimiento_limpio ?></strong> porción(es)
        </div>
        <form method="POST" style="display: flex; align-items: center; gap: 10px; margin: 0; flex-wrap: wrap;">
            <label style="font-size: 14px; font-weight: 500; color: var(--label-color);">Cambiar a:</label>
            <input type="number" step="any" name="rendimiento" value="<?= $val_rendimiento_input ?>" required style="padding: 8px 12px; width: 90px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a); text-align: center; font-weight: 500;">
            <button type="submit" name="actualizar_rendimiento" class="btn btn-warning" style="padding: 8px 14px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </form>
    </div>

    <!-- Secuencia Financiera Unificada -->
    <div class="card" style="margin-bottom: 30px; padding: 25px; background: var(--card-bg, #fff); border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid var(--border-color, #e2e8f0); padding-bottom: 12px;">
            <h3 style="margin: 0; font-size: 16px; color: var(--text-heading); font-weight: 700;">
                <i class="fa-solid fa-chart-line" style="color: #2563eb; margin-right: 8px;"></i> Secuencia de Costos y Precios 
            </h3>
            <span style="font-size: 12px; color: #16a34a; font-weight: 600; background: #16a34a15; padding: 4px 10px; border-radius: 20px;">Sincronizado al 100%</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(115px, 1fr)); gap: 10px; text-align: center;">
            <div style="background: #f8fafc; padding: 12px 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 9px; font-weight: 700; color: #64748b; display: block; margin-bottom: 6px;">COSTO ALIMENTOS</span>
                <strong style="font-size: 13px; color: #2563eb;">S/ <?= number_format($costo_alimentos, 2) ?></strong>
            </div>

            <div style="background: #f8fafc; padding: 12px 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 9px; font-weight: 700; color: #64748b; display: block; margin-bottom: 6px;">MANO OBRA (18%)</span>
                <strong style="font-size: 13px; color: #0f172a;">S/ <?= number_format($mano_obra, 2) ?></strong>
            </div>

            <div style="background: #f8fafc; padding: 12px 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 9px; font-weight: 700; color: #64748b; display: block; margin-bottom: 6px;">GASTOS OP. (18%)</span>
                <strong style="font-size: 13px; color: #0f172a;">S/ <?= number_format($gastos_operativos, 2) ?></strong>
            </div>

            <div style="background: #f8fafc; padding: 12px 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 9px; font-weight: 700; color: #9333ea; display: block; margin-bottom: 6px;">UTILIDAD (19%)</span>
                <strong style="font-size: 13px; color: #9333ea;">S/ <?= number_format($utilidad_neta, 2) ?></strong>
            </div>

            <div style="background: #eff6ff; padding: 12px 6px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <span style="font-size: 9px; font-weight: 700; color: #1e40af; display: block; margin-bottom: 6px;">VALOR VENTA (100%)</span>
                <strong style="font-size: 13px; color: #1e40af;">S/ <?= number_format($valor_venta, 2) ?></strong>
            </div>

            <div style="background: #f8fafc; padding: 12px 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 9px; font-weight: 700; color: #64748b; display: block; margin-bottom: 6px;">DESCARTABLES</span>
                <strong style="font-size: 13px; color: #0f172a;">S/ <?= number_format($descartables, 2) ?></strong>
            </div>

            <div style="background: #f8fafc; padding: 12px 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 9px; font-weight: 700; color: #64748b; display: block; margin-bottom: 6px;">RAC (10%)</span>
                <strong style="font-size: 13px; color: #0f172a;">S/ <?= number_format($rac, 2) ?></strong>
            </div>

            <div style="background: #f8fafc; padding: 12px 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 9px; font-weight: 700; color: #64748b; display: block; margin-bottom: 6px;">IGV (10%)</span>
                <strong style="font-size: 13px; color: #0f172a;">S/ <?= number_format($igv, 2) ?></strong>
            </div>

            <div style="background: #f0fdf4; padding: 12px 6px; border-radius: 8px; border: 2px solid #22c55e;">
                <span style="font-size: 9px; font-weight: 800; color: #15803d; display: block; margin-bottom: 6px;">PRECIO SUGERIDO</span>
                <strong style="font-size: 15px; color: #16a34a; font-weight: 800;">S/ <?= number_format($precio_sugerido, 2) ?></strong>
            </div>
        </div>
    </div>

    <!-- Formulario para Agregar Ingrediente con Buscador en Vivo -->
    <div class="card" style="margin-bottom: 30px;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
            <i class="fa-solid fa-circle-plus" style="margin-right: 6px;"></i> Agregar Ingrediente a la Receta
        </h3>
        <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: flex-end;">
            
            <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Buscar y Seleccionar Insumo:</label>
                <input type="text" id="buscador-insumos" placeholder="🔍 Escribe para filtrar insumos..." style="width: 100%; padding: 9px 12px; margin-bottom: 6px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a); font-size: 13px;" onkeyup="filtrarInsumos()">
                
                <select name="insumo_id" id="select-insumos" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                    <option value="">-- Elige un insumo --</option>
                    <?php foreach ($insumos_db as $ins): ?>
                        <option value="<?= $ins['id'] ?>"><?= htmlspecialchars($ins['nombre']) ?> (<?= htmlspecialchars($ins['unidad_medida']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Cantidad:</label>
                <input type="number" step="any" name="cantidad" required placeholder="Ej. 250" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Unidad de Medida:</label>
                <select name="tipo_medida_usada" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
                    <option value="GRAMOS">Gramos (g)</option>
                    <option value="MILILITRO">Mililitros (ml)</option>
                    <option value="KG">Kilogramos (kg)</option>
                    <option value="LITRO">Litros (lt)</option>
                    <option value="ONZAS">Onzas (oz)</option>
                    <option value="UNIDAD">Unidad / Pieza</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                <label style="display: block; margin-bottom: 5px; color: var(--label-color); font-weight: 500;">Observación (Opcional):</label>
                <input type="text" name="observacion" placeholder="Ej. Picado en cubos, sin pepa..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);">
            </div>

            <div style="margin-bottom: 0;">
                <button type="submit" name="agregar_ingrediente" class="btn btn-success" style="padding: 10px 16px; width: 100%;">
                    <i class="fa-solid fa-plus"></i> Añadir Ingrediente
                </button>
            </div>
        </form>
    </div>

    <!-- Script de filtrado en vivo para el buscador de insumos -->
    <script>
    function filtrarInsumos() {
        let input = document.getElementById('buscador-insumos').value.toLowerCase();
        let select = document.getElementById('select-insumos');
        let options = select.options;

        for (let i = 1; i < options.length; i++) {
            let txt = options[i].text.toLowerCase();
            if (txt.includes(input)) {
                options[i].style.display = "";
            } else {
                options[i].style.display = "none";
            }
        }
    }
    </script>

    <!-- Tabla de Insumos Agregados -->
    <div class="card" style="margin-bottom: 30px;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
            <i class="fa-solid fa-list-check" style="margin-right: 6px;"></i> Ingredientes de la Receta
        </h3>
        <table style="width: 100%; margin-top: 0;">
            <thead>
                <tr>
                    <th>Insumo</th>
                    <th>Unidad</th>
                    <th>Cantidad</th>
                    <th>Observación</th>
                    <th>Costo Unitario</th>
                    <th>Costo Total</th>
                    <th>Participación</th>
                    <th style="text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($detalles) > 0): ?>
                    <?php foreach ($detalles as $det): ?>
                        <?php 
                            $txt_unidad = $det['unidad_medida'];
                            $txt_cant = (fmod($det['cantidad'], 1) == 0) ? intval($det['cantidad']) : number_format($det['cantidad'], 2);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($det['insumo_nombre']) ?></strong></td>
                            <td><?= $txt_unidad ?></td>
                            <td><?= $txt_cant ?></td>
                            <td><?= htmlspecialchars($det['observacion']) ?></td>
                            <td>S/ <?= number_format($det['costo_unitario_mostrar'], 2) ?></td>
                            <td>S/ <?= number_format($det['costo_subtotal'], 2) ?></td>
                            <td><strong><?= number_format($det['participacion'], 1) ?>%</strong></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="index.php?seccion=recetas_ver&id=<?= $id_receta ?>&eliminar_det=<?= $det['detalle_id'] ?>" onclick="return confirm('¿Quitar este ingrediente?');" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;" title="Quitar">
                                    <i class="fa-solid fa-trash"></i> Quitar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--label-color); padding: 30px;">Aún no hay ingredientes agregados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Apartado de Procedimiento / Preparación -->
    <div class="card" style="margin-top: 0; margin-bottom: 25px;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
            <i class="fa-solid fa-list-check" style="margin-right: 6px;"></i> Procedimiento de Preparación
        </h3>
        <form method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <textarea name="preparacion" rows="5" placeholder="Escribe paso a paso las instrucciones de preparación..." style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, #fff); color: var(--text-color, #0f172a);"><?= htmlspecialchars($receta['preparacion'] ?? '') ?></textarea>
            </div>
            <button type="submit" name="actualizar_procedimiento" class="btn btn-success" style="padding: 10px 16px;">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Procedimiento
            </button>
        </form>
    </div>

    <div>
        <a href="index.php?seccion=recetas" class="btn btn-secondary" style="padding: 10px 16px;">
            <i class="fa-solid fa-arrow-left"></i> Volver a la Lista de Recetas
        </a>
    </div>
</div>