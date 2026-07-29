const Audit = {
    async load() {
        try {
            const html = await fetch('/audit').then(r => r.text());
            App.setContent(html);
            this.loadData();
        } catch (e) {
            App.toast('Ошибка загрузки страницы', 'error');
        }
    },

    async loadData() {
        try {
            const res = await App.api('/audit/list');
            const tbody = document.getElementById('auditList');
            if (res.success && res.data.length > 0) {
                tbody.innerHTML = res.data.map(log => this.renderRow(log)).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Нет записей</td></tr>';
            }
        } catch (e) {
            document.getElementById('auditList').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Ошибка загрузки данных</td></tr>';
        }
    },

    renderRow(log) {
        return `
            <tr>
                <td class="ps-4 text-secondary" style="font-size:12px">${App.formatDate(log.created_at)}</td>
                <td class="fw-bold">${App.esc(log.username || 'Система')}</td>
                <td><span class="badge bg-secondary bg-opacity-25 text-white">${App.esc(log.action)}</span></td>
                <td>${App.esc(log.target_type)}</td>
                <td style="font-family:var(--font-mono);font-size:12px">${App.esc(log.target_id)}</td>
                <td style="font-size:13px; color:var(--text-secondary)">${App.esc(log.details)}</td>
                <td style="font-family:var(--font-mono);font-size:12px">${App.esc(log.ip_address)}</td>
            </tr>
        `;
    }
};

window.Audit = Audit;
