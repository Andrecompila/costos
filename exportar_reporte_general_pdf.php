<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

// Consultar todas las recetas con sus categorías
$stmt = $conexion->query("
    SELECT r.*, c.nombre as categoria_nombre 
    FROM recetas r 
    LEFT JOIN categorias c ON r.categoria_id = c.id 
    ORDER BY c.nombre ASC, r.nombre ASC
");
$recetas_master = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Maestro de Costos y Precios</title>
    <style>
        @media print {
            @page { size: landscape; margin: 6mm; }
            body { font-family: Arial, sans-serif; font-size: 8.5px; color: #0f172a; -webkit-print-color-adjust: exact; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #cbd5e1; padding: 4px; text-align: left; }
            th { background-color: #f8fafc !important; font-weight: bold; }
            .no-print { display: none !important; } /* Oculta el botón al imprimir */
        }
        body { font-family: Arial, sans-serif; font-size: 10px; padding: 20px; color: #0f172a; background: #f8fafc; }
        .page-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 1400px; margin: 0 auto; }
        .header-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; }
        th { background: #f8fafc; text-align: left; font-size: 9.5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-green { background: #f0fdf4 !important; color: #16a34a; font-weight: bold; }
        
        /* Estilo del botón interactivo */
        .btn-print {
            background-color: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover { background-color: #1d4ed8; }
    </style>
</head>
<body>

    <!-- Botón visible en pantalla que desaparece al imprimir -->
    <div class="no-print" style="max-width: 1400px; margin: 0 auto 15px auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="index.php?seccion=recetas" style="text-decoration: none; color: #475569; font-weight: 500; font-size: 13px;">
            ← Volver al Sistema
        </a>
        <button onclick="window.print();" class="btn-print">
            🖨️ Imprimir / Guardar como PDF
        </button>
    </div>

    <div class="page-container">
        <div class="header-container">
            <div>
                <h2 style="margin: 0 0 3px 0; font-size: 16px;">Resumen Maestro de Costos y Precios</h2>
                <p style="margin: 0; color: #64748b; font-size: 11px;">Consolidado general de recetas sincronizado al modelo financiero exacto.</p>
            </div>
            <div style="font-size: 10px; color: #64748b; text-align: right;">
                <strong>Fecha:</strong> <?= date('d/m/Y') ?><br>
                <strong>Total Platos:</strong> <?= count($recetas_master) ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="text-center">Rend.</th>
                    <th class="text-center">Unidad</th>
                    <th class="text-right">Costo Alimento</th>
                    <th class="text-right">Mano Obra (18%)</th>
                    <th class="text-right">Gastos Op. (18%)</th>
                    <th class="text-right">Utilidad (19%)</th>
                    <th class="text-right">Valor Venta</th>
                    <th class="text-right">Descartables</th>
                    <th class="text-right">RAC (10%)</th>
                    <th class="text-right">IGV (10%)</th>
                    <th class="text-right">Precio Sugerido</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recetas_master) > 0): ?>
                    <?php foreach ($recetas_master as $rec): 
                        // Calcular costo total de ingredientes para esta receta
                        $stmt_det = $conexion->prepare("
                            SELECT rd.cantidad, i.cantidad_empaque, i.precio_compra 
                            FROM receta_detalle rd 
                            INNER JOIN insumos i ON rd.insumo_id = i.id 
                            WHERE rd.receta_id = ?
                        ");
                        $stmt_det->execute([$rec['id']]);
                        $detalles = $stmt_det->fetchAll();

                        $costo_receta_acumulado = 0;
                        foreach ($detalles as $d) {
                            $cp = ($d['cantidad_empaque'] > 0) ? ($d['precio_compra'] / $d['cantidad_empaque']) : 0;
                            $costo_receta_acumulado += ($cp * $d['cantidad']);
                        }
                        
                        $costo_alimentos = ($rec['rendimiento'] > 0) ? ($costo_receta_acumulado / $rec['rendimiento']) : $costo_receta_acumulado;

                        // Secuencia financiera exacta
                        $valor_venta = ($costo_alimentos > 0) ? ($costo_alimentos / 0.45) : 0;
                        $mano_obra = $valor_venta * 0.18;
                        $gastos_op = $valor_venta * 0.18;
                        $utilidad = $valor_venta * 0.19;
                        $descartables = floatval($rec['descartables'] ?? 0);
                        $rac = $valor_venta * 0.10;
                        $igv = $valor_venta * 0.10;
                        $precio_sugerido = $valor_venta + $descartables + $rac + $igv;

                        $rend_limpio = (fmod($rec['rendimiento'], 1) == 0) ? intval($rec['rendimiento']) : $rec['rendimiento'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($rec['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($rec['categoria_nombre'] ?? 'Sin Categoría') ?></td>
                        <td class="text-center"><?= $rend_limpio ?></td>
                        <td class="text-center"><?= htmlspecialchars($rec['tipo_unidad']) ?></td>
                        <td class="text-right" style="color: #2563eb;">S/ <?= number_format($costo_alimentos, 2) ?></td>
                        <td class="text-right">S/ <?= number_format($mano_obra, 2) ?></td>
                        <td class="text-right">S/ <?= number_format($gastos_op, 2) ?></td>
                        <td class="text-right" style="color: #9333ea;">S/ <?= number_format($utilidad, 2) ?></td>
                        <td class="text-right" style="font-weight: bold; background: #eff6ff;">S/ <?= number_format($valor_venta, 2) ?></td>
                        <td class="text-right"><?= $descartables > 0 ? 'S/ ' . number_format($descartables, 2) : '-' ?></td>
                        <td class="text-right">S/ <?= number_format($rac, 2) ?></td>
                        <td class="text-right">S/ <?= number_format($igv, 2) ?></td>
                        <td class="text-right badge-green">S/ <?= number_format($precio_sugerido, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" class="text-center" style="padding: 20px; color: #64748b;">No hay recetas registradas todavía.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>