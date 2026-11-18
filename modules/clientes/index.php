<?php
// modules/clientes/index.php
require_once '../../config/database.php';
requireLogin();
$conn = getConnection();

// Crear
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = limpiar($_POST['nombre_razon_social']);
    $nit = limpiar($_POST['nit_ci']);
    $correo = limpiar($_POST['correo']);
    $telefono = limpiar($_POST['telefono']);
    $direccion = limpiar($_POST['direccion']);
    
    // VALIDACIÓN DOBLE: Verifica NIT y también Nombre (sin importar mayúsculas)
    $check = $conn->prepare("SELECT id_cliente FROM clientes WHERE nit_ci = ? OR LOWER(nombre_razon_social) = LOWER(?)");
    $check->bind_param("ss", $nit, $nombre);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error: Ya existe un cliente registrado con ese nombre o NIT/CI.'];
    } else {
        $stmt = $conn->prepare("INSERT INTO clientes (nombre_razon_social, nit_ci, correo, telefono, direccion) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nombre, $nit, $correo, $telefono, $direccion);
        $stmt->execute();
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Cliente registrado correctamente.'];
    }
    header("Location: index.php"); exit();
}

// Actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $id = (int)$_POST['id_cliente'];
    $nombre = limpiar($_POST['nombre_razon_social']);
    $nit = limpiar($_POST['nit_ci']);
    $correo = limpiar($_POST['correo']);
    $telefono = limpiar($_POST['telefono']);
    $direccion = limpiar($_POST['direccion']);
    
    // VALIDACIÓN DOBLE EXCLUYENDO EL ID ACTUAL
    $check = $conn->prepare("SELECT id_cliente FROM clientes WHERE (nit_ci = ? OR LOWER(nombre_razon_social) = LOWER(?)) AND id_cliente != ?");
    $check->bind_param("ssi", $nit, $nombre, $id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error: El nombre o NIT/CI ya pertenece a otro cliente.'];
    } else {
        $stmt = $conn->prepare("UPDATE clientes SET nombre_razon_social=?, nit_ci=?, correo=?, telefono=?, direccion=? WHERE id_cliente=?");
        $stmt->bind_param("sssssi", $nombre, $nit, $correo, $telefono, $direccion, $id);
        $stmt->execute();
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Cliente actualizado.'];
    }
    header("Location: index.php"); exit();
}

// Dar de baja
if (isset($_GET['baja'])) {
    $id = (int)$_GET['baja'];
    $conn->query("UPDATE clientes SET estado = 0 WHERE id_cliente = $id");
    $_SESSION['mensaje'] = ['tipo' => 'warning', 'texto' => 'Cliente dado de baja.'];
    header("Location: index.php"); exit();
}

// Historial
$historial = null;
if (isset($_GET['historial'])) {
    $id = (int)$_GET['historial'];
    $historial = $conn->query("
        SELECT t.id_transaccion, t.fecha, t.monto_total, t.estado,
               (SELECT COUNT(*) FROM detalle_transaccion WHERE id_transaccion = t.id_transaccion) as items
        FROM transacciones t WHERE t.id_cliente = $id ORDER BY t.fecha DESC
    ");
    $clienteInfo = $conn->query("SELECT * FROM clientes WHERE id_cliente = $id")->fetch_assoc();
}

$clientes = $conn->query("SELECT * FROM clientes ORDER BY estado DESC, nombre_razon_social");
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$conn->close();

$pageTitle = 'Clientes';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <h1>👥 Gestión de Clientes</h1>
        <button class="btn btn-primary" onclick="abrirModalCliente()">+ Nuevo Cliente</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?>"><?= $mensaje['tipo'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($mensaje['texto']) ?></div>
    <?php endif; ?>

    <?php if ($historial && $clienteInfo): ?>
    <div class="card">
        <div class="card-header">
            <h2>📋 Historial de Compras: <?= htmlspecialchars($clienteInfo['nombre_razon_social']) ?></h2>
            <a href="index.php" class="btn btn-sm">Volver</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>#</th><th>Fecha</th><th>Items</th><th>Total</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php if ($historial->num_rows > 0): while ($h = $historial->fetch_assoc()): ?>
                        <tr>
                            <td><?= $h['id_transaccion'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?></td>
                            <td><?= $h['items'] ?></td>
                            <td><strong>Bs. <?= number_format($h['monto_total'], 2) ?></strong></td>
                            <td><span class="badge badge-<?= $h['estado']==='pagada'?'success':($h['estado']==='anulada'?'danger':'warning') ?>"><?= ucfirst($h['estado']) ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center text-muted">Sin compras registradas</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>Listado de Clientes</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>#</th><th>Nombre/Razón Social</th><th>NIT/CI</th><th>Correo</th><th>Teléfono</th><th>Dirección</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php while ($c = $clientes->fetch_assoc()): ?>
                        <tr>
                            <td><?= $c['id_cliente'] ?></td>
                            <td><?= htmlspecialchars($c['nombre_razon_social']) ?></td>
                            <td><?= htmlspecialchars($c['nit_ci']) ?></td>
                            <td><?= htmlspecialchars($c['correo'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($c['telefono'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($c['direccion'] ?: '-') ?></td>
                            <td><span class="badge badge-<?= $c['estado'] ? 'success' : 'secondary' ?>"><?= $c['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <a href="index.php?historial=<?= $c['id_cliente'] ?>" class="btn btn-sm btn-info">📋 Historial</a>
                                <?php if ($c['estado']): ?>
                                <button class="btn btn-sm btn-warning" onclick='editarCliente(<?= json_encode($c) ?>)'>✏️ Editar</button>
                                <a href="index.php?baja=<?= $c['id_cliente'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Dar de baja?')">🚫 Baja</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cliente -->
<div class="modal-overlay" id="modalCliente">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalClienteTitle">Nuevo Cliente</h3>
            <button class="modal-close" onclick="cerrarModalCliente()">×</button>
        </div>
        <form method="POST" id="formCliente">
            <input type="hidden" name="accion" id="accionCliente" value="crear">
            <input type="hidden" name="id_cliente" id="idCliente" value="">
            <div class="modal-body">
                <div class="form-group"><label>Nombre / Razón Social *</label><input type="text" name="nombre_razon_social" id="f_nombre" class="form-control" required></div>
                <div class="form-group"><label>NIT / CI *</label><input type="text" name="nit_ci" id="f_nit" class="form-control" required></div>
                <div class="form-group"><label>Correo</label><input type="email" name="correo" id="f_correo" class="form-control"></div>
                <div class="form-group"><label>Teléfono</label><input type="text" name="telefono" id="f_telefono" class="form-control"></div>
                <div class="form-group"><label>Dirección</label><input type="text" name="direccion" id="f_direccion" class="form-control"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#ddd;" onclick="cerrarModalCliente()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCliente() {
    document.getElementById('formCliente').reset();
    document.getElementById('accionCliente').value = 'crear';
    document.getElementById('idCliente').value = '';
    document.getElementById('modalClienteTitle').textContent = 'Nuevo Cliente';
    document.getElementById('modalCliente').classList.add('active');
}
function editarCliente(c) {
    document.getElementById('accionCliente').value = 'actualizar';
    document.getElementById('idCliente').value = c.id_cliente;
    document.getElementById('f_nombre').value = c.nombre_razon_social;
    document.getElementById('f_nit').value = c.nit_ci;
    document.getElementById('f_correo').value = c.correo || '';
    document.getElementById('f_telefono').value = c.telefono || '';
    document.getElementById('f_direccion').value = c.direccion || '';
    document.getElementById('modalClienteTitle').textContent = 'Editar Cliente';
    document.getElementById('modalCliente').classList.add('active');
}
function cerrarModalCliente() { document.getElementById('modalCliente').classList.remove('active'); }
</script>

<?php include '../../includes/footer.php'; ?>