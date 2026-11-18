<?php
// includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
function isActive($page, $currentPage, $currentDir) {
    return (strpos($currentPage, $page) !== false || strpos($currentDir, $page) !== false) ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <!-- RUTA CORREGIDA Y FUNCIONAL -->
        <img src="<?= getBaseUrl() ?>modules/uploads/logo.png" alt="Logo" style="max-height: 70px; margin-bottom: 10px;" onerror="this.style.display='none'; document.getElementById('fallback-sidebar').style.display='block';">
        <div id="fallback-sidebar" style="display: none; font-size: 40px; margin-bottom: 10px;">🪵</div>
        
        <h2>Carpintería Illimani</h2>
    </div>
    <ul class="sidebar-menu">
        <li><a href="<?= getBaseUrl() ?>index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <span class="menu-icon">📊</span> Dashboard
        </a></li>
        <li><a href="<?= getBaseUrl() ?>modules/ventas/index.php" class="<?= isActive('ventas', $currentPage, $currentDir) ?>">
            <span class="menu-icon">🛒</span> Ventas
        </a></li>
        <li><a href="<?= getBaseUrl() ?>modules/clientes/index.php" class="<?= isActive('clientes', $currentPage, $currentDir) ?>">
            <span class="menu-icon">👥</span> Clientes
        </a></li>
        <li><a href="<?= getBaseUrl() ?>modules/productos/index.php" class="<?= isActive('productos', $currentPage, $currentDir) ?>">
            <span class="menu-icon">📦</span> Inventario
        </a></li>
        <li><a href="<?= getBaseUrl() ?>modules/pagos/index.php" class="<?= isActive('pagos', $currentPage, $currentDir) ?>">
            <span class="menu-icon">💳</span> Pagos
        </a></li>
        <li><a href="<?= getBaseUrl() ?>modules/facturacion/index.php" class="<?= isActive('facturacion', $currentPage, $currentDir) ?>">
            <span class="menu-icon">🧾</span> Facturación
        </a></li>
        <li><a href="<?= getBaseUrl() ?>modules/reportes/index.php" class="<?= isActive('reportes', $currentPage, $currentDir) ?>">
            <span class="menu-icon">📈</span> Reportes
        </a></li>
        <?php if ($_SESSION['rol'] === 'admin'): ?>
        <li><a href="<?= getBaseUrl() ?>modules/usuarios/index.php" class="<?= isActive('usuarios', $currentPage, $currentDir) ?>">
            <span class="menu-icon">⚙️</span> Usuarios
        </a></li>
        <?php endif; ?>
        <li><a href="<?= getBaseUrl() ?>logout.php">
            <span class="menu-icon">🚪</span> Cerrar Sesión
        </a></li>
    </ul>
</aside>