<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'carpinteria_illimani');

function getConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para verificar autenticación
function requireLogin() {
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: " . getBaseUrl() . "login.php");
        exit();
    }
}

// Función para obtener URL base
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = str_replace('\\', '/', $path);
    // Ajustar si estamos en subcarpeta
    $base = rtrim($path, '/modules/usuarios') . '/';
    if (strpos($base, '/modules/') !== false) {
        $base = substr($base, 0, strpos($base, '/modules/') + 1);
    }
    return $protocol . $host . $base;
}

// Función para limpiar datos
function limpiar($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>