/**
 * DockerPanel — Images Module
 */
const Images = {
    async load() {
        const data = await App.get('/images/list');
        const images = data.data || [];
        App.setContent(`<div class="fade-in">
            <div class="page-header">
                <div><h1 class="page-title">Docker Images</h1><p class="page-subtitle">Всего: ${images.length}</p></div>
                <div class="action-bar">
                    <button class="btn btn-primary" onclick="Images.pullDialog()">⬇ Скачать образ</button>
                    <button class="btn btn-secondary" onclick="Images.searchDialog()">🔍 Поиск Docker Hub</button>
                </div>
            </div>
            <div class="card"><div class="table-container"><table>
                <thead><tr><th>Образ</th><th>ID</th><th>Размер</th><th>Создан</th><th>Действия</th></tr></thead>
                <tbody>${images.map(i => `<tr>
                    <td><strong>${App.esc(i.name)}</strong></td>
                    <td class="text-mono" style="font-size:11px">${i.short_id}</td>
                    <td>${i.size_formatted}</td>
                    <td style="font-size:12px">${App.formatDate(i.created)}</td>
                    <td><button class="btn btn-sm btn-ghost text-danger" onclick="Images.remove('${App.esc(i.id)}')">🗑</button></td>
                </tr>`).join('')}</tbody>
            </table></div></div></div>`);
    },
    pullDialog() {
        App.showModal('Скачать образ', `<div class="form-group"><label class="form-label">Имя образа</label><input class="form-control" id="pull-image" placeholder="nginx"></div><div class="form-group"><label class="form-label">Tag</label><input class="form-control" id="pull-tag" value="latest"></div>`, {
            confirmText: '⬇ Скачать', onConfirm: async () => {
                const name = document.getElementById('pull-image').value;
                const tag = document.getElementById('pull-tag').value || 'latest';
                if (!name) return App.error('Укажите имя');
                try { App.info('Скачивание...'); await App.post('/images/pull', {name, tag}); App.success('Образ скачан'); App.closeModal(); this.load(); } catch(e) { App.error(e.message); }
            }
        });
    },
    searchDialog() {
        App.showModal('Поиск Docker Hub', `<div class="form-group"><label class="form-label">Поисковый запрос</label><input class="form-control" id="search-query" placeholder="nginx"><button class="btn btn-primary mt-1" onclick="Images.doSearch()">Найти</button></div><div id="search-results"></div>`, { width: '700px' });
    },
    async doSearch() {
        const q = document.getElementById('search-query').value;
        if (!q) return;
        try {
            const data = await App.get(`/images/search?q=${encodeURIComponent(q)}`);
            document.getElementById('search-results').innerHTML = (data.data||[]).map(r => `<div style="padding:8px 0;border-bottom:1px solid var(--bg-glass-border)"><strong>${App.esc(r.name)}</strong> ⭐${r.star_count||0}<br><span style="font-size:12px;color:var(--text-secondary)">${App.esc(r.description||'').substring(0,100)}</span><br><button class="btn btn-sm btn-primary mt-1" onclick="document.getElementById('pull-image')&&(document.getElementById('pull-image').value='${App.esc(r.name)}');App.closeModal();Images.pullDialog()">Скачать</button></div>`).join('') || '<p>Ничего не найдено</p>';
        } catch(e) { App.error(e.message); }
    },
    async remove(id) {
        App.confirm('Удалить образ?', async () => {
            try { await App.post('/images/remove', {id}); App.success('Удалён'); this.load(); } catch(e) { App.error(e.message); }
        });
    }
};
