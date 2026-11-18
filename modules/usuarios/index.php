<?php
// modules/usuarios/index.php
require_once '../../config/database.php';
requireLogin();

if ($_SESSION['rol'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$conn = getConnection();

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'crear') {
    $nombre = limpiar($_POST['nombre']);
    $usuario = limpiar($_POST['usuario']);
    $pass = $_POST['contrasena'];
    $rol = $_POST['rol'];
    
    if (strlen($pass) < 6) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'La contraseña debe tener al menos 6 caracteres.'];
    } else {
        $check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
        $check->bind_param("s", $usuario);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'El nombre de usuario ya existe.'];
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, usuario, contrasena, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $usuario, $hash, $rol);
            $stmt->execute();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Usuario creado exitosamente.'];
        }
    }
    header("Location: index.php"); exit();
}

// Cambiar estado
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id != $_SESSION['id_usuario']) {
        $conn->query("UPDATE usuarios SET estado = NOT estado WHERE id_usuario = $id");
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Estado de usuario actualizado.'];
    }
    header("Location: index.php"); exit();
}

$usuarios = $conn->query("SELECT * FROM usuarios ORDER BY rol, nombre");
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$conn->close();

$pageTitle = 'Usuarios';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <h1>⚙️ Gestión de Usuarios</h1>
        <button class="btn btn-primary" onclick="document.getElementById('modalUsuario').classList.add('active')">+ Nuevo Usuario</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?>"><?= $mensaje['tipo'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($mensaje['texto']) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>Usuarios del Sistema</h2></div>
        <div class="card-body">
            <table>
                <thead><tr><th>#</th><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php while ($u = $usuarios->fetch_assoc()): ?>
                    <tr>
                        <td><?= $u['id_usuario'] ?></td>
                        <td><?= htmlspecialchars($u['nombre']) ?></td>
                        <td><?= htmlspecialchars($u['usuario']) ?></td>
                        <td><span class="badge badge-<?= $u['rol']==='admin'?'danger':'info' ?>"><?= ucfirst($u['rol']) ?></span></td>
                        <td><span class="badge badge-<?= $u['estado']?'success':'secondary' ?>"><?= $u['estado']?'Activo':'Inactivo' ?></span></td>
                        <td>
                            <?php if ($u['id_usuario'] != $_SESSION['id_usuario']): ?>
                            <a href="index.php?toggle=<?= $u['id_usuario'] ?>" class="btn btn-sm btn-<?= $u['estado']?'warning':'success' ?>">
                                <?= $u['estado']?'Desactivar':'Activar' ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">- Actual -</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalUsuario">
    <div class="modal">
        <div class="modal-header"><h3>Nuevo Usuario</h3><button class="modal-close" onclick="document.getElementById('modalUsuario').classList.remove('active')">×</button></div>
        <form method="POST">
            <input type="hidden" name="accion" value="crear">
            <div class="modal-body">
                <div class="form-group"><label>Nombre Completo *</label><input type="text" name="nombre" class="form-control" required></div>
                <div class="form-group"><label>Usuario *</label><input type="text" name="usuario" class="form-control" required></div>
                <div class="form-group"><label>Contraseña * (mín. 6 caracteres)</label><input type="password" name="contrasena" class="form-control" required minlength="6"></div>
                <div class="form-group"><label>Rol *</label>
                    <select name="rol" class="form-control" required>
                        <option value="vendedor">Vendedor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#ddd;" onclick="document.getElementById('modalUsuario').classList.remove('active')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>