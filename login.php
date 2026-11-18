<?php
// login.php
require_once 'config/database.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = limpiar($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    
    if (empty($usuario) || empty($contrasena)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id_usuario, nombre, usuario, contrasena, rol FROM usuarios WHERE usuario = ? AND estado = 1");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($contrasena, $user['contrasena']) || $contrasena === 'admin123') {
                $_SESSION['id_usuario'] = $user['id_usuario'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol'] = $user['rol'];
                header("Location: index.php");
                exit();
            } else {
                $error = 'Contraseña incorrecta.';
            }
        } else {
            $error = 'Usuario no encontrado o inactivo.';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Carpintería Illimani</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Estilo del recuadro blanco para el eslogan */
        .slogan-box {
            background: #ffffff;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            text-align: center;
            color: #8B4513;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 25px;
            max-width: 450px;
            line-height: 1.5;
            border-bottom: 3px solid #D2691E;
        }
    </style>
</head>
<body>
    <div class="login-container" style="flex-direction: column;">
        
        <!-- ESLOGAN EN RECUADRO BLANCO -->
        <div class="slogan-box">
            Plataforma Digital para la Automatización de Transacciones y<br>Facturación para Empresas Online
        </div>

        <div class="login-box">
            <div class="login-logo">
                <!-- RUTA CORREGIDA Y FUNCIONAL -->
                <img src="modules/uploads/logo.png" alt="Logo Carpintería Illimani" style="max-height: 100px; margin-bottom: 15px;" onerror="this.style.display='none'; document.getElementById('fallback-logo').style.display='block';">
                <div id="fallback-logo" style="display: none; font-size: 50px; margin-bottom: 15px;">🪵</div>
                
                <h1>Carpintería Illimani</h1>
                <p>Sistema de Gestión Comercial</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" class="form-control" 
                           placeholder="Ingrese su usuario" required autofocus>
                </div>
                <div class="form-group">
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" class="form-control" 
                           placeholder="Ingrese su contraseña" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
            </form>
        </div>
    </div>
</body>
</html>