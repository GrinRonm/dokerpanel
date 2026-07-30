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
                                    <th>Имя / Образ</th>
                                    <th>Статус</th>
                                    <th>Порты</th>
                                    <th>Сеть</th>
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
        if (c.is_pending) {
            return `
                <tr>
                    <td colspan="3">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm text-primary me-3" role="status"></div>
                            <div>
                                <div class="fw-bold text-white mb-1">${App.esc(c.name)}</div>
                                <div style="font-size: 12px; color: var(--text-secondary)">${App.esc(c.image)}</div>
                            </div>
                        </div>
                    </td>
                    <td colspan="4" class="text-start">
                        <span class="badge bg-primary bg-opacity-25 text-primary">${App.esc(c.status)}</span>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Пожалуйста, подождите...</div>
                    </td>
                </tr>
            `;
        }

        const stateColors = {
            running: 'bg-success text-success',
            exited: 'bg-danger text-danger',
            created: 'bg-warning text-warning'
        };
        const badgeColor = stateColors[c.state] || 'bg-secondary text-secondary';
        
        return `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="status-indicator ${c.state === 'running' ? 'active' : ''} me-3"></div>
                        <div>
                            <div class="fw-bold text-white mb-1" style="cursor:pointer" onclick="Containers.detail('${c.id}')">${App.esc(c.name)}</div>
                            <div style="font-size: 12px; color: var(--text-secondary)">${App.esc(c.image)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge ${badgeColor} bg-opacity-25 px-2 py-1">${App.esc(c.state)}</span>
                </td>
                <td style="font-family: var(--font-mono); font-size: 13px;">
                    ${c.ports.length > 0 ? c.ports.map(p => `<div class="text-info">${App.esc(p)}</div>`).join('') : '<span class="text-muted">-</span>'}
                </td>
                <td style="font-family: var(--font-mono); font-size: 13px;">
                    ${c.network.length > 0 ? c.network.join(', ') : '<span class="text-muted">-</span>'}
                </td>
                <td style="font-size: 13px;">
                    <div>RW: <span class="text-white">${c.size_rw}</span></div>
                    <div class="text-muted">Root: ${c.size_root}</div>
                </td>
                <td style="font-size: 12px; color: var(--text-secondary)">
                    ${App.formatDate(c.created)}
                </td>
                <td>
                    <div class="action-buttons d-flex align-items-center gap-1">
                        ${c.state === 'running' ? `
                            <button class="btn btn-icon btn-ghost" title="Терминал" onclick="Containers.openTerminal('${c.id}')">🖥</button>
                            <button class="btn btn-icon btn-ghost" title="Файлы" onclick="Containers.openFiles('${c.id}')">📁</button>
                            <button class="btn btn-icon btn-ghost" title="Остановить" onclick="Containers.action('${c.id}','stop')">⏹</button>
                            <button class="btn btn-icon btn-ghost" title="Перезапустить" onclick="Containers.action('${c.id}','restart')">🔄</button>
                        ` : `
                            <button class="btn btn-icon btn-ghost" title="Запустить" onclick="Containers.action('${c.id}','start')">▶</button>
                        `}
                        <button class="btn btn-icon btn-ghost" title="Логи" onclick="Containers.logs('${c.id}')">📄</button>
                        <button class="btn btn-icon btn-ghost" title="Редактировать" onclick="Containers.edit('${c.id}')">✏️</button>
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
        TerminalModule.showModal(id);
    },

    openFiles(id) {
        App.navigateTo(`files?id=${id}`);
    },

    async logs(id, autoRefresh = false) {
        try {
            const data = await App.get(`/containers/logs?id=${id}`);
            const logs = data.data.logs || 'Логи пусты.';
            
            // Format logs to have some color or just better padding
            App.showModal('Логи контейнера', `
                <div class="bg-black text-white p-3 rounded" style="max-height: 500px; overflow-y: auto; overflow-x: auto; white-space: pre; font-size: 13px; font-family: 'Consolas', 'Courier New', monospace; line-height: 1.4; border: 1px solid var(--bg-glass-border);" id="logs-container">${App.esc(logs)}</div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <label class="d-flex align-items-center gap-2 m-0" style="cursor:pointer; user-select:none;">
                        <input type="checkbox" id="logs-auto-refresh" class="form-check-input mt-0" ${autoRefresh ? 'checked' : ''} onchange="Containers.toggleLogsAutoRefresh('${id}', this.checked)">
                        <span class="text-secondary" style="font-size:13px">Автообновление (3 сек)</span>
                    </label>
                    <button class="btn btn-primary" onclick="Containers.logs('${id}', document.getElementById('logs-auto-refresh')?.checked)"><i class="bi bi-arrow-clockwise me-1"></i>Обновить</button>
                </div>
            `, 'lg');

            // Scroll to bottom
            const container = document.getElementById('logs-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        } catch (e) {
            App.error(e.message);
        }
    },

    toggleLogsAutoRefresh(id, enabled) {
        if (this._logsInterval) clearInterval(this._logsInterval);
        if (enabled) {
            this._logsInterval = setInterval(() => {
                // Check if modal is still open and checkbox is checked
                const cb = document.getElementById('logs-auto-refresh');
                if (cb && cb.checked) {
                    this.logs(id, true);
                } else {
                    clearInterval(this._logsInterval);
                }
            }, 3000);
        }
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
    async edit(id) {
        try {
            const res = await App.get('/containers/detail?id=' + id);
            if (res && res.data) {
                // Если есть db_id и config, редактируем
                const config = res.data.config || {};
                config._db_id = res.data.db_id;
                config._name = res.data.name;
                this.showCreateForm(config);
            }
        } catch (e) {
            App.error('Ошибка получения контейнера: ' + e.message);
        }
    },

    async showCreateForm(editConfig = null) {
        let templatesOptions = '<option value="">-- Не использовать шаблон --</option>';
        try {
            const res = await App.get('/templates');
            if (res && res.data) {
                // Save globally so we can use the config on change
                window._loadedTemplates = res.data;
                res.data.forEach(t => {
                    templatesOptions += `<option value="${t.id}">${App.esc(t.name)}</option>`;
                });
            }
        } catch(e) {
            console.error('Failed to load templates', e);
        }

        App.setContent(`
            <div class="fade-in">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">${editConfig ? 'Редактировать контейнер' : 'Создать контейнер'}</h1>
                        <p class="page-subtitle">${editConfig ? 'Измените параметры и сохраните (контейнер будет пересоздан)' : 'Настройте параметры нового контейнера'}</p>
                    </div>
                    <button class="btn btn-secondary" onclick="App.navigateTo('containers')">← Назад</button>
                </div>

                <div class="card" style="max-width:800px">
                    <form id="create-container-form" onsubmit="Containers.createSubmit(event, ${editConfig ? editConfig._db_id : 'null'})">
                        
                        ${!editConfig ? `
                        <div class="form-group mb-3 pb-3" style="border-bottom: 1px solid var(--border-color)">
                            <label class="form-label">Выбрать готовый шаблон</label>
                            <select class="form-control" id="template-select" onchange="Containers.applyTemplate(this.value)">
                                ${templatesOptions}
                            </select>
                            <small class="text-muted">Выбор шаблона автоматически заполнит поля ниже.</small>
                        </div>
                        ` : ''}

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
                            <button type="submit" class="btn btn-primary btn-lg">🚀 ${editConfig ? 'Сохранить изменения' : 'Создать контейнер'}</button>
                        </div>
                    </form>
                </div>
            </div>
        `);

        if (editConfig) {
            const form = document.getElementById('create-container-form');
            form.name.value = editConfig._name || '';
            form.image.value = (editConfig.image || '').split(':')[0] || '';
            form.tag.value = (editConfig.image || '').split(':')[1] || 'latest';
            form.cmd.value = editConfig.cmd || '';
            form.cpu.value = editConfig.cpu || '1';
            form.ram.value = editConfig.ram || '512m';
            form.restart.value = editConfig.restart || 'unless-stopped';
            form.privileged.checked = !!editConfig.privileged;
            if (editConfig.network) form.network.value = editConfig.network;

            // Заполняем динамические поля
            const portsContainer = document.getElementById('ports-container');
            portsContainer.innerHTML = '';
            if (editConfig.ports && editConfig.ports.length) {
                editConfig.ports.forEach(p => this.addPortRow(p.host || '', p.container || ''));
            } else {
                this.addPortRow();
            }

            const envContainer = document.getElementById('env-container');
            envContainer.innerHTML = '';
            if (editConfig.env && editConfig.env.length) {
                editConfig.env.forEach(e => this.addEnvRow(e.name || '', e.value || ''));
            } else {
                this.addEnvRow();
            }

            const volumesContainer = document.getElementById('volumes-container');
            volumesContainer.innerHTML = '';
            if (editConfig.volumes && editConfig.volumes.length) {
                editConfig.volumes.forEach(v => this.addVolRow(v.host || '', v.container || ''));
            } else {
                this.addVolRow();
            }
            
            // Сохраняем скрытые поля
            window._editTmpfs = editConfig.tmpfs || [];
            window._editCgroupns = editConfig.cgroupns || '';
        } else {
            window._editTmpfs = null;
            window._editCgroupns = null;
        }
    },

    applyTemplate(id) {
        if (!id) return;
        const form = document.getElementById('create-container-form');
        const t = (window._loadedTemplates || []).find(x => x.id == id);
        if (!t) return;

        form.name.value = t.slug + '-' + Math.floor(Math.random() * 1000);
        form.image.value = t.image;
        form.tag.value = t.default_tag || 'latest';
        
        const c = t.config || {};
        form.cmd.value = c.cmd || '';
        form.cpu.value = c.cpu || '1';
        form.ram.value = c.ram || '512m';
        form.restart.value = c.restart || 'unless-stopped';
        form.privileged.checked = !!c.privileged;

        // Clear dynamic fields
        document.getElementById('ports-container').innerHTML = '';
        document.getElementById('env-container').innerHTML = '';
        document.getElementById('volumes-container').innerHTML = '';

        // Populate dynamic fields
        if (c.ports && c.ports.length) {
            c.ports.forEach(p => this.addPortRow(p.host || '', p.container || ''));
        } else {
            this.addPortRow();
        }

        if (c.env && c.env.length) {
            c.env.forEach(e => this.addEnvRow(e.name || '', e.value || ''));
        } else {
            this.addEnvRow();
        }

        if (c.volumes && c.volumes.length) {
            c.volumes.forEach(v => this.addVolRow(v.host || '', v.container || ''));
        } else {
            this.addVolRow();
        }
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

    addPortRow(host = '', containerVal = '') {
        const container = document.getElementById('ports-container');
        const row = document.createElement('div');
        row.className = 'form-row mb-1';
        row.innerHTML = `
            <input type="text" class="form-control" placeholder="Хост" data-port="host" value="${App.esc(host)}">
            <input type="text" class="form-control" placeholder="Контейнер" data-port="container" value="${App.esc(containerVal)}">
            <button type="button" class="btn btn-ghost text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    },

    addEnvRow(name = '', value = '') {
        const container = document.getElementById('env-container');
        const row = document.createElement('div');
        row.className = 'form-row mb-1';
        row.innerHTML = `
            <input type="text" class="form-control" placeholder="Имя" data-env="name" value="${App.esc(name)}">
            <input type="text" class="form-control" placeholder="Значение" data-env="value" value="${App.esc(value)}">
            <button type="button" class="btn btn-ghost text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    },

    addVolRow(host = '', containerVal = '') {
        const container = document.getElementById('volumes-container');
        const row = document.createElement('div');
        row.className = 'form-row mb-1';
        row.innerHTML = `
            <input type="text" class="form-control" placeholder="Путь на хосте" data-vol="host" value="${App.esc(host)}">
            <input type="text" class="form-control" placeholder="Путь в контейнере" data-vol="container" value="${App.esc(containerVal)}">
            <button type="button" class="btn btn-ghost text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    },

    async createSubmit(e, editId = null) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('[type="submit"]');
        const originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner"></div> ' + (editId ? 'Сохранение...' : 'Создание (может занять время)...');

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

        let tmpfs = window._editTmpfs !== null ? window._editTmpfs : [];
        let cgroupns = window._editCgroupns !== null ? window._editCgroupns : '';

        const templateSelect = document.getElementById('template-select');
        if (templateSelect && templateSelect.value) {
            const t = (window._loadedTemplates || []).find(x => x.id == templateSelect.value);
            if (t && t.config) {
                tmpfs = t.config.tmpfs || [];
                cgroupns = t.config.cgroupns || '';
            }
        }

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
            ports, env, volumes, tmpfs, cgroupns
        };

        if (editId) body.id = editId;

        try {
            if (editId) {
                const res = await App.post('/containers/update', body);
                App.success(res.message || 'Контейнер обновлён!');
            } else {
                const res = await App.post('/containers/create', body);
                App.success(res.message || 'Процесс создания запущен!');
            }
            App.navigateTo('containers');
        } catch (e) {
            App.error(e.message);
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
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
