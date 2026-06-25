document.addEventListener('DOMContentLoaded', function () {
    const ipFields = document.querySelectorAll('.validate-ip');
    const ipv4Regex = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;

    ipFields.forEach(field => {
        field.addEventListener('input', function () {
            const val = this.value.trim();
            if (val === '') {
                this.classList.remove('ip-invalid', 'ip-valid');
                return;
            }
            if (ipv4Regex.test(val)) {
                this.classList.remove('ip-invalid');
                this.classList.add('ip-valid');
            } else {
                this.classList.remove('ip-valid');
                this.classList.add('ip-invalid');
            }
        });
    });

});

// Confirm delete device and user using event delegation and Bootstrap Modal
document.addEventListener('click', function (e) {
    const deleteDeviceBtn = e.target.closest('.delete-device-btn');
    if (deleteDeviceBtn) {
        e.preventDefault();
        const deleteUrl = deleteDeviceBtn.getAttribute('href');
        document.getElementById('deleteConfirmModalBody').innerText = 'Ви впевнені, що хочете видалити цей пристрій та всі його налаштування й логи?';
        const confirmBtn = document.getElementById('deleteConfirmModalBtn');
        confirmBtn.setAttribute('href', deleteUrl);
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
        return;
    }

    const deleteUserBtn = e.target.closest('.delete-user-btn');
    if (deleteUserBtn) {
        e.preventDefault();
        const deleteUrl = deleteUserBtn.getAttribute('href');
        document.getElementById('deleteConfirmModalBody').innerText = 'Ви впевнені, що хочете видалити цього користувача?';
        const confirmBtn = document.getElementById('deleteConfirmModalBtn');
        confirmBtn.setAttribute('href', deleteUrl);
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
        return;
    }
});

// Toggle specs edit mode
function toggleSpecsEdit() {
    const viewMode = document.getElementById('specs-view-mode');
    const editMode = document.getElementById('specs-edit-mode');
    const btn = document.getElementById('edit-specs-btn');
    
    if (viewMode.classList.contains('d-none')) {
        viewMode.classList.remove('d-none');
        editMode.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-pencil-square text-warning"></i> Редагувати';
    } else {
        viewMode.classList.add('d-none');
        editMode.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-eye text-primary"></i> Перегляд';
    }
}

// Print inventory tag tag
function printInventoryTag() {
    document.body.classList.add('printing-inventory-tag');
    window.print();
}

window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing-inventory-tag');
});

// Open ticket action modal (close/reject)
function openTicketActionModal(ticketId, action) {
    document.getElementById('modal-ticket-id').value = ticketId;
    
    // Map 'close' to 'resolve' to match the backend expectation in update_ticket.php
    const resolvedAction = action === 'close' ? 'resolve' : action;
    document.getElementById('modal-ticket-action').value = resolvedAction;
    
    const titleEl = document.getElementById('action-modal-title');
    const descEl = document.getElementById('action-modal-desc');
    const btnEl = document.getElementById('action-modal-btn');
    const commentEl = document.getElementById('modal-ticket-comment');
    
    if (commentEl) commentEl.value = '';
    
    if (action === 'reject') {
        titleEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger me-2"></i>Відхилення заявки';
        descEl.innerText = 'Вкажіть причину відхилення заявки (цей коментар буде збережено в історії пристрою).';
        btnEl.className = 'btn btn-danger btn-sm px-4';
        btnEl.innerText = 'Відхилити заявку';
    } else {
        titleEl.innerHTML = '<i class="bi bi-check-circle-fill text-success me-2"></i>Вирішення заявки';
        descEl.innerText = 'Опишіть виконані роботи по обслуговуванню пристрою для закриття заявки.';
        btnEl.className = 'btn btn-success btn-sm px-4';
        btnEl.innerText = 'Позначити як вирішену';
    }
    
    const myModal = new bootstrap.Modal(document.getElementById('actionTicketModal'));
    myModal.show();
}

// ==========================================
// EXPENSE MODULE JS — Витрати на ремонт
// ==========================================

let expenseRowIndex = 0;

/**
 * Build the HTML for one dynamic expense input row
 */
function buildExpenseRow(index) {
    return `
    <div class="expense-input-row" id="expense-row-${index}">
        <button type="button" class="btn-remove-expense-row" onclick="removeExpenseRow(${index})" title="Видалити рядок">
            <i class="bi bi-x"></i>
        </button>
        <div class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label text-muted small mb-1">Тип витрати</label>
                <select name="expense_rows[${index}][type]" class="form-select form-select-sm">
                    <option value="запчастина">🔩 Запчастина</option>
                    <option value="витратний матеріал">📦 Витратний матеріал</option>
                    <option value="послуга">🛠️ Послуга</option>
                    <option value="доставка">🚚 Доставка</option>
                    <option value="інше">📋 Інше</option>
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label text-muted small mb-1">Назва <span class="text-danger">*</span></label>
                <input type="text" name="expense_rows[${index}][name]" class="form-control form-control-sm" placeholder="Напр. SSD Kingston 480GB" required>
            </div>
            <div class="col-sm-1">
                <label class="form-label text-muted small mb-1">К-сть</label>
                <input type="number" name="expense_rows[${index}][qty]" class="form-control form-control-sm expense-qty" min="1" value="1" oninput="calcRowTotal(${index})">
            </div>
            <div class="col-sm-2">
                <label class="form-label text-muted small mb-1">Ціна (грн)</label>
                <input type="number" name="expense_rows[${index}][price]" class="form-control form-control-sm expense-price" min="0" step="0.01" value="0" oninput="calcRowTotal(${index})">
            </div>
            <div class="col-sm-2 text-center">
                <label class="form-label text-muted small mb-1">Сума</label>
                <div class="row-total" id="row-total-${index}">0.00 грн</div>
            </div>
            <div class="col-sm-3">
                <label class="form-label text-muted small mb-1">Постачальник</label>
                <input type="text" name="expense_rows[${index}][supplier]" class="form-control form-control-sm" placeholder="Rozetka, Фокстрот...">
            </div>
            <div class="col-sm-2">
                <label class="form-label text-muted small mb-1">Гарантія (міс.)</label>
                <input type="number" name="expense_rows[${index}][warranty]" class="form-control form-control-sm" min="0" value="0">
            </div>
            <div class="col-sm-2">
                <label class="form-label text-muted small mb-1">Статус оплати</label>
                <select name="expense_rows[${index}][payment]" class="form-select form-select-sm">
                    <option value="оплачено">✅ Оплачено</option>
                    <option value="очікує оплати">⏳ Очікує</option>
                    <option value="заплановано">📅 Заплановано</option>
                </select>
            </div>
            <div class="col-sm-5">
                <label class="form-label text-muted small mb-1">Чек/Накладна (JPG, PNG, PDF, ≤5МБ)</label>
                <input type="file" name="expense_rows[${index}][attachment]" class="form-control form-control-sm expense-attachment" accept=".jpg,.jpeg,.png,.pdf">
            </div>
        </div>
    </div>`;
}

/**
 * Recalculate total for a specific row
 */
function calcRowTotal(index) {
    const row = document.getElementById('expense-row-' + index);
    if (!row) return;
    const qty   = parseFloat(row.querySelector('.expense-qty')?.value || 0) || 0;
    const price = parseFloat(row.querySelector('.expense-price')?.value || 0) || 0;
    const total = qty * price;
    const totalEl = document.getElementById('row-total-' + index);
    if (totalEl) totalEl.textContent = total.toFixed(2) + ' грн';
    calcGrandTotal();
}

/**
 * Sum all row totals and display grand total
 */
function calcGrandTotal() {
    const totalEls = document.querySelectorAll('[id^="row-total-"]');
    let grand = 0;
    totalEls.forEach(el => {
        grand += parseFloat(el.textContent) || 0;
    });
    const grandEl = document.getElementById('expense-grand-total');
    if (grandEl) grandEl.textContent = grand.toFixed(2) + ' грн';
}

/**
 * Add a new expense row to the container
 */
function addExpenseRow() {
    const container = document.getElementById('expense-rows-container');
    if (!container) return;
    expenseRowIndex++;
    container.insertAdjacentHTML('beforeend', buildExpenseRow(expenseRowIndex));
}

/**
 * Remove an expense row
 */
function removeExpenseRow(index) {
    const row = document.getElementById('expense-row-' + index);
    if (row) {
        row.remove();
        calcGrandTotal();
    }
    // Show placeholder if no rows remain
    const container = document.getElementById('expense-rows-container');
    if (container && container.querySelectorAll('.expense-input-row').length === 0) {
        const placeholder = document.getElementById('expense-rows-placeholder');
        if (placeholder) placeholder.style.display = '';
    }
}

/**
 * Toggle the expense section in resolve-ticket modal
 */
function toggleExpenseSection() {
    const checkbox = document.getElementById('add-expense-checkbox');
    const section  = document.getElementById('expense-section');
    if (!checkbox || !section) return;
    if (checkbox.checked) {
        section.style.display = '';
        // Add first row automatically
        if (document.querySelectorAll('.expense-input-row').length === 0) {
            addExpenseRow();
        }
        const placeholder = document.getElementById('expense-rows-placeholder');
        if (placeholder) placeholder.style.display = 'none';
    } else {
        section.style.display = 'none';
    }
}

/**
 * Validate file inputs before form submission (size and extension)
 */
document.addEventListener('submit', function(e) {
    const form = e.target;
    const fileInputs = form.querySelectorAll('.expense-attachment');
    const maxSize    = 5 * 1024 * 1024;
    const allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];

    for (const input of fileInputs) {
        if (!input.files || input.files.length === 0) continue;
        const file = input.files[0];
        const ext  = file.name.split('.').pop().toLowerCase();
        if (!allowedExt.includes(ext)) {
            alert('Недозволений тип файлу: ' + file.name + '\nДозволено: JPG, PNG, PDF');
            e.preventDefault();
            return;
        }
        if (file.size > maxSize) {
            alert('Файл занадто великий: ' + file.name + '\nМаксимальний розмір: 5 МБ');
            e.preventDefault();
            return;
        }
    }
});

/**
 * Delete expense confirmation via shared modal
 */
document.addEventListener('click', function(e) {
    const deleteExpenseBtn = e.target.closest('.delete-expense-btn');
    if (deleteExpenseBtn) {
        e.preventDefault();
        const deleteUrl = deleteExpenseBtn.getAttribute('href');
        const modalBody = document.getElementById('deleteConfirmModalBody');
        const confirmBtn = document.getElementById('deleteConfirmModalBtn');
        if (modalBody) modalBody.innerText = 'Видалити цей запис витрати? Прикріплений файл (якщо є) також буде видалено.';
        if (confirmBtn) confirmBtn.setAttribute('href', deleteUrl);
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
    }

    // Delete device/user — existing code
    const deleteDeviceBtn = e.target.closest('.delete-device-btn');
    if (deleteDeviceBtn) {
        e.preventDefault();
        const deleteUrl = deleteDeviceBtn.getAttribute('href');
        document.getElementById('deleteConfirmModalBody').innerText = 'Ви впевнені, що хочете видалити цей пристрій та всі його налаштування й логи?';
        const confirmBtn = document.getElementById('deleteConfirmModalBtn');
        confirmBtn.setAttribute('href', deleteUrl);
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
    }

    const deleteUserBtn = e.target.closest('.delete-user-btn');
    if (deleteUserBtn) {
        e.preventDefault();
        const deleteUrl = deleteUserBtn.getAttribute('href');
        document.getElementById('deleteConfirmModalBody').innerText = 'Ви впевнені, що хочете видалити цього користувача?';
        const confirmBtn = document.getElementById('deleteConfirmModalBtn');
        confirmBtn.setAttribute('href', deleteUrl);
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
    }
});

/**
 * Print expense report (adds body class, triggers print, removes class after)
 */
function printExpenseReport() {
    document.body.classList.add('printing-expense-report');
    window.print();
}
window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing-inventory-tag');
    document.body.classList.remove('printing-expense-report');
});

/**
 * Period quick-select buttons on repair_costs.php
 */
function setDatePeriod(period) {
    const today      = new Date();
    const toStr      = d => d.toISOString().slice(0, 10);
    const fromInput  = document.getElementById('filter-date-from');
    const toInput    = document.getElementById('filter-date-to');
    if (!fromInput || !toInput) return;

    document.querySelectorAll('.btn-period').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector('.btn-period[data-period="' + period + '"]');
    if (btn) btn.classList.add('active');

    if (period === 'month') {
        const first = new Date(today.getFullYear(), today.getMonth(), 1);
        fromInput.value = toStr(first);
        toInput.value   = toStr(today);
    } else if (period === 'year') {
        fromInput.value = today.getFullYear() + '-01-01';
        toInput.value   = toStr(today);
    } else if (period === 'all') {
        fromInput.value = '';
        toInput.value   = '';
    }
}

