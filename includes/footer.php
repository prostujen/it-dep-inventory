    </div>
    <footer class="footer mt-auto py-3 no-print">
        <div class="container text-center">
            <span>© Центр інформаційних систем 2026</span>
        </div>
    </footer>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <div class="modal-header modal-header-custom py-2 px-3">
                    <h5 class="modal-title fs-6 text-gradient d-flex align-items-center gap-2" id="deleteConfirmModalLabel">
                        <i class="bi bi-exclamation-triangle-fill text-warning"></i> Підтвердження видалення
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 text-center">
                    <div class="fs-5 text-dark fw-semibold mb-2" id="deleteConfirmModalBody">Ви впевнені, що хочете видалити цей пристрій та всі його налаштування й логи?</div>
                    <div class="small text-muted">Цю дію неможливо скасувати!</div>
                </div>
                <div class="modal-footer modal-footer-custom py-2 px-3">
                    <button type="button" class="btn btn-sm btn-custom-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <a href="#" id="deleteConfirmModalBtn" class="btn btn-sm btn-danger fw-semibold px-3 text-white d-inline-flex align-items-center gap-2">
                        <i class="bi bi-trash3-fill"></i> Видалити
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
