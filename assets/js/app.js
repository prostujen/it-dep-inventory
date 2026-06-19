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
