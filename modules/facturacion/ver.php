<?php
// modules/facturacion/ver.php
require_once '../../config/database.php';
requireLogin();
$conn = getConnection();

$id = (int)($_GET['id'] ?? 0);
$factura = $conn->query("
    SELECT f.*, t.fecha AS fecha_venta, t.subtotal, t.descuento,
           c.nombre_razon_social AS cliente, c.nit_ci, c.correo, c.telefono, c.direccion,
           u.nombre AS vendedor
    FROM facturas f
    JOIN transacciones t ON f.id_transaccion = t.id_transaccion
    JOIN clientes c ON t.id_cliente = c.id_cliente
    JOIN usuarios u ON t.id_usuario = u.id_usuario
    WHERE f.id_factura = $id
")->fetch_assoc();

if (!$factura) die("Factura no encontrada.");

$detalles = $conn->query("
    SELECT dt.*, p.codigo, p.nombre
    FROM detalle_transaccion dt
    JOIN productos p ON dt.id_producto = p.id_producto
    WHERE dt.id_transaccion = {$factura['id_transaccion']}
");
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?= $factura['numero_factura'] ?></title>
    <!-- CDN de html2pdf para generar la descarga real -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* CSS específico para la factura (evita conflictos con styles.css) */
        body { background: #f0f2f5; font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; margin: 0; }
        .factura-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .factura-header { display: flex; justify-content: space-between; border-bottom: 3px solid #8B4513; padding-bottom: 20px; margin-bottom: 20px; }
        .empresa { font-size: 24px; color: #8B4513; font-weight: bold; }
        .empresa small { display: block; font-size: 12px; color: #666; font-weight: normal; margin-top: 5px; }
        .factura-info { text-align: right; }
        .factura-info h2 { color: #8B4513; margin: 0 0 10px 0; }
        .factura-info p { margin: 4px 0; }
        .cliente-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .cliente-box h3 { color: #8B4513; margin-top: 0; margin-bottom: 8px; font-size: 14px; }
        .cliente-box p { margin: 3px 0; font-size: 13px; }
        
        /* TABLA CORREGIDA: forzando el color blanco en el texto del encabezado */
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { 
            background-color: #8B4513 !important; 
            color: #ffffff !important; /* Texto blanco para que se vea */
            padding: 10px; 
            text-align: left; 
            font-size: 13px; 
        }
        table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 13px; color: #333; }
        
        .totales { margin-left: auto; width: 300px; margin-top: 20px; }
        .totales .row { display: flex; justify-content: space-between; padding: 8px 0; }
        .totales .total { border-top: 2px solid #8B4513; padding-top: 10px; font-size: 18px; font-weight: bold; color: #8B4513; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        .no-print { text-align: center; margin-top: 20px; display: flex; justify-content: center; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #8B4513; }
        .btn-primary:hover { background: #5D2E0C; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #ddd; color: #333; }
        .btn-secondary:hover { background: #ccc; }
        
        @media print { 
            .no-print { display: none; } 
            body { background: white; padding: 0; } 
            .factura-container { box-shadow: none; padding: 0; max-width: 100%; } 
        }
    </style>
</head>
<body>
    <div class="factura-container" id="facturaPDF">
        <div class="factura-header">
            <div class="empresa">
                🪵 CARPINTERÍA ILLIMANI
                <small>
                    NIT: 1020304050<br>
                    Av. Principal #123, La Paz - Bolivia<br>
                    Tel: 71234567 | Email: info@illimani.com
                </small>
            </div>
            <div class="factura-info">
                <h2>FACTURA</h2>
                <p><strong>N°:</strong> <?= $factura['numero_factura'] ?></p>
                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($factura['fecha_emision'])) ?></p>
                <p><strong>Estado:</strong> <span class="badge badge-<?= $factura['estado_factura']==='emitida'?'success':'danger' ?>"><?= strtoupper($factura['estado_factura']) ?></span></p>
            </div>
        </div>

        <div class="cliente-box">
            <h3>DATOS DEL CLIENTE</h3>
            <p><strong>Nombre:</strong> <?= htmlspecialchars($factura['cliente']) ?></p>
            <p><strong>NIT/CI:</strong> <?= htmlspecialchars($factura['nit_ci']) ?></p>
            <p><strong>Dirección:</strong> <?= htmlspecialchars($factura['direccion'] ?: '-') ?></p>
            <p><strong>Teléfono:</strong> <?= htmlspecialchars($factura['telefono'] ?: '-') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>P. Unit.</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($d = $detalles->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($d['codigo']) ?></td>
                    <td><?= htmlspecialchars($d['nombre']) ?></td>
                    <td><?= $d['cantidad'] ?></td>
                    <td>Bs. <?= number_format($d['precio_unitario'], 2) ?></td>
                    <td>Bs. <?= number_format($d['subtotal'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="totales">
            <div class="row"><span>Subtotal:</span><span>Bs. <?= number_format($factura['subtotal'], 2) ?></span></div>
            <div class="row"><span>Descuento:</span><span>Bs. <?= number_format($factura['descuento'], 2) ?></span></div>
            <div class="row total"><span>TOTAL:</span><span>Bs. <?= number_format($factura['monto_total'], 2) ?></span></div>
        </div>

        <p style="margin-top: 40px; text-align: center; font-size: 12px; color: #666;">
            Gracias por su compra - Vendedor: <?= htmlspecialchars($factura['vendedor']) ?>
        </p>
    </div>

    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir</button>
        <button class="btn btn-danger" onclick="descargarPDF()">📥 Descargar PDF</button>
        <!-- Botón volver con función que limpia el historial -->
        <a href="index.php" class="btn btn-secondary" onclick="volverSeguro(event)">← Volver</a>
    </div>

    <script>
        // Función para descargar el PDF
        function descargarPDF() {
            const elemento = document.getElementById('facturaPDF');
            const opciones = {
                margin:       10,
                filename:     'Factura_<?= $factura['numero_factura'] ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            // Generar y descargar
            html2pdf().set(opciones).from(elemento).save();
        }

        // Función para evitar que el botón "atrás" del navegador vuelva a esta página
        function volverSeguro(e) {
            e.preventDefault();
            window.location.replace('index.php'); 
        }
    </script>
</body>
</html>