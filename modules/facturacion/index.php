<?php
// modules/facturacion/index.php
require_once '../../config/database.php';
requireLogin();
$conn = getConnection();

// Emitir factura
if (isset($_GET['emitir'])) {
    $id_trans = (int)$_GET['emitir'];
    $trans = $conn->query("SELECT * FROM transacciones WHERE id_transaccion = $id_trans AND estado = 'pagada'")->fetch_assoc();
    if ($trans) {
        // Verificar si ya existe factura
        $existe = $conn->query("SELECT id_factura FROM facturas WHERE id_transaccion = $id_trans")->fetch_assoc();
        if (!$existe) {
            $num = 'FAC-' . date('Y') . '-' . str_pad($id_trans, 5, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("INSERT INTO facturas (id_transaccion, numero_factura, monto_total) VALUES (?, ?, ?)");
            $stmt->bind_param("isd", $id_trans, $num, $trans['monto_total']);
            $stmt->execute();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => "Factura $num emitida correctamente."];
        } else {
            $_SESSION['mensaje'] = ['tipo' => 'warning', 'texto' => 'Esta transacción ya tiene factura.'];
        }
    } else {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'La transacción debe estar PAGADA para facturar.'];
    }
    header("Location: index.php"); exit();
}

// Anular factura
if (isset($_GET['anular'])) {
    $id = (int)$_GET['anular'];
    $conn->query("UPDATE facturas SET estado_factura = 'anulada', motivo_anulacion = 'Anulada por administrador' WHERE id_factura = $id");
    $_SESSION['mensaje'] = ['tipo' => 'warning', 'texto' => 'Factura anulada.'];
    header("Location: index.php"); exit();
}

$facturas = $conn->query("
    SELECT f.*, t.fecha AS fecha_venta, c.nombre_razon_social AS cliente, c.nit_ci
    FROM facturas f
    JOIN transacciones t ON f.id_transaccion = t.id_transaccion
    JOIN clientes c ON t.id_cliente = c.id_cliente
    ORDER BY f.fecha_emision DESC
");

$facturables = $conn->query("
    SELECT t.id_transaccion, t.monto_total, t.fecha, c.nombre_razon_social AS cliente
    FROM transacciones t
    JOIN clientes c ON t.id_cliente = c.id_cliente
    WHERE t.estado = 'pagada' AND NOT EXISTS (SELECT 1 FROM facturas f WHERE f.id_transaccion = t.id_transaccion)
    ORDER BY t.fecha DESC
");

$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$conn->close();

$pageTitle = 'Facturación';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar"><h1>🧾 Facturación Electrónica</h1></div>
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?>"><?= $mensaje['tipo'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($mensaje['texto']) ?></div>
    <?php endif; ?>

    <?php if ($facturables->num_rows > 0): ?>
    <div class="card">
        <div class="card-header"><h2>⏳ Pendientes de Facturar</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Venta #</th><th>Cliente</th><th>Monto</th><th>Fecha</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php while ($f = $facturables->fetch_assoc()): ?>
                        <tr>
                            <td><?= $f['id_transaccion'] ?></td>
                            <td><?= htmlspecialchars($f['cliente']) ?></td>
                            <td>Bs. <?= number_format($f['monto_total'], 2) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($f['fecha'])) ?></td>
                            <td><a href="index.php?emitir=<?= $f['id_transaccion'] ?>" class="btn btn-sm btn-success">🧾 Emitir Factura</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>📋 Historial de Facturación</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>N° Factura</th><th>Venta #</th><th>Cliente</th><th>NIT/CI</th><th>Monto</th><th>Fecha Emisión</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php if ($facturas->num_rows > 0): while ($f = $facturas->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $f['numero_factura'] ?></strong></td>
                            <td>#<?= $f['id_transaccion'] ?></td>
                            <td><?= htmlspecialchars($f['cliente']) ?></td>
                            <td><?= htmlspecialchars($f['nit_ci']) ?></td>
                            <td>Bs. <?= number_format($f['monto_total'], 2) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($f['fecha_emision'])) ?></td>
                            <td><span class="badge badge-<?= $f['estado_factura']==='emitida'?'success':'danger' ?>"><?= ucfirst($f['estado_factura']) ?></span></td>
                            <td>
                                <?php if ($f['estado_factura'] === 'emitida'): ?>
                                <a href="ver.php?id=<?= $f['id_factura'] ?>" class="btn btn-sm btn-info" target="_blank">👁️ Ver</a>
                                <a href="index.php?anular=<?= $f['id_factura'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Anular factura?')">🚫</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center text-muted">No hay facturas emitidas</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>