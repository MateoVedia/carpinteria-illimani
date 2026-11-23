<?php
// modules/ventas/index.php
require_once '../../config/database.php';
requireLogin();

$conn = getConnection();

// ==========================================
// LÓGICA PARA CLIENTE RÁPIDO (AJAX)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_cliente_rapido') {
    header('Content-Type: application/json');
    $nombre = limpiar($_POST['nombre'] ?? '');
    $nit = limpiar($_POST['nit_ci'] ?? '');
    
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'mensaje' => 'El nombre es obligatorio.']);
        exit();
    }

    if (empty($nit)) $nit = 'S/N-' . rand(1000, 9999);

    $check = $conn->prepare("SELECT id_cliente FROM clientes WHERE nit_ci = ? OR LOWER(nombre_razon_social) = LOWER(?)");
    $check->bind_param("ss", $nit, $nombre);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'mensaje' => 'El cliente ya existe.']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO clientes (nombre_razon_social, nit_ci, correo, telefono, direccion) VALUES (?, ?, 'N/A', 'N/A', 'N/A')");
    $stmt->bind_param("ss", $nombre, $nit);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'nombre' => $nombre, 'nit' => $nit]);
    } else {
        echo json_encode(['success' => false, 'mensaje' => 'Error al guardar.']);
    }
    exit();
}

// ==========================================
// LÓGICA NORMAL DE VENTA (CON DESCUENTO INDIVIDUAL)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_venta') {
    $id_cliente = (int)$_POST['id_cliente'];
    $descuento_general = (float)($_POST['descuento'] ?? 0);
    $productos = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $descuentos_items = $_POST['descuentos_items'] ?? []; // NUEVO
    
    if ($id_cliente <= 0 || empty($productos)) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Debe seleccionar cliente y al menos un producto.'];
    } else {
        $conn->begin_transaction();
        try {
            $subtotal_general = 0;
            $items = [];
            
            for ($i = 0; $i < count($productos); $i++) {
                $id_prod = (int)$productos[$i];
                $cant = (int)$cantidades[$i];
                $desc_item = (float)($descuentos_items[$i] ?? 0); // NUEVO
                
                if ($id_prod <= 0 || $cant <= 0) continue;
                
                $prod = $conn->query("SELECT * FROM productos WHERE id_producto = $id_prod AND estado = 1")->fetch_assoc();
                if (!$prod) throw new Exception("Producto no encontrado.");
                if ($prod['stock'] < $cant) throw new Exception("Stock insuficiente para: {$prod['nombre']}");
                
                $sub_item = $prod['precio_unitario'] * $cant;
                
                // Validar descuento por item (máximo 20% del subtotal del item)
                $max_desc_item = $sub_item * 0.20;
                if ($desc_item > $max_desc_item) $desc_item = $max_desc_item;
                
                $total_item = $sub_item - $desc_item;
                $subtotal_general += $total_item;
                
                $items[] = [
                    'id' => $id_prod, 
                    'cant' => $cant, 
                    'precio' => $prod['precio_unitario'], 
                    'sub' => $sub_item, 
                    'desc' => $desc_item, 
                    'total_item' => $total_item
                ];
            }
            
            if (empty($items)) throw new Exception("No hay productos válidos.");
            
            // Validar descuento general (máximo 20% del subtotal ya con descuentos individuales)
            $max_desc_general = $subtotal_general * 0.20;
            if ($descuento_general > $max_desc_general) $descuento_general = $max_desc_general;
            
            $total_final = $subtotal_general - $descuento_general;
            $id_usuario = $_SESSION['id_usuario'];
            
            // Guardamos el subtotal original (antes de descuentos individuales) y el descuento total
            $subtotal_original = 0;
            foreach($items as $it) { $subtotal_original += $it['sub']; }
            
            $descuento_total = ($subtotal_original - $subtotal_general) + $descuento_general;
            
            $stmt = $conn->prepare("INSERT INTO transacciones (id_cliente, id_usuario, subtotal, descuento, monto_total, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')");
            $stmt->bind_param("iiddd", $id_cliente, $id_usuario, $subtotal_original, $descuento_total, $total_final);
            $stmt->execute();
            $id_trans = $conn->insert_id;
            
            foreach ($items as $it) {
                // Guardamos el subtotal del item CON su descuento aplicado
                $stmt2 = $conn->prepare("INSERT INTO detalle_transaccion (id_transaccion, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("iiidd", $id_trans, $it['id'], $it['cant'], $it['precio'], $it['total_item']);
                $stmt2->execute();
                
                $conn->query("UPDATE productos SET stock = stock - {$it['cant']} WHERE id_producto = {$it['id']}");
            }
            
            $conn->commit();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => "Venta #$id_trans registrada. Total: Bs. " . number_format($total_final, 2)];
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
        }
    }
}

// Anular venta
if (isset($_GET['anular']) && $_SESSION['rol'] === 'admin') {
    $id = (int)$_GET['anular'];
    $conn->query("UPDATE transacciones SET estado = 'anulada' WHERE id_transaccion = $id");
    $detalles = $conn->query("SELECT id_producto, cantidad FROM detalle_transaccion WHERE id_transaccion = $id");
    while ($d = $detalles->fetch_assoc()) {
        $conn->query("UPDATE productos SET stock = stock + {$d['cantidad']} WHERE id_producto = {$d['id_producto']}");
    }
    $_SESSION['mensaje'] = ['tipo' => 'warning', 'texto' => "Venta #$id anulada."];
    header("Location: index.php");
    exit();
}

$clientes = $conn->query("SELECT id_cliente, nombre_razon_social, nit_ci FROM clientes WHERE estado = 1 ORDER BY nombre_razon_social");
$productos = $conn->query("SELECT id_producto, codigo, nombre, precio_unitario, stock FROM productos WHERE estado = 1 AND stock > 0 ORDER BY nombre");
$ventas = $conn->query("
    SELECT t.*, c.nombre_razon_social AS cliente, u.nombre AS vendedor,
           (SELECT COUNT(*) FROM detalle_transaccion dt WHERE dt.id_transaccion = t.id_transaccion) AS items
    FROM transacciones t
    JOIN clientes c ON t.id_cliente = c.id_cliente
    JOIN usuarios u ON t.id_usuario = u.id_usuario
    ORDER BY t.fecha DESC
");
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$conn->close();

$pageTitle = 'Ventas';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <h1>🛒 Gestión de Ventas</h1>
        <button class="btn btn-primary" onclick="document.getElementById('modalVenta').classList.add('active')">+ Nueva Venta</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?>"><?= $mensaje['tipo'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($mensaje['texto']) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>Historial de Ventas</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>#</th><th>Cliente</th><th>Vendedor</th><th>Items</th><th>Subtotal</th><th>Descuento</th><th>Total</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($ventas->num_rows > 0): while ($v = $ventas->fetch_assoc()): ?>
                        <tr>
                            <td><?= $v['id_transaccion'] ?></td>
                            <td><?= htmlspecialchars($v['cliente']) ?></td>
                            <td><?= htmlspecialchars($v['vendedor']) ?></td>
                            <td><?= $v['items'] ?></td>
                            <td>Bs. <?= number_format($v['subtotal'], 2) ?></td>
                            <td>Bs. <?= number_format($v['descuento'], 2) ?></td>
                            <td><strong>Bs. <?= number_format($v['monto_total'], 2) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                            <td>
                                <?php $bc = match($v['estado']) { 'pagada'=>'badge-success','pendiente'=>'badge-warning','anulada'=>'badge-danger', default=>'badge-secondary' }; ?>
                                <span class="badge <?= $bc ?>"><?= ucfirst($v['estado']) ?></span>
                            </td>
                            <td>
                                <?php if ($v['estado'] === 'pendiente'): ?>
                                    <a href="index.php?anular=<?= $v['id_transaccion'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Anular esta venta?')">Anular</a>
                                <?php elseif ($v['estado'] === 'pagada'): ?>
                                    <a href="../facturacion/index.php?transaccion=<?= $v['id_transaccion'] ?>" class="btn btn-sm btn-info">Facturar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="10" class="text-center text-muted">No hay ventas registradas</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NUEVA VENTA -->
<div class="modal-overlay" id="modalVenta">
    <div class="modal" style="max-width: 900px;">
        <div class="modal-header">
            <h3>🛒 Registrar Nueva Venta</h3>
            <button class="modal-close" onclick="document.getElementById('modalVenta').classList.remove('active')">×</button>
        </div>
        <form method="POST" id="formVenta">
            <input type="hidden" name="accion" value="crear_venta">
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Cliente *</label>
                            <div style="display: flex; gap: 10px;">
                                <select name="id_cliente" id="selectCliente" class="form-control" required style="flex: 1;">
                                    <option value="">-- Seleccionar --</option>
                                    <?php while ($c = $clientes->fetch_assoc()): ?>
                                    <option value="<?= $c['id_cliente'] ?>"><?= htmlspecialchars($c['nombre_razon_social']) ?> (<?= $c['nit_ci'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                                <button type="button" class="btn btn-success" onclick="abrirModalClienteRapido()" title="Crear cliente rápido">+ Nuevo</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Descuento General (Bs.) - Máx 20%</label>
                            <input type="number" name="descuento" id="descuento" class="form-control" value="0" min="0" step="0.01">
                        </div>
                    </div>
                </div>

                <div class="sale-items-table">
                    <h4 style="margin-bottom: 10px;">Productos</h4>
                    <table style="width:100%; font-size:13px;">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Desc. Item (Bs.)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="productosBody"></tbody>
                    </table>
                    <button type="button" class="btn btn-secondary btn-sm" style="margin-top:10px;" onclick="agregarFila()">+ Agregar Producto</button>
                </div>

                <div class="total-section">
                    <div class="total-row"><span>Subtotal (sin desc. item):</span><span id="lblSubtotal">Bs. 0.00</span></div>
                    <div class="total-row"><span>Descuento General:</span><span id="lblDescuento">Bs. 0.00</span></div>
                    <div class="total-row grand-total"><span>TOTAL:</span><span id="lblTotal">Bs. 0.00</span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#ddd;" onclick="document.getElementById('modalVenta').classList.remove('active')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CLIENTE RÁPIDO -->
<div class="modal-overlay" id="modalClienteRapido" style="z-index: 3000;">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header" style="background: #28a745; color: white;">
            <h3 style="color: white; margin: 0;">👤 Cliente Rápido</h3>
            <button class="modal-close" style="color: white;" onclick="document.getElementById('modalClienteRapido').classList.remove('active')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nombre o Razón Social *</label>
                <input type="text" id="rapidoNombre" class="form-control" placeholder="Ej: Juan Pérez" required>
            </div>
            <div class="form-group">
                <label>NIT / CI (Opcional)</label>
                <input type="text" id="rapidoNit" class="form-control" placeholder="Ej: 12345678">
            </div>
            <div id="errorRapido" class="alert alert-danger" style="display: none; padding: 10px; font-size: 13px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" style="background:#ddd;" onclick="document.getElementById('modalClienteRapido').classList.remove('active')">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="guardarClienteRapido()" id="btnGuardarRapido">Guardar y Seleccionar</button>
        </div>
    </div>
</div>

<script>
const productosData = <?= json_encode($productos->fetch_all(MYSQLI_ASSOC)) ?>;

function agregarFila() {
    const tbody = document.getElementById('productosBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="productos[]" class="form-control" style="min-width:150px;" onchange="calcularTotales()" required>
                <option value="">-- Seleccionar --</option>
                ${productosData.map(p => `<option value="${p.id_producto}" data-precio="${p.precio_unitario}" data-stock="${p.stock}">${p.codigo} - ${p.nombre}</option>`).join('')}
            </select>
        </td>
        <td class="precio-cell">Bs. 0.00</td>
        <td class="stock-cell">-</td>
        <td><input type="number" name="cantidades[]" class="form-control" value="1" min="1" style="width:70px;" onchange="calcularTotales()" required></td>
        <td class="subtotal-cell">Bs. 0.00</td>
        <td><input type="number" name="descuentos_items[]" class="form-control" value="0" min="0" step="0.01" style="width:80px;" onchange="calcularTotales()"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); calcularTotales()">×</button></td>
    `;
    tbody.appendChild(tr);
}

function calcularTotales() {
    let subtotalSinDescuento = 0;
    let totalDescuentosItems = 0;
    
    document.querySelectorAll('#productosBody tr').forEach(tr => {
        const select = tr.querySelector('select');
        const cant = parseInt(tr.querySelector('input[name="cantidades[]"]').value) || 0;
        const descItem = parseFloat(tr.querySelector('input[name="descuentos_items[]"]').value) || 0;
        const opt = select.options[select.selectedIndex];
        const precio = parseFloat(opt.dataset.precio) || 0;
        const stock = parseInt(opt.dataset.stock) || 0;
        
        const sub = precio * cant;
        
        // Validar descuento máximo del 20% por item
        const maxDesc = sub * 0.20;
        let descAplicado = descItem;
        if (descAplicado > maxDesc) {
            descAplicado = maxDesc;
            tr.querySelector('input[name="descuentos_items[]"]').value = maxDesc.toFixed(2);
        }
        
        tr.querySelector('.precio-cell').textContent = 'Bs. ' + precio.toFixed(2);
        tr.querySelector('.stock-cell').textContent = opt.value ? stock : '-';
        tr.querySelector('.subtotal-cell').textContent = 'Bs. ' + (sub - descAplicado).toFixed(2);
        
        if (opt.value && cant > stock) {
            tr.querySelector('input[name="cantidades[]"]').style.borderColor = '#dc3545';
        } else {
            tr.querySelector('input[name="cantidades[]"]').style.borderColor = '#e0e0e0';
        }
        
        subtotalSinDescuento += sub;
        totalDescuentosItems += descAplicado;
    });
    
    let descuentoGeneral = parseFloat(document.getElementById('descuento').value) || 0;
    const subtotalConDescItems = subtotalSinDescuento - totalDescuentosItems;
    
    // Validar descuento general máximo del 20%
    const maxDescGeneral = subtotalConDescItems * 0.20;
    if (descuentoGeneral > maxDescGeneral) {
        descuentoGeneral = maxDescGeneral;
        document.getElementById('descuento').value = maxDescGeneral.toFixed(2);
    }
    
    document.getElementById('lblSubtotal').textContent = 'Bs. ' + subtotalSinDescuento.toFixed(2);
    document.getElementById('lblDescuento').textContent = 'Bs. ' + descuentoGeneral.toFixed(2);
    document.getElementById('lblTotal').textContent = 'Bs. ' + (subtotalConDescItems - descuentoGeneral).toFixed(2);
}

document.getElementById('descuento').addEventListener('input', calcularTotales);
document.getElementById('formVenta').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('#productosBody tr');
    if (rows.length === 0) { e.preventDefault(); alert('Debe agregar al menos un producto.'); return; }
    let valido = true;
    rows.forEach(tr => {
        const select = tr.querySelector('select');
        const cant = parseInt(tr.querySelector('input[name="cantidades[]"]').value) || 0;
        const opt = select.options[select.selectedIndex];
        const stock = parseInt(opt.dataset.stock) || 0;
        if (!opt.value || cant <= 0 || cant > stock) valido = false;
    });
    if (!valido) { e.preventDefault(); alert('Verifique los productos y cantidades.'); }
});

document.querySelector('[onclick*="modalVenta"]').addEventListener('click', function() {
    if (document.getElementById('productosBody').children.length === 0) agregarFila();
});

// Lógica Cliente Rápido
function abrirModalClienteRapido() {
    document.getElementById('rapidoNombre').value = '';
    document.getElementById('rapidoNit').value = '';
    document.getElementById('errorRapido').style.display = 'none';
    document.getElementById('modalClienteRapido').classList.add('active');
    document.getElementById('rapidoNombre').focus();
}

function guardarClienteRapido() {
    const nombre = document.getElementById('rapidoNombre').value.trim();
    const nit = document.getElementById('rapidoNit').value.trim();
    const errorDiv = document.getElementById('errorRapido');
    const btn = document.getElementById('btnGuardarRapido');
    
    if (!nombre) {
        errorDiv.textContent = 'El nombre es obligatorio.';
        errorDiv.style.display = 'block';
        return;
    }
    
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    
    const formData = new FormData();
    formData.append('accion', 'crear_cliente_rapido');
    formData.append('nombre', nombre);
    formData.append('nit_ci', nit);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('selectCliente');
            const option = document.createElement('option');
            option.value = data.id;
            option.textContent = data.nombre + ' (' + data.nit + ')';
            select.appendChild(option);
            select.value = data.id;
            document.getElementById('modalClienteRapido').classList.remove('active');
        } else {
            errorDiv.textContent = data.mensaje;
            errorDiv.style.display = 'block';
        }
    })
    .catch(err => {
        errorDiv.textContent = 'Error de conexión.';
        errorDiv.style.display = 'block';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Guardar y Seleccionar';
    });
}
</script>

<?php include '../../includes/footer.php'; ?>