<?php
// includes/footer.php
?>
</div> <!-- .container -->
<footer class="bg-light text-center text-muted py-3 mt-4 border-top">
    <small>Inventario Informático — &copy; By JSenen - 2025 - <script>document.write(new Date().getFullYear())</script></small>
</footer>

<script src="assets/js/app_popups.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('redesToggle');
    var menu   = document.getElementById('redesMenu');

    if (!toggle || !menu) return;

    // Abrir/cerrar al hacer clic en "Redes"
    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        menu.classList.toggle('show');
    });

    // Cerrar si se hace clic fuera
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && !toggle.contains(e.target)) {
            menu.classList.remove('show');
        }
    });
});
</script>
</body>
</html>
