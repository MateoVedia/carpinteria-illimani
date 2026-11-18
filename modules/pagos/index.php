<?php
// modules/pagos/index.php
require_once '../../config/database.php';
requireLogin();
$conn = getConnection();

// Registrar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'registrar_pago') {
    $id_trans = (int)$_POST['id_transaccion'];
    $metodo = $_POST['metodo_pago'];
    $monto = (float)$_POST['monto'];
    $referencia = limpiar($_POST['referencia'] ?? '');
    
    // Verificar transacción
    $trans = $conn->query("SELECT * FROM transacciones WHERE id_transaccion = $id_trans AND estado = 'pendiente'")->fetch_assoc();
    if (!$trans) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Transacción no válida o no está pendiente.'];
    } else {
        $stmt = $conn->prepare("INSERT INTO pagos (id_transaccion, metodo_pago, monto, estado_pago, referencia) VALUES (?, ?, ?, 'aprobado', ?)");
        $stmt->bind_param("isds", $id_trans, $metodo, $monto, $referencia);
        $stmt->execute();
        
        $conn->query("UPDATE transacciones SET estado = 'pagada' WHERE id_transaccion = $id_trans");
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => "Pago registrado. Transacción #$id_trans marcada como PAGADA."];
    }
    header("Location: index.php"); exit();
}

$pendientes = $conn->query("
    SELECT t.*, c.nombre_razon_social AS cliente
    FROM transacciones t JOIN clientes c ON t.id_cliente = c.id_cliente
    WHERE t.estado = 'pendiente' ORDER BY t.fecha DESC
");
$pagos = $conn->query("
    SELECT p.*, t.monto_total, c.nombre_razon_social AS cliente
    FROM pagos p
    JOIN transacciones t ON p.id_transaccion = t.id_transaccion
    JOIN clientes c ON t.id_cliente = c.id_cliente
    ORDER BY p.fecha_pago DESC
");
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$conn->close();

$pageTitle = 'Pagos';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar"><h1>💳 Gestión de Pagos</h1></div>
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?>"><?= $mensaje['tipo'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($mensaje['texto']) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h2>⏳ Transacciones Pendientes de Pago</h2></div>
                <div class="card-body">
                    <?php if ($pendientes->num_rows > 0): while ($t = $pendientes->fetch_assoc()): ?>
                    <div style="border:1px solid #eee; padding:15px; border-radius:8px; margin-bottom:15px;">
                        <div class="d-flex justify-between align-center" style="margin-bottom:10px;">
                            <div>
                                <strong>Venta #<?= $t['id_transaccion'] ?></strong><br>
                                <span class="text-muted"><?= htmlspecialchars($t['cliente']) ?> - <?= date('d/m/Y', strtotime($t['fecha'])) ?></span>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge badge-warning">Bs. <?= number_format($t['monto_total'], 2) ?></span>
                            </div>
                        </div>
                        <form method="POST" class="d-flex gap-2" style="flex-wrap:wrap;">
                            <input type="hidden" name="accion" value="registrar_pago">
                            <input type="hidden" name="id_transaccion" value="<?= $t['id_transaccion'] ?>">
                            <input type="hidden" name="monto" value="<?= $t['monto_total'] ?>">
                            <select name="metodo_pago" class="form-control" style="flex:1; min-width:120px;" required>
                                <option value="">Método</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="qr">QR</option>
                            </select>
                            <input type="text" name="referencia" class="form-control" placeholder="Ref. (opcional)" style="flex:1; min-width:100px;">
                            <button type="submit" class="btn btn-success">✓ Cobrar</button>
                        </form>
                    </div>
                    <?php endwhile; else: ?>
                    <p class="text-center text-muted">No hay transacciones pendientes. ✅</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h2>✅ Pagos Registrados</h2></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>#</th><th>Venta</th><th>Cliente</th><th>Método</th><th>Monto</th><th>Fecha</th></tr></thead>
                            <tbody>
                                <?php if ($pagos->num_rows > 0): while ($p = $pagos->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $p['id_pago'] ?></td>
                                    <td>#<?= $p['id_transaccion'] ?></td>
                                    <td><?= htmlspecialchars($p['cliente']) ?></td>
                                    <td><span class="badge badge-info"><?= ucfirst($p['metodo_pago']) ?></span></td>
                                    <td>Bs. <?= number_format($p['monto'], 2) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($p['fecha_pago'])) ?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Sin pagos registrados</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>