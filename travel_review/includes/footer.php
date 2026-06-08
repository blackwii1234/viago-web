    </div>

    <!-- 토스트 알림 -->

    <div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index:99999;">
        <div id="viagoToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="viagoToastBody"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

    <!-- jsVectorMap -->

    <script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>

    <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
    function showToast(message, type) {
        type = type || 'success';
        var el   = document.getElementById('viagoToast');
        var body = document.getElementById('viagoToastBody');
        if (!el || !body) return;
        body.textContent = message;
        el.className = 'toast align-items-center border-0 text-bg-' + type;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 3000 }).show();
    }
    </script>
</body>
</html>