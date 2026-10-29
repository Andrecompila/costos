<?php
if (!isset($conexion)) {
    require_once 'conexion.php';
}

// 1. Estadísticas generales
$total_recetas = $conexion->query("SELECT COUNT(*) FROM recetas")->fetchColumn();
$total_insumos = $conexion->query("SELECT COUNT(*) FROM insumos")->fetchColumn();
$total_categorias = $conexion->query("SELECT COUNT(*) FROM categorias")->fetchColumn();

// 2. Datos para el gráfico: Cantidad de recetas por categoría
$stmt_cat = $conexion->query("SELECT c.nombre, COUNT(r.id) as total FROM categorias c LEFT JOIN recetas r ON c.id = r.categoria_id GROUP BY c.id, c.nombre");
$datos_categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

$nombres_categorias = [];
$totales_categorias = [];
foreach ($datos_categorias as $cat) {
    $nombres_categorias[] = $cat['nombre'];
    $totales_categorias[] = $cat['total'];
}

// 3. Últimas 5 recetas
$ultimas_recetas = $conexion->query("SELECT r.*, c.nombre as categoria_nombre FROM recetas r LEFT JOIN categorias c ON r.categoria_id = c.id ORDER BY r.id DESC LIMIT 5")->fetchAll();
?>

<!-- Importar Chart.js desde CDN para los gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0 0 5px 0;"><i class="fa-solid fa-chart-pie" style="color: var(--primary-color, #2563eb); margin-right: 8px;"></i> Panel de Control y Costos</h2>
        <p style="color: var(--label-color); margin: 0;">Monitoreo en tiempo real de tu inventario, platos y categorías.</p>
    </div>
    <div>
        <a href="index.php?seccion=recetas_nuevo" class="btn btn-success"><i class="fa-solid fa-plus"></i> Nueva Receta</a>
    </div>
</div>

<!-- Tarjetas KPI -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="margin-bottom: 0; text-align: center;">
        <p style="color: var(--label-color); margin: 0 0 10px 0; font-weight: 500;">Total Recetas</p>
        <h3 style="font-size: 32px; color: #2563eb; margin: 0;"><?= $total_recetas ?></h3>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center;">
        <p style="color: var(--label-color); margin: 0 0 10px 0; font-weight: 500;">Insumos Registrados</p>
        <h3 style="font-size: 32px; color: #16a34a; margin: 0;"><?= $total_insumos ?></h3>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center;">
        <p style="color: var(--label-color); margin: 0 0 10px 0; font-weight: 500;">Categorías Activas</p>
        <h3 style="font-size: 32px; color: #d97706; margin: 0;"><?= $total_categorias ?></h3>
    </div>
</div>

<!-- Seccion de Gráficos y Accesos -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
    
    <!-- Gráfico de Categorías -->
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
            <i class="fa-solid fa-chart-column" style="margin-right: 6px;"></i> Recetas por Categoría
        </h3>
        <div style="position: relative; height: 220px;">
            <canvas id="graficoCategorias"></canvas>
        </div>
    </div>

    <!-- Tabla de Últimas Recetas -->
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--text-heading); font-size: 16px;">
            <i class="fa-solid fa-clock-rotate-left" style="margin-right: 6px;"></i> Últimas Recetas Agregadas
        </h3>
        <table style="margin-top: 0;">
            <thead>
                <tr>
                    <th>Plato</th>
                    <th>Categoría</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($ultimas_recetas) > 0): ?>
                    <?php foreach ($ultimas_recetas as $rec): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($rec['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($rec['categoria_nombre'] ?? 'Sin Categoría') ?></td>
                            <td>
                                <a href="index.php?seccion=recetas_ver&id=<?= $rec['id'] ?>" class="btn btn-warning" style="padding: 4px 10px; font-size: 11px;">
                                    <i class="fa-solid fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--label-color);">No hay recetas aún.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Script para renderizar el gráfico -->
<script>
const ctx = document.getElementById('graficoCategorias').getContext('2d');
const graficoCategorias = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($nombres_categorias) ?>,
        datasets: [{
            label: 'Nº de Recetas',
            data: <?= json_encode($totales_categorias) ?>,
            backgroundColor: '#2563eb',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});
</script>