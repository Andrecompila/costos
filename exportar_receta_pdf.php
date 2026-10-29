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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha Técnica - <?= htmlspecialchars($receta['nombre']) ?></title>
    <!-- Enlace de Font Awesome para los íconos modernos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20px; box-sizing: border-box; }
        h1 { font-size: 20px; color: #1e293b; border-bottom: 2px solid #38bdf8; padding-bottom: 8px; margin-bottom: 15px; }
        
        /* Contenedor flex para poner la tabla de datos a la izquierda y la foto a la derecha */
        .header-container { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 20px; }
        .info-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .info-table td { padding: 6px 10px; border: 1px solid #cbd5e1; }
        .info-table td:first-child { font-weight: bold; background-color: #f1f5f9; width: 40%; }
        
        /* Estilo de la imagen de la receta */
        .recipe-img { width: 190px; height: 130px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
        .data-table th, .data-table td { padding: 8px 10px; border: 1px solid #cbd5e1; text-align: left; }
        .data-table th { background-color: #38bdf8; color: white; font-size: 12px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section-title { background-color: #0284c7; color: white; padding: 6px 10px; font-weight: bold; font-size: 13px; margin-top: 20px; margin-bottom: 0; }
        
        /* Caja de procedimiento ajustada perfectamente al cuadro */
        .procedimiento-box { 
            border: 1px solid #cbd5e1; 
            padding: 12px; 
            background: #f8fafc; 
            font-size: 13px; 
            line-height: 1.5; 
            min-height: 60px; 
            width: 100%;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: normal;
        }
        
        /* Botones de acción modernos ocultos al imprimir */
        .no-print { margin-bottom: 25px; display: flex; gap: 12px; align-items: center; }

        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <!-- Botones de control modernos -->
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13.5px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;" onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
            <i class="fa-solid fa-print"></i> Imprimir / Guardar como PDF
        </button>
        <a href="index.php?seccion=recetas_ver&id=<?= $id_receta ?>" style="padding: 10px 16px; background: #64748b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13.5px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;" onmouseover="this.style.background='#475569'" onmouseout="this.style.background='#64748b'">
            <i class="fa-solid fa-arrow-left"></i> Volver al Sistema
        </a>
    </div>

    <h1>FICHA TÉCNICA: <?= htmlspecialchars(strtoupper($receta['nombre'])) ?></h1>

    <!-- Sección Superior: Tabla de Datos + Foto del Plato -->
    <div class="header-container">
        <div style="flex: 1;">
            <table class="info-table">
                <tr>
                    <td>Categoría</td>
                    <td><?= htmlspecialchars($receta['categoria_nombre'] ?? 'Sin Categoría') ?></td>
                </tr>
                <tr>
                    <td>Rendimiento</td>
                    <td><?= $rendimiento_limpio ?> <?= htmlspecialchars($tipo_unidad) ?>(es)</td>
                </tr>
                <tr>
                    <td>Tipo de Presentación</td>
                    <td><?= htmlspecialchars($tipo_unidad) ?></td>
                </tr>
                <tr>
                    <td>Conservación</td>
                    <td><?= htmlspecialchars($conservacion) ?></td>
                </tr>
                <tr>
                    <td>Costo Total Insumos</td>
                    <td>S/ <?= number_format($costo_total_receta, 2) ?></td>
                </tr>
                <tr>
                    <td>Costo Unitario (<?= htmlspecialchars($tipo_unidad) ?>)</td>
                    <td><strong>S/ <?= number_format($costo_por_porcion, 2) ?></strong></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($receta['imagen']) && file_exists('uploads/' . $receta['imagen'])): ?>
            <div>
                <img src="uploads/<?= htmlspecialchars($receta['imagen']) ?>" alt="Foto del Plato" class="recipe-img">
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabla de Insumos -->
    <div class="section-title">COSTO DE INSUMOS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Insumo</th>
                <th class="text-center">Unidad</th>
                <th class="text-right">Cantidad</th>
                <th>Observación</th>
                <th class="text-right">Costo Unitario</th>
                <th class="text-right">Costo Total</th>
                <th class="text-center">Participación</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($detalles) > 0): ?>
                <?php foreach ($detalles as $det): 
                    $txt_cant = (fmod($det['cantidad'], 1) == 0) ? intval($det['cantidad']) : number_format($det['cantidad'], 2);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($det['insumo_nombre']) ?></strong></td>
                    <td class="text-center"><?= htmlspecialchars($det['unidad_medida']) ?></td>
                    <td class="text-right"><?= $txt_cant ?></td>
                    <td><?= htmlspecialchars($det['observacion']) ?></td>
                    <td class="text-right">S/ <?= number_format($det['costo_unitario_mostrar'], 2) ?></td>
                    <td class="text-right">S/ <?= number_format($det['costo_subtotal'], 2) ?></td>
                    <td class="text-center"><strong><?= number_format($det['participacion'], 0) ?>%</strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No hay insumos agregados en esta receta.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Procedimiento -->
    <div class="section-title">PROCEDIMIENTO DE PREPARACIÓN</div>
    <div class="procedimiento-box">
        <?= nl2br(htmlspecialchars($receta['preparacion'] ?? 'Sin procedimiento registrado.')) ?>
    </div>

</body>
</html>