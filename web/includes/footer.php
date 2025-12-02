<?php
// includes/footer.php
?>
</div> <!-- .container -->
<footer class="bg-light text-center text-muted py-3 mt-4 border-top">
    <small>Inventario Informático — &copy; By JSenen -- 2025 --</small>
</footer>

<!-- Bootstrap local JS -->
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
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
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

</body>
</html>
