const Settings = {
    async load() {
        const html = await fetch('/settings').then(r => r.text());
        App.setContent(html);
        this.init();
    },
    
    init() {
        this.loadSettings();
        this.loadDockerInfo();
        this.checkUpdates();
        
        document.getElementById('settingsForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSettings();
        });
    },

    async loadSettings() {
        try {
            const res = await App.api('/settings');
            if (res.success && res.data) {
                const form = document.getElementById('settingsForm');
                for (const key in res.data) {
                    if (form.elements[key]) {
                        form.elements[key].value = res.data[key];
                    }
                }
            }
        } catch (e) {
            console.error(e);
        }
    },

    async saveSettings() {
        const form = document.getElementById('settingsForm');
        const data = {};
        new FormData(form).forEach((value, key) => data[key] = value);
        
        try {
            const res = await App.api('/settings/update', 'POST', data);
            if (res.success) App.toast('Настройки сохранены', 'success');
            else App.toast(res.message || 'Ошибка', 'error');
        } catch (e) {
            App.toast('Ошибка сохранения', 'error');
        }
    },

    async loadDockerInfo() {
        try {
            const res = await App.api('/settings/docker');
            if (res.success && res.data) {
                const info = res.data.info;
                let html = '<table class="table table-sm table-borderless">';
                html += `<tr><td class="text-muted">Версия Docker</td><td class="fw-bold">${info.server_version}</td></tr>`;
                html += `<tr><td class="text-muted">ОС</td><td class="fw-bold">${info.os}</td></tr>`;
                html += `<tr><td class="text-muted">Архитектура</td><td class="fw-bold">${info.arch}</td></tr>`;
                html += `<tr><td class="text-muted">CPU</td><td class="fw-bold">${info.cpus}</td></tr>`;
                html += `<tr><td class="text-muted">RAM</td><td class="fw-bold">${info.memory}</td></tr>`;
                html += `<tr><td class="text-muted">Контейнеры</td><td class="fw-bold">${info.containers}</td></tr>`;
                html += `<tr><td class="text-muted">Образы</td><td class="fw-bold">${info.images}</td></tr>`;
                html += `<tr><td class="text-muted">Хранилище</td><td class="fw-bold text-break">${info.docker_root}</td></tr>`;
                html += '</table>';
                
                document.getElementById('dockerInfoContainer').innerHTML = html;
            }
        } catch (e) {
            document.getElementById('dockerInfoContainer').innerHTML = '<div class="alert alert-danger">Ошибка загрузки информации</div>';
        }
    },

    async checkUpdates() {
        try {
            const res = await App.api('/settings/check_update');
            document.getElementById('updateSpinner').classList.add('d-none');
            document.getElementById('updateStatus').classList.remove('d-none');
            
            if (res.success && res.data) {
                document.getElementById('currentVersionBadge').textContent = res.data.current_version;
                document.getElementById('latestVersionBadge').textContent = res.data.latest_version;
                
                const actionArea = document.getElementById('updateActionArea');
                if (res.data.has_update) {
                    actionArea.innerHTML = `
                        <div class="alert alert-warning mb-3">Доступно новое обновление!</div>
                        <button class="btn btn-success btn-lg px-4" onclick="SettingsModule.startUpdate()"><i class="bi bi-cloud-download me-2"></i>Обновить сейчас</button>
                    `;
                } else {
                    actionArea.innerHTML = `
                        <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>У вас установлена самая актуальная версия!</div>
                    `;
                }
            } else {
                document.getElementById('updateStatus').innerHTML = '<div class="alert alert-danger">Не удалось проверить обновления</div>';
            }
        } catch (e) {
            document.getElementById('updateSpinner').classList.add('d-none');
            document.getElementById('updateStatus').classList.remove('d-none');
            document.getElementById('updateStatus').innerHTML = '<div class="alert alert-danger">Ошибка связи с GitHub</div>';
        }
    },

    async startUpdate() {
        if (!confirm('Вы уверены, что хотите обновить DockerPanel? Система будет недоступна около минуты.')) return;
        
        try {
            const res = await App.api('/settings/start_update', 'POST');
            if (res.success) {
                document.getElementById('updateActionArea').innerHTML = `
                    <div class="alert alert-info">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Обновление запущено... Пожалуйста, подождите.
                    </div>
                `;
                setTimeout(() => window.location.reload(), 30000);
            } else {
                App.toast(res.message || 'Ошибка запуска', 'error');
            }
        } catch (e) {
            App.toast('Ошибка сети', 'error');
        }
    }
};

window.Settings = Settings;
