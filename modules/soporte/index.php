<?php
// modules/soporte/index.php
require_once '../../config/database.php';
requireLogin();

$mensaje = '';

// Lógica para el formulario de soporte (simula el envío)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'enviar_ticket') {
    $asunto = limpiar($_POST['asunto']);
    $descripcion = limpiar($_POST['descripcion']);
    // En un sistema real, aquí se enviaría un correo o se guardaría en BD.
    $mensaje = "✅ Ticket de soporte generado con éxito. Un técnico se contactará pronto. (Asunto: $asunto)";
}

$pageTitle = 'Soporte y Actualizaciones';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <h1>🛠️ Soporte Técnico y Actualizaciones</h1>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= $mensaje ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Módulo de Soporte en Línea (FC-003) -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h2>📩 Contactar a Soporte</h2></div>
                <div class="card-body">
                    <p class="text-muted" style="margin-bottom: 15px;">¿Tienes algún problema con el sistema? Envíanos un mensaje y lo revisaremos.</p>
                    <form method="POST">
                        <input type="hidden" name="accion" value="enviar_ticket">
                        <div class="form-group">
                            <label>Asunto *</label>
                            <input type="text" name="asunto" class="form-control" required placeholder="Ej: Error al facturar">
                        </div>
                        <div class="form-group">
                            <label>Descripción del problema *</label>
                            <textarea name="descripcion" class="form-control" rows="4" required placeholder="Describe lo que sucedió..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Enviar Ticket de Soporte</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Módulo de Historial de Actualizaciones / Git (FC-002) -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h2>🔄 Historial de Actualizaciones</h2></div>
                <div class="card-body">
                    <p class="text-muted" style="margin-bottom: 15px;">Registro de los últimos cambios y mejoras implementadas en el sistema (Control de versiones).</p>
                    <ul style="list-style-type: none; padding: 0;">
                        <li style="border-bottom: 1px solid #eee; padding: 10px 0;">
                            <strong>v1.1 - Noviembre 2026</strong><br>
                            <span class="text-muted">- Añadido descuento por producto individual (FC-001).<br>- Implementado módulo de soporte técnico (FC-003).</span>
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 10px 0;">
                            <strong>v1.0 - Octubre 2026</strong><br>
                            <span class="text-muted">- Lanzamiento inicial del sistema.<br>- Módulos de ventas, clientes, inventario y facturación.</span>
                        </li>
                    </ul>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <small class="text-muted">
                            <strong>Estado del Repositorio Git:</strong><br>
                            Rama: <code>main</code><br>
                            Último commit: <code>Cambios realizados</code>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>