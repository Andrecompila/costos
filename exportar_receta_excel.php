<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'conexion.php';

$id_receta = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. Consultar datos de la receta (incluyendo tipo_unidad y conservacion)
$stmt_rec = $conexion->prepare("SELECT r.*, c.nombre as categoria_nombre FROM recetas r LEFT JOIN categorias c ON r.categoria_id = c.id WHERE r.id = ?");
$stmt_rec->execute([$id_receta]);
$receta = $stmt_rec->fetch(PDO::FETCH_ASSOC);

if (!$receta) {
    exit("Receta no encontrada.");
}

// 2. Consultar detalle de ingredientes
$stmt_det = $conexion->prepare("
    SELECT rd.id as detalle_id, rd.cantidad, rd.observacion, i.nombre as insumo_nombre, i.unidad_medida, i.cantidad_empaque, i.precio_compra 
    FROM receta_detalle rd
    INNER JOIN insumos i ON rd.insumo_id = i.id
    WHERE rd.receta_id = ?
");
$stmt_det->execute([$id_receta]);
$detalles = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

// 3. Calcular Costos y Participación
$costo_total_receta = 0;
foreach ($detalles as &$det) {
    $costo_por_gramo_o_ml = $det['precio_compra'] / ($det['cantidad_empaque'] > 0 ? $det['cantidad_empaque'] : 1);
    $det['costo_subtotal'] = $costo_por_gramo_o_ml * $det['cantidad'];
    $costo_total_receta += $det['costo_subtotal'];

    if ($det['unidad_medida'] == 'GRAMOS' || $det['unidad_medida'] == 'MILILITRO' || $det['unidad_medida'] == 'ONZAS') {
        $det['costo_unitario_mostrar'] = $costo_por_gramo_o_ml * 1000; 
    } else {
        $det['costo_unitario_mostrar'] = $costo_por_gramo_o_ml;
    }
}
unset($det);

foreach ($detalles as &$det) {
    $det['participacion'] = ($costo_total_receta > 0) ? ($det['costo_subtotal'] / $costo_total_receta) * 100 : 0;
}
unset($det);

$costo_por_porcion = $receta['rendimiento'] > 0 ? ($costo_total_receta / $receta['rendimiento']) : $costo_total_receta;
$rendimiento_limpio = (fmod($receta['rendimiento'], 1) == 0) ? intval($receta['rendimiento']) : number_format($receta['rendimiento'], 2);

$tipo_unidad = !empty($receta['tipo_unidad']) ? $receta['tipo_unidad'] : 'Porción';
$conservacion = !empty($receta['conservacion']) ? $receta['conservacion'] : 'No aplica';

// Nombre del archivo Excel
$filename = "Ficha_" . preg_replace('/[^a-zA-Z0-9]/', '_', $receta['nombre']) . ".xls";

// Encabezados HTTP para forzar la descarga en Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<meta charset="UTF-8">
<style>
    table {
        width: 750px;
        border-collapse: collapse;
    }
    th, td {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 11pt;
    }
    .procedimiento-celda {
        white-space: pre-wrap;
        word-wrap: break-word;
        mso-data-placement: same-cell;
    }
</style>
<table border="0" style="width: 750px; border-collapse: collapse;">
    <tr>
        <th colspan="7" style="font-size: 14pt; font-weight: bold; background-color: #e2e8f0; color: #1e293b; text-align: left; padding: 10px;">
            <?= htmlspecialchars(strtoupper($receta['nombre'])) ?>
        </th>
    </tr>
    <tr><td colspan="7"></td></tr>

    <tr>
        <td style="font-weight: bold; background-color: #f1f5f9; border: 1px solid #cbd5e1; width: 160px;">Categoría</td>
        <td colspan="6" style="border: 1px solid #cbd5e1;"><?= htmlspecialchars($receta['categoria_nombre'] ?? 'Sin Categoría') ?></td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f1f5f9; border: 1px solid #cbd5e1;">Rendimiento</td>
        <td colspan="6" style="border: 1px solid #cbd5e1;"><?= $rendimiento_limpio ?> <?= htmlspecialchars($tipo_unidad) ?>(es)</td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f1f5f9; border: 1px solid #cbd5e1;">Tipo de Presentación</td>
        <td colspan="6" style="border: 1px solid #cbd5e1;"><?= htmlspecialchars($tipo_unidad) ?></td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f1f5f9; border: 1px solid #cbd5e1;">Conservación</td>
        <td colspan="6" style="border: 1px solid #cbd5e1;"><?= htmlspecialchars($conservacion) ?></td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f1f5f9; border: 1px solid #cbd5e1;">Costo Insumos</td>
        <td colspan="6" style="border: 1px solid #cbd5e1;">S/ <?= number_format($costo_total_receta, 2) ?></td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f1f5f9; border: 1px solid #cbd5e1;">Costo Unitario (<?= htmlspecialchars($tipo_unidad) ?>)</td>
        <td colspan="6" style="border: 1px solid #cbd5e1;">S/ <?= number_format($costo_por_porcion, 2) ?></td>
    </tr>
    <tr><td colspan="7"></td></tr>

    <tr>
        <th colspan="7" style="background-color: #38bdf8; color: white; font-weight: bold; text-align: center; border: 1px solid #0284c7;">
            Costo de Insumos
        </th>
    </tr>
    <tr style="background-color: #bae6fd; font-weight: bold; text-align: center;">
        <th style="border: 1px solid #7dd3fc; width: 180px;">Insumo</th>
        <th style="border: 1px solid #7dd3fc; width: 90px;">Unidad</th>
        <th style="border: 1px solid #7dd3fc; width: 90px;">Cantidad</th>
        <th style="border: 1px solid #7dd3fc; width: 140px;">Observación</th>
        <th style="border: 1px solid #7dd3fc; width: 85px;">Costo Unitario</th>
        <th style="border: 1px solid #7dd3fc; width: 85px;">Costo Total</th>
        <th style="border: 1px solid #7dd3fc; width: 80px;">Participación</th>
    </tr>

    <?php if (count($detalles) > 0): ?>
        <?php foreach ($detalles as $det): 
            if ($det['unidad_medida'] == 'GRAMOS') {
                $txt_unidad = 'GRAMOS';
                $txt_cant = (fmod($det['cantidad'], 1) == 0) ? intval($det['cantidad']) : number_format($det['cantidad'], 2);
            } elseif ($det['unidad_medida'] == 'MILILITRO') {
                $txt_unidad = 'MILILITRO';
                $txt_cant = (fmod($det['cantidad'], 1) == 0) ? intval($det['cantidad']) : number_format($det['cantidad'], 2);
            } else {
                $txt_unidad = 'PORCION';
                $txt_cant = (fmod($det['cantidad'], 1) == 0) ? intval($det['cantidad']) : number_format($det['cantidad'], 2);
            }
        ?>
        <tr>
            <td style="border: 1px solid #cbd5e1;"><?= htmlspecialchars($det['insumo_nombre']) ?></td>
            <td style="border: 1px solid #cbd5e1; text-align: center;"><?= $txt_unidad ?></td>
            <td style="border: 1px solid #cbd5e1; text-align: right;"><?= $txt_cant ?></td>
            <td style="border: 1px solid #cbd5e1;"><?= htmlspecialchars($det['observacion']) ?></td>
            <td style="border: 1px solid #cbd5e1; text-align: right;">S/ <?= number_format($det['costo_unitario_mostrar'], 2) ?></td>
            <td style="border: 1px solid #cbd5e1; text-align: right;">S/ <?= number_format($det['costo_subtotal'], 2) ?></td>
            <td style="border: 1px solid #cbd5e1; text-align: right;"><?= number_format($det['participacion'], 0) ?>%</td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" style="border: 1px solid #cbd5e1; text-align: center;">No hay insumos agregados en esta receta.</td>
        </tr>
    <?php endif; ?>

    <tr><td colspan="7"></td></tr>

    <tr>
        <th colspan="7" style="background-color: #38bdf8; color: white; font-weight: bold; text-align: center; border: 1px solid #0284c7;">
            PROCEDIMIENTO
        </th>
    </tr>
    <tr>
        <td colspan="7" class="procedimiento-celda" style="border: 1px solid #cbd5e1; padding: 10px; vertical-align: top; width: 750px;">
            <?= nl2br(htmlspecialchars($receta['preparacion'] ?? 'Sin procedimiento registrado.')) ?>
        </td>
    </tr>
</table>