<?php
// index.php
require_once 'config/database.php';
requireLogin();

$conn = getConnection();

// Estadísticas
$totalVentas = $conn->query("SELECT COUNT(*) as total FROM transacciones WHERE estado != 'anulada'")->fetch_assoc()['total'];
$totalClientes = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE estado = 1")->fetch_assoc()['total'];
$totalProductos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE estado = 1")->fetch_assoc()['total'];
$totalFacturas = $conn->query("SELECT COUNT(*) as total FROM facturas WHERE estado_factura = 'emitida'")->fetch_assoc()['total'];
$ingresosTotales = $conn->query("SELECT COALESCE(SUM(monto_total), 0) as total FROM transacciones WHERE estado = 'pagada'")->fetch_assoc()['total'];
$ventasPendientes = $conn->query("SELECT COUNT(*) as total FROM transacciones WHERE estado = 'pendiente'")->fetch_assoc()['total'];

// Ventas recientes
$ventasRecientes = $conn->query("
    SELECT t.id_transaccion, t.fecha, t.monto_total, t.estado, c.nombre_razon_social AS cliente
    FROM transacciones t
    JOIN clientes c ON t.id_cliente = c.id_cliente
    ORDER BY t.fecha DESC LIMIT 5
");

// Productos con bajo stock
$bajoStock = $conn->query("
    SELECT nombre, stock, stock_minimo FROM productos 
    WHERE stock <= stock_minimo AND estado = 1
    ORDER BY stock ASC LIMIT 5
");

// Datos para gráfico de ventas últimos 7 días
$ventasChart = $conn->query("
    SELECT DATE(fecha) as dia, SUM(monto_total) as total
    FROM transacciones
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND estado != 'anulada'
    GROUP BY DATE(fecha)
    ORDER BY dia ASC
");
$chartLabels = [];
$chartData = [];
while ($row = $ventasChart->fetch_assoc()) {
    $chartLabels[] = date('d/m', strtotime($row['dia']));
    $chartData[] = (float)$row['total'];
}

$conn->close();

$pageTitle = 'Dashboard';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <h1>📊 Panel Principal</h1>
        <div class="user-info">
            <span class="user-badge"><?= htmlspecialchars($_SESSION['nombre']) ?> (<?= $_SESSION['rol'] ?>)</span>
        </div>
    </div>

    <div class="welcome-banner">
        <h2>¡Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?>!</h2>
        <p>Sistema de gestión comercial - Carpintería Illimani</p>
        <div class="decorative-icon">🪵</div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon sales">🛒</div>
            <div class="stat-info">
                <h3><?= $totalVentas ?></h3>
                <p>Ventas Registradas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon clients">👥</div>
            <div class="stat-info">
                <h3><?= $totalClientes ?></h3>
                <p>Clientes Activos</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon products">📦</div>
            <div class="stat-info">
                <h3><?= $totalProductos ?></h3>
                <p>Productos en Inventario</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon invoices">💰</div>
            <div class="stat-info">
                <h3>Bs. <?= number_format($ingresosTotales, 2) ?></h3>
                <p>Ingresos Totales</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2>🕐 Ventas Recientes</h2>
                    <a href="modules/ventas/index.php" class="btn btn-sm btn-primary">Ver todas</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr><th>#</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($ventasRecientes->num_rows > 0): ?>
                                    <?php while ($v = $ventasRecientes->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $v['id_transaccion'] ?></td>
                                        <td><?= htmlspecialchars($v['cliente']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($v['fecha'])) ?></td>
                                        <td>Bs. <?= number_format($v['monto_total'], 2) ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = match($v['estado']) {
                                                'pagada' => 'badge-success',
                                                'pendiente' => 'badge-warning',
                                                'anulada' => 'badge-danger',
                                                default => 'badge-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($v['estado']) ?></span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted">No hay ventas registradas</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2>⚠️ Productos con Bajo Stock</h2>
                    <a href="modules/productos/index.php" class="btn btn-sm btn-warning">Ver inventario</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr><th>Producto</th><th>Stock</th><th>Mínimo</th><th>Estado</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($bajoStock->num_rows > 0): ?>
                                    <?php while ($p = $bajoStock->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                                        <td><strong><?= $p['stock'] ?></strong></td>
                                        <td><?= $p['stock_minimo'] ?></td>
                                        <td><span class="badge badge-danger">Reabastecer</span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Todo el stock está en orden ✅</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>📈 Ventas de los Últimos 7 Días</h2>
        </div>
        <div class="card-body">
            <canvas id="ventasChart" height="80"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('ventasChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Ventas (Bs.)',
                    data: <?= json_encode($chartData) ?>,
                    borderColor: '#8B4513',
                    backgroundColor: 'rgba(139, 69, 19, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: '#D2691E',
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => 'Bs. ' + v } }
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>