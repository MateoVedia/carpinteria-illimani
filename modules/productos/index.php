<?php
// modules/productos/index.php
require_once '../../config/database.php';
requireLogin();
$conn = getConnection();

// Ruta absoluta de la carpeta uploads para evitar errores
$uploadDir = realpath(__DIR__ . '/../../') . '/uploads/';

// Crear producto (con imagen)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'crear') {
    $codigo = limpiar($_POST['codigo']);
    $nombre = limpiar($_POST['nombre']);
    $descripcion = limpiar($_POST['descripcion']);
    $categoria = limpiar($_POST['categoria']);
    $precio = (float)$_POST['precio_unitario'];
    $stock = (int)$_POST['stock'];
    $stock_min = (int)$_POST['stock_minimo'];
    $imagenNombre = null;

    // Procesar imagen si se subió
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $imagenNombre = uniqid('prod_') . '.' . $ext;
        
        // Asegurarse de que la carpeta exista
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $imagenNombre)) {
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al guardar la imagen en el servidor.'];
            header("Location: index.php"); exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO productos (codigo, nombre, imagen, descripcion, categoria, precio_unitario, stock, stock_minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssdii", $codigo, $nombre, $imagenNombre, $descripcion, $categoria, $precio, $stock, $stock_min);
    $stmt->execute();
    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Producto registrado.'];
    header("Location: index.php"); exit();
}

// Actualizar
// Actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'actualizar') {
    $id = (int)$_POST['id_producto'];
    $nombre = limpiar($_POST['nombre']);
    $precio = (float)$_POST['precio_unitario'];
    $stock = (int)$_POST['stock'];
    
    // En lugar de confiar en el formulario, buscamos la imagen actual en la Base de Datos
    $stmtImg = $conn->prepare("SELECT imagen FROM productos WHERE id_producto = ?");
    $stmtImg->bind_param("i", $id);
    $stmtImg->execute();
    $resImg = $stmtImg->get_result()->fetch_assoc();
    $imagenActual = $resImg['imagen'] ?? null;
    $imagenNombre = $imagenActual;

    // Si se sube nueva imagen, reemplazar
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $imagenNombre = uniqid('prod_') . '.' . $ext;
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $imagenNombre)) {
            // Borrar imagen anterior si existía
            if ($imagenActual && file_exists($uploadDir . $imagenActual)) {
                unlink($uploadDir . $imagenActual);
            }
        } else {
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al guardar la nueva imagen.'];
            header("Location: index.php"); exit();
        }
    }

    $stmt = $conn->prepare("UPDATE productos SET nombre=?, imagen=?, precio_unitario=?, stock=? WHERE id_producto=?");
    $stmt->bind_param("ssdii", $nombre, $imagenNombre, $precio, $stock, $id);
    $stmt->execute();
    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Producto actualizado.'];
    header("Location: index.php"); exit();
}

// Ajustar stock rápido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'ajustar_stock') {
    $id = (int)$_POST['id_producto'];
    $cant = (int)$_POST['cantidad'];
    $conn->query("UPDATE productos SET stock = stock + $cant WHERE id_producto = $id");
    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Stock ajustado.'];
    header("Location: index.php"); exit();
}

$productos = $conn->query("SELECT * FROM productos ORDER BY estado DESC, categoria, nombre");
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$conn->close();

$pageTitle = 'Inventario';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <h1>📦 Inventario de Productos</h1>
        <button class="btn btn-primary" onclick="abrirModalProducto()">+ Nuevo Producto</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?>"><?= $mensaje['tipo'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($mensaje['texto']) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>Catálogo de Productos</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Imagen</th><th>Código</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Stock Mín.</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php while ($p = $productos->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php 
                                // Verificamos si la imagen existe físicamente en la carpeta
                                if (!empty($p['imagen']) && file_exists($uploadDir . $p['imagen'])): 
                                    // Usamos la ruta relativa directa para evitar problemas con getBaseUrl()
                                    $rutaImagen = '../../uploads/' . $p['imagen'];
                                ?>
                                    <img src="<?= $rutaImagen ?>" alt="Img" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 6px; display:flex; align-items:center; justify-content:center; font-size:20px; color:#999;" title="Sin imagen">🖼️</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['codigo']) ?></td>
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= htmlspecialchars($p['categoria'] ?: '-') ?></td>
                            <td>Bs. <?= number_format($p['precio_unitario'], 2) ?></td>
                            <td>
                                <strong style="color: <?= $p['stock'] <= $p['stock_minimo'] ? '#dc3545' : '#28a745' ?>;"><?= $p['stock'] ?></strong>
                            </td>
                            <td><?= $p['stock_minimo'] ?></td>
                            <td><span class="badge badge-<?= $p['estado'] ? 'success' : 'secondary' ?>"><?= $p['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editarProducto(<?= json_encode($p) ?>)'>✏️</button>
                                <button class="btn btn-sm btn-info" onclick='ajustarStock(<?= $p['id_producto'] ?>, "<?= htmlspecialchars($p['nombre']) ?>")'>📦</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Producto -->
<div class="modal-overlay" id="modalProducto">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalProductoTitle">Nuevo Producto</h3>
            <button class="modal-close" onclick="cerrarModalProducto()">×</button>
        </div>
        <form method="POST" id="formProducto" enctype="multipart/form-data">
            <input type="hidden" name="accion" id="accionProducto" value="crear">
            <input type="hidden" name="id_producto" id="idProducto" value="">
            <input type="hidden" name="imagen_actual" id="imagenActual" value="">
            <div class="modal-body">
                <div class="form-group"><label>Código *</label><input type="text" name="codigo" id="p_codigo" class="form-control" required></div>
                <div class="form-group"><label>Nombre *</label><input type="text" name="nombre" id="p_nombre" class="form-control" required></div>
                <div class="form-group"><label>Imagen (JPG/PNG)</label><input type="file" name="imagen" class="form-control" accept="image/*"></div>
                <div class="form-group"><label>Descripción</label><textarea name="descripcion" id="p_desc" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label>Categoría</label><input type="text" name="categoria" id="p_cat" class="form-control" placeholder="Maderas, Herrajes, etc."></div>
                <div class="row">
                    <div class="col-md-4"><div class="form-group"><label>Precio (Bs.) *</label><input type="number" name="precio_unitario" id="p_precio" class="form-control" step="0.01" required></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Stock *</label><input type="number" name="stock" id="p_stock" class="form-control" required></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Stock Mínimo *</label><input type="number" name="stock_minimo" id="p_stockmin" class="form-control" value="5" required></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#ddd;" onclick="cerrarModalProducto()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ajustar Stock -->
<div class="modal-overlay" id="modalStock">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header"><h3>Ajustar Stock</h3><button class="modal-close" onclick="document.getElementById('modalStock').classList.remove('active')">×</button></div>
        <form method="POST">
            <input type="hidden" name="accion" value="ajustar_stock">
            <input type="hidden" name="id_producto" id="stock_id">
            <div class="modal-body">
                <p id="stock_nombre" style="margin-bottom:15px; font-weight:600;"></p>
                <div class="form-group"><label>Cantidad a agregar (+)</label><input type="number" name="cantidad" class="form-control" value="10" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#ddd;" onclick="document.getElementById('modalStock').classList.remove('active')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Ajustar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalProducto() {
    document.getElementById('formProducto').reset();
    document.getElementById('accionProducto').value = 'crear';
    document.getElementById('idProducto').value = '';
    document.getElementById('imagenActual').value = '';
    document.getElementById('p_codigo').readOnly = false;
    document.getElementById('modalProductoTitle').textContent = 'Nuevo Producto';
    document.getElementById('modalProducto').classList.add('active');
}
function editarProducto(p) {
    document.getElementById('accionProducto').value = 'actualizar';
    document.getElementById('idProducto').value = p.id_producto;
    document.getElementById('imagenActual').value = p.imagen || '';
    document.getElementById('p_codigo').value = p.codigo;
    document.getElementById('p_codigo').readOnly = true;
    document.getElementById('p_nombre').value = p.nombre;
    document.getElementById('p_desc').value = p.descripcion || '';
    document.getElementById('p_cat').value = p.categoria || '';
    document.getElementById('p_precio').value = p.precio_unitario;
    document.getElementById('p_stock').value = p.stock;
    document.getElementById('p_stockmin').value = p.stock_minimo;
    document.getElementById('modalProductoTitle').textContent = 'Editar Producto';
    document.getElementById('modalProducto').classList.add('active');
}
function cerrarModalProducto() { document.getElementById('modalProducto').classList.remove('active'); }
function ajustarStock(id, nombre) {
    document.getElementById('stock_id').value = id;
    document.getElementById('stock_nombre').textContent = 'Producto: ' + nombre;
    document.getElementById('modalStock').classList.add('active');
}
</script>

<?php include '../../includes/footer.php'; ?>