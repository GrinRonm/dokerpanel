/**
 * DockerPanel — Containers Module
 */

const Containers = {
    async load(page) {
        if (page === 'containers/create') return this.showCreateForm();
        if (page === 'containers/terminal') return this.showTerminal();
        
        const data = await App.get('/containers/list');
        const containers = data.data || [];

        App.setContent(`
            <div class="fade-in">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Контейнеры</h1>
                        <p class="page-subtitle">Всего: ${containers.length}</p>
                    </div>
                    <div class="action-bar">
                        <button class="btn btn-primary" onclick="App.navigateTo('containers/create')">＋ Создать</button>
                        <button class="btn btn-secondary" onclick="App.navigateTo('templates')">📋 Шаблоны</button>
                        <button class="btn btn-secondary" onclick="Containers.importExisting()">📥 Импорт</button>
                    </div>
                </div>

                <div class="card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Имя</th>
                                    <th>Статус</th>
                                    <th>Образ</th>
                                    <th>Порты</th>
                                    <th>Размер</th>
                                    <th>Создан</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="containers-table">
                                ${containers.map(c => this.renderRow(c)).join('')}
                            </tbody>
                        </table>
                    </div>
                    ${!containers.length ? '<div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-text">Нет контейнеров</div><button class="btn btn-primary mt-2" onclick="App.navigateTo(\'containers/create\')">Создать первый контейнер</button></div>' : ''}
                </div>
            </div>
        `);

        App.addInterval(() => this.refresh(), 5000);
    },

    renderRow(c) {
        const stateClass = c.state === 'running' ? 'running' : (c.state === 'exited' ? 'stopped' : c.state);
        const ports = (c.ports || []).join(', ') || '-';
        return `
            <tr>
                <td>
                    <a href="#" onclick="Containers.showDetail('${c.id}');return false" style="font-weight:600;color:var(--text-white)">
                        ${App.esc(c.name)}
                    </a>
                    <div class="text-muted" style="font-size:11px;font-family:var(--font-mono)">${c.short_id}</div>
                </td>
                <td><span class="badge badge-${stateClass}"><span class="badge-dot"></span>${c.state}</span></td>
                <td><span class="tag">${App.esc(c.image)}</span></td>
                <td style="font-family:var(--font-mono);font-size:12px">${App.esc(ports)}</td>
                <td>${c.size_rw}</td>
                <td style="font-size:12px;color:var(--text-secondary)">${App.formatDate(c.created)}</td>
                <td>
                    <div class="action-buttons">
                        ${c.state === 'running' ? `
                            <button class="btn btn-icon btn-ghost" title="Терминал" onclick="Containers.openTerminal('${c.id}')">🖥</button>
                            <button class="btn btn-icon btn-ghost" title="Файлы" onclick="Containers.openFiles('${c.id}')">📁</button>
                            <button class="btn btn-icon btn-ghost" title="Мониторинг" onclick="Monitor.render('${c.id}')">📊</button>
                            <button class="btn btn-icon btn-ghost" title="Остановить" onclick="Containers.action('${c.id}','stop')">⏹</button>
                            <button class="btn btn-icon btn-ghost" title="Перезапустить" onclick="Containers.action('${c.id}','restart')">🔄</button>
                        ` : `
                            <button class="btn btn-icon btn-ghost" title="Запустить" onclick="Containers.action('${c.id}','start')">▶</button>
                        `}
                        <button class="btn btn-icon btn-ghost text-danger" title="Удалить" onclick="Containers.action('${c.id}','remove')">🗑</button>
                    </div>
                </td>
            </tr>
        `;
    },

    async action(id, action) {
        if (action === 'remove') {
            App.confirm('Удалить контейнер? Это действие необратимо.', async () => {
                try {
                    await App.post(`/containers/${action}`, { id });
                    App.success(`Контейнер удалён`);
                    this.load('containers');
                } catch (e) { App.error(e.message); }
            });
            return;
        }
        try {
            await App.post(`/containers/${action}`, { id });
            App.success(`Контейнер: ${action}`);
            this.load('containers');
        } catch (e) { App.error(e.message); }
    },

    openTerminal(id) {
        window.open(`/containers/terminal?id=${id}`, '_blank');
    },

    openFiles(id) {
        App.navigateTo(`files?id=${id}`);
    },

    async showDetail(id) {
        try {
            const data = await App.get(`/containers/detail?id=${id}`);
            const c = data.data;
            App.showModal(c.name, `
                <div class="grid-2 gap-2">
                    <div><strong>ID:</strong> <span class="text-mono">${c.id?.substring(0,12)}</span></div>
                    <div><strong>Состояние:</strong> <span class="badge badge-${c.running ? 'running' : 'stopped'}">${c.state}</span></div>
                    <div><strong>Образ:</strong> ${App.esc(c.image)}</div>
                    <div><strong>Запущен:</strong> ${App.formatDate(c.started_at)}</div>
                    <div><strong>CPU:</strong> ${c.cpu_nano ? (c.cpu_nano / 1e9).toFixed(1) + ' cores' : 'Без лимита'}</div>
                    <div><strong>RAM:</strong> ${c.memory_limit_formatted || 'Без лимита'}</div>
                    <div><strong>Restart:</strong> ${c.restart_policy}</div>
                    <div><strong>Привилегированный:</strong> ${c.privileged ? 'Да' : 'Нет'}</div>
                </div>
                <h4 class="mt-2 mb-1">Порты</h4>
                <div>${(c.ports || []).map(p => `<span class="tag">${p}</span> `).join('') || '-'}</div>
                <h4 class="mt-2 mb-1">Сети</h4>
                ${(c.networks || []).map(n => `<div class="tag">${n.name}: ${n.ip}</div>`).join(' ') || '-'}
                <h4 class="mt-2 mb-1">Переменные окружения</h4>
                <div style="max-height:150px;overflow:auto;font-size:12px;font-family:var(--font-mono)">
                    ${(c.env || []).map(e => `<div style="padding:2px 0">${App.esc(e)}</div>`).join('') || '-'}
                </div>
            `, { width: '700px' });
        } catch (e) { App.error(e.message); }
    },

    async showCreateForm() {
        App.setContent(`
            <div class="fade-in">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Создать контейнер</h1>
                        <p class="page-subtitle">Настройте параметры нового контейнера</p>
                    </div>
                    <button class="btn btn-secondary" onclick="App.navigateTo('containers')">← Назад</button>
                </div>

                <div class="card" style="max-width:800px">
                    <form id="create-container-form" onsubmit="Containers.createSubmit(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Имя контейнера *</label>
                                <input type="text" class="form-control" name="name" required placeholder="my-container">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Docker Image *</label>
                                <input type="text" class="form-control" name="image" required placeholder="ubuntu" value="ubuntu">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Версия (Tag)</label>
                                <input type="text" class="form-control" name="tag" placeholder="latest" value="latest">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Команда</label>
                                <input type="text" class="form-control" name="cmd" placeholder="/bin/bash">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">CPU (ядра)</label>
                                <input type="text" class="form-control" name="cpu" placeholder="1" value="1">
                            </div>
                            <div class="form-group">
                                <label class="form-label">RAM</label>
                                <input type="text" class="form-control" name="ram" placeholder="512m" value="512m">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Проброс портов</label>
                            <div id="ports-container">
                                <div class="form-row mb-1">
                                    <input type="text" class="form-control" placeholder="Хост (например, 8080)" data-port="host">
                                    <input type="text" class="form-control" placeholder="Контейнер (например, 80)" data-port="container">
                                    <button type="button" class="btn btn-ghost" onclick="Containers.addPortRow()">＋</button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Переменные окружения</label>
                            <div id="env-container">
                                <div class="form-row mb-1">
                                    <input type="text" class="form-control" placeholder="Имя" data-env="name">
                                    <input type="text" class="form-control" placeholder="Значение" data-env="value">
                                    <button type="button" class="btn btn-ghost" onclick="Containers.addEnvRow()">＋</button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Volumes</label>
                            <div id="volumes-container">
                                <div class="form-row mb-1">
                                    <input type="text" class="form-control" placeholder="Путь на хосте" data-vol="host">
                                    <input type="text" class="form-control" placeholder="Путь в контейнере" data-vol="container">
                                    <button type="button" class="btn btn-ghost" onclick="Containers.addVolRow()">＋</button>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Сеть</label>
                                <select class="form-control" name="network">
                                    <option value="">По умолчанию (bridge)</option>
                                    <option value="host">Host</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Restart Policy</label>
                                <select class="form-control" name="restart">
                                    <option value="unless-stopped">Unless Stopped</option>
                                    <option value="always">Always</option>
                                    <option value="on-failure">On Failure</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                                <input type="checkbox" name="privileged"> Привилегированный режим
                            </label>
                        </div>

                        <div class="d-flex justify-between mt-3">
                            <button type="button" class="btn btn-secondary" onclick="App.navigateTo('containers')">Отмена</button>
                            <button type="submit" class="btn btn-primary btn-lg">🚀 Создать контейнер</button>
                        </div>
                    </form>
                </div>
            </div>
        `);
    },

    async showTerminal() {
        const id = new URLSearchParams(window.location.search).get('id');
        App.setContent(`
            <div class="fade-in" style="height: 100%; display: flex; flex-direction: column;">
                <div class="page-header" style="margin-bottom: 12px;">
                    <div>
                        <h1 class="page-title">Терминал контейнера</h1>
                        <p class="page-subtitle text-mono" id="term-container-id">${App.esc(id)}</p>
                    </div>
                    <div class="action-bar">
                        <button class="btn btn-secondary" onclick="TerminalModule.toggleFullscreen()">⛶ На весь экран</button>
                        <button class="btn btn-danger" onclick="window.close()">Закрыть</button>
                    </div>
                </div>

                <div class="terminal-container" style="flex: 1; height: auto;">
                    <div class="terminal-header">
                        <div class="terminal-title">
                            <span style="color:var(--accent-primary)">root</span>@<span id="term-host">container</span>:~#
                        </div>
                        <div class="terminal-dots">
                            <div class="terminal-dot red"></div>
                            <div class="terminal-dot yellow"></div>
                            <div class="terminal-dot green"></div>
                        </div>
                    </div>
                    <div id="terminal-view"></div>
                </div>
            </div>
        `);
        setTimeout(() => TerminalModule.init(id), 100);
    },

    addPortRow() {
        const container = document.getElementById('ports-container');
        const row = document.createElement('div');
        row.className = 'form-row mb-1';
        row.innerHTML = `
            <input type="text" class="form-control" placeholder="Хост" data-port="host">
            <input type="text" class="form-control" placeholder="Контейнер" data-port="container">
            <button type="button" class="btn btn-ghost text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    },

    addEnvRow() {
        const container = document.getElementById('env-container');
        const row = document.createElement('div');
        row.className = 'form-row mb-1';
        row.innerHTML = `
            <input type="text" class="form-control" placeholder="Имя" data-env="name">
            <input type="text" class="form-control" placeholder="Значение" data-env="value">
            <button type="button" class="btn btn-ghost text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    },

    addVolRow() {
        const container = document.getElementById('volumes-container');
        const row = document.createElement('div');
        row.className = 'form-row mb-1';
        row.innerHTML = `
            <input type="text" class="form-control" placeholder="Путь на хосте" data-vol="host">
            <input type="text" class="form-control" placeholder="Путь в контейнере" data-vol="container">
            <button type="button" class="btn btn-ghost text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    },

    async createSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner"></div> Создание...';

        // Собираем порты
        const ports = [];
        document.querySelectorAll('#ports-container .form-row').forEach(row => {
            const host = row.querySelector('[data-port="host"]')?.value;
            const container = row.querySelector('[data-port="container"]')?.value;
            if (container) ports.push({ host: host || '', container });
        });

        // Переменные окружения
        const env = [];
        document.querySelectorAll('#env-container .form-row').forEach(row => {
            const name = row.querySelector('[data-env="name"]')?.value;
            const value = row.querySelector('[data-env="value"]')?.value;
            if (name) env.push({ name, value: value || '' });
        });

        // Volumes
        const volumes = [];
        document.querySelectorAll('#volumes-container .form-row').forEach(row => {
            const host = row.querySelector('[data-vol="host"]')?.value;
            const container = row.querySelector('[data-vol="container"]')?.value;
            if (container) volumes.push({ host: host || '', container });
        });

        const body = {
            name: form.name.value,
            image: form.image.value,
            tag: form.tag.value || 'latest',
            cmd: form.cmd.value,
            cpu: form.cpu.value || '1',
            ram: form.ram.value || '512m',
            network: form.network.value,
            restart: form.restart.value,
            privileged: form.privileged.checked,
            ports, env, volumes,
        };

        try {
            await App.post('/containers/create', body);
            App.success('Контейнер создан!');
            App.navigateTo('containers');
        } catch (e) {
            App.error(e.message);
            btn.disabled = false;
            btn.innerHTML = '🚀 Создать контейнер';
        }
    },

    async importExisting() {
        try {
            const data = await App.post('/containers/import');
            App.success(data.message);
            if (App.currentPage === 'containers') this.load('containers');
        } catch (e) { App.error(e.message); }
    },

    async refresh() {
        try {
            const data = await App.get('/containers/list');
            const tbody = document.getElementById('containers-table');
            if (tbody && data.data) {
                tbody.innerHTML = data.data.map(c => this.renderRow(c)).join('');
            }
        } catch (e) {}
    }
};
