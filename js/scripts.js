// js/scripts.js
document.addEventListener('DOMContentLoaded', function() {
    // Cerrar modales al hacer clic fuera
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });

    // Auto-ocultar alertas
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Toggle sidebar en móvil
    const topbar = document.querySelector('.topbar');
    if (topbar && window.innerWidth < 768) {
        const toggleBtn = document.createElement('button');
        toggleBtn.innerHTML = '☰';
        toggleBtn.className = 'btn btn-sm';
        toggleBtn.style.marginRight = '10px';
        toggleBtn.onclick = () => document.getElementById('sidebar').classList.toggle('active');
        topbar.insertBefore(toggleBtn, topbar.firstChild);
    }
});

// Confirmación genérica
function confirmar(mensaje) {
    return confirm(mensaje || '¿Está seguro?');
}