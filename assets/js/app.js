// assets/js/app.js

document.addEventListener('DOMContentLoaded', function () {
    // Валідація IP-адрес на клієнті в реальному часі
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

// Функція запуску симуляції пінг-тесту
function runPingTest(deviceId) {
    const statusContainer = document.getElementById(`ping-status-${deviceId}`);
    const pingBtn = document.getElementById(`ping-btn-${deviceId}`);
    
    if (!statusContainer || !pingBtn) return;

    // Встановлення статусу завантаження
    pingBtn.disabled = true;
    const originalBtnText = pingBtn.innerHTML;
    pingBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Перевірка...`;
    
    statusContainer.className = 'mt-2 text-warning';
    statusContainer.innerHTML = '<i class="bi bi-hourglass-split"></i> З\'єднання з пристроєм...';

    // AJAX-запит до actions/check_ping.php
    fetch(`actions/check_ping.php?device_id=${deviceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.status === 'online') {
                    statusContainer.className = 'mt-2 text-success fw-bold';
                    statusContainer.innerHTML = `<i class="bi bi-check-circle-fill"></i> Доступний (RTT: ${data.latency} ms)`;
                } else {
                    statusContainer.className = 'mt-2 text-danger fw-bold';
                    statusContainer.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Недоступний: ${data.message}`;
                }
            } else {
                statusContainer.className = 'mt-2 text-danger fw-bold';
                statusContainer.innerHTML = `<i class="bi bi-x-circle-fill"></i> Помилка: ${data.error}`;
            }
        })
        .catch(err => {
            statusContainer.className = 'mt-2 text-danger fw-bold';
            statusContainer.innerHTML = '<i class="bi bi-x-circle-fill"></i> Помилка підключення до сервера';
            console.error(err);
        })
        .finally(() => {
            pingBtn.disabled = false;
            pingBtn.innerHTML = originalBtnText;
        });
}
