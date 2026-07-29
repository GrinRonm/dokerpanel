/**
 * DockerPanel — File Manager Module
 */

const FileManager = {
    containerId: '',
    currentPath: '/',
    
    async load(cId) {
        this.containerId = cId || new URLSearchParams(window.location.search).get('id') || '';
        if (!this.containerId) { App.error('ID контейнера не указан'); return; }
        await this.browse('/');
    },

    async browse(path) {
        this.currentPath = path;
        try {
            const data = await App.get(`/files/list?id=${this.containerId}&path=${encodeURIComponent(path)}`);
            const files = data.data?.files || [];

            App.setContent(`
                <div class="fade-in">
                    <div class="page-header">
                        <div>
                            <h1 class="page-title">📁 Файлы</h1>
                            <p class="page-subtitle">${App.esc(path)}</p>
                        </div>
                        <div class="action-bar">
                            <button class="btn btn-secondary" onclick="FileManager.goUp()">⬆ Вверх</button>
                            <button class="btn btn-secondary" onclick="FileManager.createNew('file')">📄 Новый файл</button>
                            <button class="btn btn-secondary" onclick="FileManager.createNew('dir')">📁 Новая папка</button>
                            <button class="btn btn-primary" onclick="FileManager.uploadDialog()">⬆ Загрузить</button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="table-container">
                            <table>
                                <thead><tr><th>Имя</th><th>Размер</th><th>Права</th><th>Владелец</th><th>Изменён</th><th>Действия</th></tr></thead>
                                <tbody>
                                    ${files.map(f => `
                                        <tr>
                                            <td>
                                                <div class="d-flex align-center gap-1">
                                                    <span>${f.is_dir ? '📁' : (f.is_link ? '🔗' : '📄')}</span>
                                                    ${f.is_dir 
                                                        ? `<a href="#" onclick="FileManager.browse('${App.esc(f.path)}');return false" style="font-weight:500">${App.esc(f.name)}</a>`
                                                        : `<a href="#" onclick="FileManager.editFile('${App.esc(f.path)}');return false">${App.esc(f.name)}</a>`
                                                    }
                                                </div>
                                            </td>
                                            <td class="text-mono" style="font-size:12px">${f.is_dir ? '-' : f.size_formatted}</td>
                                            <td class="text-mono" style="font-size:11px">${f.permissions}</td>
                                            <td style="font-size:12px">${f.owner}</td>
                                            <td style="font-size:12px;color:var(--text-secondary)">${f.modified}</td>
                                            <td>
                                                <div class="action-buttons">
                                                    ${!f.is_dir ? `<button class="btn btn-icon btn-ghost" title="Скачать" onclick="FileManager.download('${App.esc(f.path)}')">⬇</button>` : ''}
                                                    <button class="btn btn-icon btn-ghost text-danger" title="Удалить" onclick="FileManager.deleteItem('${App.esc(f.path)}')">🗑</button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        ${!files.length ? '<div class="empty-state">Пустая директория</div>' : ''}
                    </div>
                </div>
            `);
        } catch (e) { App.error(e.message); }
    },

    goUp() {
        const parts = this.currentPath.split('/').filter(Boolean);
        parts.pop();
        this.browse('/' + parts.join('/'));
    },

    async editFile(path) {
        try {
            const data = await App.get(`/files/read?id=${this.containerId}&path=${encodeURIComponent(path)}`);
            const content = data.data?.content || '';
            const ext = path.split('.').pop().toLowerCase();
            
            let mode = 'text/plain';
            if (ext === 'js' || ext === 'json') mode = 'javascript';
            else if (ext === 'html') mode = 'htmlmixed';
            else if (ext === 'css') mode = 'css';
            else if (ext === 'php') mode = 'php';
            else if (ext === 'yml' || ext === 'yaml') mode = 'yaml';
            else if (ext === 'sh') mode = 'shell';
            else if (ext === 'xml') mode = 'xml';
            
            App.showModal(`📝 ${path.split('/').pop()}`, `
                <div style="margin-bottom:8px;font-size:12px;color:var(--text-muted)">${App.esc(path)}</div>
                <textarea id="file-editor-content" style="display:none;"></textarea>
            `, {
                width: '800px',
                confirmText: '💾 Сохранить',
                onConfirm: async () => {
                    const newContent = window.currentCodeMirror ? window.currentCodeMirror.getValue() : '';
                    try {
                        await App.post('/files/write', { id: this.containerId, path, content: newContent });
                        App.success('Файл сохранён');
                        App.closeModal();
                    } catch (e) { App.error(e.message); }
                }
            });

            // Initialize CodeMirror after modal is rendered
            setTimeout(() => {
                const textarea = document.getElementById('file-editor-content');
                textarea.value = content;
                window.currentCodeMirror = CodeMirror.fromTextArea(textarea, {
                    lineNumbers: true,
                    mode: mode,
                    theme: 'dracula',
                    indentUnit: 4,
                    lineWrapping: true,
                    matchBrackets: true
                });
                window.currentCodeMirror.setSize('100%', '450px');
            }, 100);
        } catch (e) { App.error(e.message); }
    },

    async deleteItem(path) {
        App.confirm(`Удалить ${path}?`, async () => {
            try {
                await App.post('/files/delete', { id: this.containerId, path });
                App.success('Удалено');
                this.browse(this.currentPath);
            } catch (e) { App.error(e.message); }
        });
    },

    createNew(type) {
        const label = type === 'dir' ? 'Имя папки' : 'Имя файла';
        App.showModal(type === 'dir' ? 'Новая папка' : 'Новый файл', `
            <div class="form-group">
                <label class="form-label">${label}</label>
                <input type="text" class="form-control" id="new-item-name" placeholder="${type === 'dir' ? 'my-folder' : 'file.txt'}">
            </div>
        `, {
            confirmText: 'Создать',
            onConfirm: async () => {
                const name = document.getElementById('new-item-name').value;
                if (!name) { App.error('Укажите имя'); return; }
                const fullPath = this.currentPath.replace(/\/$/, '') + '/' + name;
                try {
                    if (type === 'dir') {
                        await App.post('/files/mkdir', { id: this.containerId, path: fullPath });
                    } else {
                        await App.post('/files/write', { id: this.containerId, path: fullPath, content: '' });
                    }
                    App.success('Создано');
                    App.closeModal();
                    this.browse(this.currentPath);
                } catch (e) { App.error(e.message); }
            }
        });
    },

    download(path) {
        window.open(`/files/download?id=${this.containerId}&path=${encodeURIComponent(path)}`, '_blank');
    },

    uploadDialog() {
        App.showModal('Загрузить файл', `
            <form id="upload-form" enctype="multipart/form-data">
                <input type="hidden" name="id" value="${this.containerId}">
                <input type="hidden" name="path" value="${this.currentPath}">
                <div class="form-group">
                    <label class="form-label">Выберите файл</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
            </form>
        `, {
            confirmText: '⬆ Загрузить',
            onConfirm: async () => {
                const form = document.getElementById('upload-form');
                const formData = new FormData(form);
                try {
                    await App.api('/files/upload', { method: 'POST', body: formData });
                    App.success('Файл загружен');
                    App.closeModal();
                    this.browse(this.currentPath);
                } catch (e) { App.error(e.message); }
            }
        });
    }
};
