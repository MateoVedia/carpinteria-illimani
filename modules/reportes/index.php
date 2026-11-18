<?php
// modules/reportes/index.php
require_once '../../config/database.php';
requireLogin();
$conn = getConnection();

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? 'ventas';

$desdeSQL = $conn->real_escape_string($desde) . ' 00:00:00';
$hastaSQL = $conn->real_escape_string($hasta) . ' 23:59:59';

// Reporte de ventas
$ventasReporte = $conn->query("
    SELECT t.id_transaccion, t.fecha, c.nombre_razon_social AS cliente,
           u.nombre AS vendedor, t.subtotal, t.descuento, t.monto_total, t.estado,
           (SELECT COUNT(*) FROM detalle_transaccion WHERE id_transaccion = t.id_transaccion) as items
    FROM transacciones t
    JOIN clientes c ON t.id_cliente = c.id_cliente
    JOIN usuarios u ON t.id_usuario = u.id_usuario
    WHERE t.fecha BETWEEN '$desdeSQL' AND '$hastaSQL'
    ORDER BY t.fecha DESC
");

$totalVendido = $conn->query("SELECT COALESCE(SUM(monto_total),0) as t FROM transacciones WHERE fecha BETWEEN '$desdeSQL' AND '$hastaSQL' AND estado != 'anulada'")->fetch_assoc()['t'];
$cantidadVentas = $conn->query("SELECT COUNT(*) as c FROM transacciones WHERE fecha BETWEEN '$desdeSQL' AND '$hastaSQL'")->fetch_assoc()['c'];

// Reporte productos más vendidos
$topProductos = $conn->query("
    SELECT p.nombre, p.codigo, SUM(dt.cantidad) as total_vendido, SUM(dt.subtotal) as total_ingreso
    FROM detalle_transaccion dt
    JOIN productos p ON dt.id_producto = p.id_producto
    JOIN transacciones t ON dt.id_transaccion = t.id_transaccion
    WHERE t.fecha BETWEEN '$desdeSQL' AND '$hastaSQL' AND t.estado != 'anulada'
    GROUP BY p.id_producto
    ORDER BY total_vendido DESC
    LIMIT 10
");

// Reporte por método de pago
$porMetodo = $conn->query("
    SELECT metodo_pago, COUNT(*) as cantidad, SUM(monto) as total
    FROM pagos
    WHERE fecha_pago BETWEEN '$desdeSQL' AND '$hastaSQL' AND estado_pago = 'aprobado'
    GROUP BY metodo_pago
");

$conn->close();
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$pageTitle = 'Reportes';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar"><h1>📈 Reportes</h1></div>

    <div class="card">
        <div class="card-header"><h2>🔍 Filtros</h2></div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-3"><div class="form-group"><label>Desde</label><input type="date" name="desde" class="form-control" value="<?= $desde ?>"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Hasta</label><input type="date" name="hasta" class="form-control" value="<?= $hasta ?>"></div></div>
                <div class="col-md-3"><div class="form-group"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-block">Filtrar</button></div></div>
                <div class="col-md-3"><div class="form-group"><label>&nbsp;</label><button type="button" class="btn btn-success btn-block" onclick="window.print()">🖨️ Imprimir</button></div></div>
            </form>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon sales">💰</div>
            <div class="stat-info"><h3>Bs. <?= number_format($totalVendido, 2) ?></h3><p>Total Vendido</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon clients">🛒</div>
            <div class="stat-info"><h3><?= $cantidadVentas ?></h3><p>Ventas Realizadas</p></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>📋 Detalle de Ventas</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Items</th><th>Subtotal</th><th>Descuento</th><th>Total</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php while ($v = $ventasReporte->fetch_assoc()): ?>
                        <tr>
                            <td><?= $v['id_transaccion'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                            <td><?= htmlspecialchars($v['cliente']) ?></td>
                            <td><?= htmlspecialchars($v['vendedor']) ?></td>
                            <td><?= $v['items'] ?></td>
                            <td>Bs. <?= number_format($v['subtotal'], 2) ?></td>
                            <td>Bs. <?= number_format($v['descuento'], 2) ?></td>
                            <td><strong>Bs. <?= number_format($v['monto_total'], 2) ?></strong></td>
                            <td><span class="badge badge-<?= $v['estado']==='pagada'?'success':($v['estado']==='anulada'?'danger':'warning') ?>"><?= ucfirst($v['estado']) ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h2>🏆 Productos Más Vendidos</h2></div>
                <div class="card-body">
                    <table>
                        <thead><tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Ingresos</th></tr></thead>
                        <tbody>
                            <?php while ($p = $topProductos->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['codigo']) ?></td>
                                <td><?= htmlspecialchars($p['nombre']) ?></td>
                                <td><strong><?= $p['total_vendido'] ?></strong></td>
                                <td>Bs. <?= number_format($p['total_ingreso'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h2>💳 Ingresos por Método de Pago</h2></div>
                <div class="card-body">
                    <table>
                        <thead><tr><th>Método</th><th>Operaciones</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php while ($m = $porMetodo->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge badge-info"><?= ucfirst($m['metodo_pago']) ?></span></td>
                                <td><?= $m['cantidad'] ?></td>
                                <td><strong>Bs. <?= number_format($m['total'], 2) ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>