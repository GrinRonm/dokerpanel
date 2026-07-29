/**
 * DockerPanel — Networks Module
 */
const Networks = {
    async load() {
        const data = await App.get('/networks/list');
        const nets = data.data || [];
        App.setContent(`<div class="fade-in"><div class="page-header"><div><h1 class="page-title">Сети Docker</h1></div><button class="btn btn-primary" onclick="Networks.createDialog()">＋ Создать сеть</button></div>
        <div class="card"><div class="table-container"><table><thead><tr><th>Имя</th><th>Драйвер</th><th>Подсеть</th><th>Шлюз</th><th>Контейнеры</th><th>Действия</th></tr></thead>
        <tbody>${nets.map(n => `<tr><td><strong>${App.esc(n.name)}</strong></td><td><span class="tag">${n.driver}</span></td><td class="text-mono" style="font-size:12px">${n.subnet}</td><td class="text-mono" style="font-size:12px">${n.gateway}</td><td>${(n.containers||[]).map(c=>`<span class="tag">${App.esc(c.name)}</span>`).join(' ')}</td><td>${!['bridge','host','none'].includes(n.name)?`<button class="btn btn-sm btn-ghost text-danger" onclick="Networks.remove('${n.id}')">🗑</button>`:''}</td></tr>`).join('')}</tbody></table></div></div></div>`);
    },
    createDialog() {
        App.showModal('Создать сеть', `<div class="form-group"><label class="form-label">Имя</label><input class="form-control" id="net-name" placeholder="my-network"></div><div class="form-group"><label class="form-label">Драйвер</label><select class="form-control" id="net-driver"><option value="bridge">Bridge</option><option value="host">Host</option><option value="macvlan">Macvlan</option><option value="overlay">Overlay</option></select></div><div class="form-group"><label class="form-label">Подсеть (необязательно)</label><input class="form-control" id="net-subnet" placeholder="172.20.0.0/16"></div>`, {
            confirmText: 'Создать', onConfirm: async () => {
                try { await App.post('/networks/create', { name: document.getElementById('net-name').value, driver: document.getElementById('net-driver').value, subnet: document.getElementById('net-subnet').value }); App.success('Сеть создана'); App.closeModal(); this.load(); } catch(e) { App.error(e.message); }
            }
        });
    },
    async remove(id) { App.confirm('Удалить сеть?', async()=>{ try { await App.post('/networks/remove',{id}); App.success('Удалена'); this.load(); } catch(e){ App.error(e.message); }}); }
};

/**
 * DockerPanel — Volumes Module
 */
const Volumes = {
    async load() {
        const data = await App.get('/volumes/list');
        const vols = data.data || [];
        App.setContent(`<div class="fade-in"><div class="page-header"><div><h1 class="page-title">Docker Volumes</h1></div><div class="action-bar"><button class="btn btn-primary" onclick="Volumes.createDialog()">＋ Создать</button><button class="btn btn-warning" onclick="Volumes.prune()">🧹 Очистить неиспользуемые</button></div></div>
        <div class="card"><div class="table-container"><table><thead><tr><th>Имя</th><th>Драйвер</th><th>Размер</th><th>Путь</th><th>Действия</th></tr></thead>
        <tbody>${vols.map(v => `<tr><td><strong>${App.esc(v.name)}</strong></td><td><span class="tag">${v.driver}</span></td><td>${v.size_formatted}</td><td class="text-mono" style="font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis">${App.esc(v.mountpoint)}</td><td><button class="btn btn-sm btn-ghost text-danger" onclick="Volumes.remove('${App.esc(v.name)}')">🗑</button></td></tr>`).join('')}</tbody></table></div></div></div>`);
    },
    createDialog() {
        App.showModal('Создать Volume', `<div class="form-group"><label class="form-label">Имя</label><input class="form-control" id="vol-name" placeholder="my-volume"></div>`, {
            confirmText: 'Создать', onConfirm: async () => {
                try { await App.post('/volumes/create', {name:document.getElementById('vol-name').value}); App.success('Volume создан'); App.closeModal(); this.load(); } catch(e) { App.error(e.message); }
            }
        });
    },
    async remove(name) { App.confirm(`Удалить volume ${name}?`, async()=>{ try { await App.post('/volumes/remove',{name}); App.success('Удалён'); this.load(); } catch(e){ App.error(e.message); }}); },
    async prune() { App.confirm('Удалить все неиспользуемые volumes?', async()=>{ try { await App.post('/volumes/prune'); App.success('Очищено'); this.load(); } catch(e){ App.error(e.message); }}); }
};

/**
 * DockerPanel — Compose Module
 */
const Compose = {
    async load() {
        const data = await App.get('/compose/list');
        const projects = data.data || [];
        App.setContent(`<div class="fade-in"><div class="page-header"><div><h1 class="page-title">Docker Compose</h1></div><button class="btn btn-primary" onclick="Compose.createDialog()">＋ Новый проект</button></div>
        ${projects.length ? projects.map(p => `<div class="card mb-2">
            <div class="d-flex align-center justify-between">
                <div><h3 style="color:var(--text-white)">${App.esc(p.name)}</h3><span class="badge badge-${p.status==='running'?'running':'stopped'}">${p.status}</span><span class="text-muted" style="margin-left:8px;font-size:12px">${App.formatDate(p.updated_at)}</span></div>
                <div class="action-bar">
                    <button class="btn btn-sm btn-success" onclick="Compose.action(${p.id},'up')">▶ Up</button>
                    <button class="btn btn-sm btn-warning" onclick="Compose.action(${p.id},'down')">⏹ Down</button>
                    <button class="btn btn-sm btn-secondary" onclick="Compose.action(${p.id},'restart')">🔄</button>
                    <button class="btn btn-sm btn-secondary" onclick="Compose.edit(${p.id},'${App.esc(p.yaml_content?.replace(/'/g,"\\'").replace(/\n/g,"\\n"))}')">📝 Редактировать</button>
                    <button class="btn btn-sm btn-secondary" onclick="Compose.showLogs(${p.id})">📋 Логи</button>
                    <button class="btn btn-sm btn-ghost text-danger" onclick="Compose.remove(${p.id})">🗑</button>
                </div>
            </div>
        </div>`).join('') : '<div class="card"><div class="empty-state"><div class="empty-state-icon">📋</div><p>Нет Compose-проектов</p></div></div>'}
        </div>`);
    },
    createDialog() {
        App.showModal('Новый Docker Compose проект', `<div class="form-group"><label class="form-label">Имя проекта</label><input class="form-control" id="compose-name" placeholder="my-project"></div><div class="form-group"><label class="form-label">docker-compose.yml</label><textarea class="form-control" id="compose-yaml" style="height:300px" placeholder="version: '3'\nservices:\n  web:\n    image: nginx\n    ports:\n      - '80:80'"></textarea></div><button class="btn btn-secondary btn-sm" onclick="Compose.validateYaml()">✓ Проверить YAML</button><div id="validate-result" class="mt-1"></div>`, {
            width: '700px', confirmText: 'Создать', onConfirm: async () => {
                try { await App.post('/compose/create', {name:document.getElementById('compose-name').value, yaml:document.getElementById('compose-yaml').value}); App.success('Проект создан'); App.closeModal(); this.load(); } catch(e) { App.error(e.message); }
            }
        });
    },
    async action(id, act) { try { const r = await App.post(`/compose/${act}`,{id}); App.success(r.message); this.load(); } catch(e) { App.error(e.message); } },
    async remove(id) { App.confirm('Удалить проект?', async()=>{ try { await App.post('/compose/delete',{id}); App.success('Удалён'); this.load(); } catch(e){ App.error(e.message); }}); },
    async showLogs(id) { try { const data = await App.get(`/compose/logs?id=${id}`); App.showModal('Логи',`<div class="log-viewer">${App.esc(data.data?.logs||'Нет логов')}</div>`,{width:'800px'}); } catch(e){ App.error(e.message); } },
    edit(id, yaml) { App.showModal('Редактировать YAML', `<textarea class="form-control" id="compose-edit-yaml" style="height:400px">${yaml.replace(/\\n/g,'\n')}</textarea>`, { width:'700px', confirmText:'Сохранить', onConfirm: async()=>{ try { await App.post('/compose/update',{id,yaml:document.getElementById('compose-edit-yaml').value}); App.success('Обновлено'); App.closeModal(); this.load(); } catch(e){ App.error(e.message); }}}); },
    async validateYaml() { const yaml = document.getElementById('compose-yaml')?.value; if(!yaml) return; try { const r = await App.post('/compose/validate',{yaml}); document.getElementById('validate-result').innerHTML = r.data?.valid ? '<span class="text-success">✓ YAML валиден</span>' : `<span class="text-danger">✕ ${App.esc(r.data?.output||'Ошибка')}</span>`; } catch(e) { document.getElementById('validate-result').innerHTML = `<span class="text-danger">${e.message}</span>`; } }
};

/**
 * DockerPanel — Templates Module
 */
const Templates = {
    async load() {
        const data = await App.get('/templates/list');
        const templates = data.data || [];
        const categories = [...new Set(templates.map(t => t.category))];
        
        App.setContent(`<div class="fade-in"><div class="page-header"><div><h1 class="page-title">Шаблоны</h1><p class="page-subtitle">Готовые конфигурации для быстрого развёртывания</p></div></div>
        <div class="tabs mb-2"><button class="tab active" onclick="Templates.filterCategory(this,'')">Все</button>${categories.map(c=>`<button class="tab" onclick="Templates.filterCategory(this,'${c}')">${c}</button>`).join('')}</div>
        <div class="template-grid" id="template-list">${templates.map(t => this.renderCard(t)).join('')}</div></div>`);
    },
    renderCard(t) {
        const icons = {os:'🐧',webserver:'🌐',language:'💻',database:'🗄',monitoring:'📊',apps:'📦',ai:'🤖',communication:'💬',media:'🎬'};
        return `<div class="template-card" data-category="${t.category}" onclick="Templates.deploy(${t.id},'${App.esc(t.name)}','${App.esc(t.image)}','${App.esc(t.default_tag)}')">
            <div class="template-card-header"><div class="template-icon">${icons[t.category]||'📦'}</div><div><div class="template-name">${App.esc(t.name)}</div><div class="template-category">${t.category}</div></div></div>
            <div class="template-desc">${App.esc(t.description)}</div>
            <div class="mt-1"><span class="tag">${App.esc(t.image)}:${t.default_tag}</span></div></div>`;
    },
    filterCategory(btn, cat) {
        document.querySelectorAll('.tabs .tab').forEach(t=>t.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.template-card').forEach(c => { c.style.display = !cat || c.dataset.category === cat ? '' : 'none'; });
    },
    deploy(templateId, name, image, tag) {
        App.showModal(`Развернуть: ${name}`, `<div class="form-group"><label class="form-label">Имя контейнера</label><input class="form-control" id="tpl-name" value="${name.toLowerCase().replace(/\s+/g,'-')}-1"></div><div class="form-group"><label class="form-label">Tag</label><input class="form-control" id="tpl-tag" value="${tag}"></div>`, {
            confirmText: '🚀 Развернуть', onConfirm: async () => {
                const btn = document.getElementById('modal-confirm'); btn.disabled=true; btn.innerHTML='<div class="spinner"></div> Создание...';
                try { await App.post('/templates/deploy', { template_id: templateId, name: document.getElementById('tpl-name').value, tag: document.getElementById('tpl-tag').value }); App.success('Контейнер создан!'); App.closeModal(); App.navigateTo('containers'); } catch(e) { App.error(e.message); btn.disabled=false; btn.textContent='🚀 Развернуть'; }
            }
        });
    }
};

/**
 * DockerPanel — Domains Module
 */
const Domains = {
    async load() {
        const data = await App.get('/domains/list');
        const domains = data.data || [];
        App.setContent(`<div class="fade-in"><div class="page-header"><div><h1 class="page-title">Домены</h1></div><button class="btn btn-primary" onclick="Domains.createDialog()">＋ Добавить домен</button></div>
        <div class="card"><div class="table-container"><table><thead><tr><th>Домен</th><th>Контейнер</th><th>Порт</th><th>SSL</th><th>Действия</th></tr></thead>
        <tbody>${domains.map(d=>`<tr><td><strong>${App.esc(d.subdomain)}.${App.esc(d.base_domain)}</strong></td><td>${App.esc(d.container_name||'-')}</td><td>${d.container_port}</td><td>${d.ssl_enabled?'<span class="text-success">✓</span>':'<button class="btn btn-sm btn-secondary" onclick="Domains.enableSSL(${d.id})">Включить</button>'}</td><td><button class="btn btn-sm btn-ghost text-danger" onclick="Domains.remove(${d.id})">🗑</button></td></tr>`).join('')}</tbody></table></div></div></div>`);
    },
    createDialog() {
        App.showModal('Добавить домен', `<div class="form-group"><label class="form-label">Docker ID контейнера</label><input class="form-control" id="domain-cid"></div><div class="form-group"><label class="form-label">Поддомен</label><input class="form-control" id="domain-sub" placeholder="myapp"></div><div class="form-group"><label class="form-label">Порт контейнера</label><input class="form-control" id="domain-port" value="80"></div>`, {
            confirmText:'Создать', onConfirm: async()=>{ try { await App.post('/domains/create',{container_id:document.getElementById('domain-cid').value,subdomain:document.getElementById('domain-sub').value,container_port:document.getElementById('domain-port').value}); App.success('Домен создан'); App.closeModal(); this.load(); } catch(e){ App.error(e.message); }}
        });
    },
    async remove(id) { try { await App.post('/domains/remove',{id}); App.success('Удалён'); this.load(); } catch(e) { App.error(e.message); } },
    async enableSSL(id) { try { App.info('Получение SSL...'); const r = await App.post('/domains/ssl',{id}); App.success(r.message); this.load(); } catch(e) { App.error(e.message); } }
};

/**
 * DockerPanel — Backups Module
 */
const Backups = {
    async load() {
        const data = await App.get('/backups/list');
        const backups = data.data || [];
        App.setContent(`<div class="fade-in"><div class="page-header"><div><h1 class="page-title">Резервные копии</h1></div><button class="btn btn-primary" onclick="Backups.createDialog()">＋ Создать бэкап</button></div>
        <div class="card"><div class="table-container"><table><thead><tr><th>Имя</th><th>Тип</th><th>Контейнер</th><th>Размер</th><th>Статус</th><th>Дата</th><th>Действия</th></tr></thead>
        <tbody>${backups.map(b=>`<tr><td>${App.esc(b.name)}</td><td><span class="tag">${b.type}</span></td><td>${App.esc(b.container_name||'-')}</td><td>${App.formatBytes(b.file_size)}</td><td><span class="badge badge-${b.status==='completed'?'success':'stopped'}">${b.status}</span></td><td style="font-size:12px">${App.formatDate(b.created_at)}</td><td><div class="action-buttons">${b.status==='completed'?`<button class="btn btn-sm btn-ghost" onclick="Backups.restore(${b.id})">♻ Восстановить</button><a href="/backups/download?id=${b.id}" class="btn btn-sm btn-ghost">⬇</a>`:''}  <button class="btn btn-sm btn-ghost text-danger" onclick="Backups.remove(${b.id})">🗑</button></div></td></tr>`).join('')}</tbody></table></div></div></div>`);
    },
    createDialog() {
        App.showModal('Создать бэкап', `<div class="form-group"><label class="form-label">Тип</label><select class="form-control" id="bk-type"><option value="container">Контейнер</option><option value="volume">Volume</option></select></div><div class="form-group"><label class="form-label">ID контейнера / Имя volume</label><input class="form-control" id="bk-target"></div>`, {
            confirmText:'Создать', onConfirm: async()=>{ const type=document.getElementById('bk-type').value; const target=document.getElementById('bk-target').value;
                try { await App.post('/backups/create',type==='volume'?{type,volume_name:target}:{type,container_id:target}); App.success('Бэкап создан'); App.closeModal(); this.load(); } catch(e){ App.error(e.message); }}
        });
    },
    async restore(id) { App.confirm('Восстановить из бэкапа?', async()=>{ try { await App.post('/backups/restore',{id}); App.success('Восстановлено'); } catch(e) { App.error(e.message); }}); },
    async remove(id) { try { await App.post('/backups/delete',{id}); App.success('Удалён'); this.load(); } catch(e) { App.error(e.message); } }
};

/**
 * DockerPanel — Settings Module
 */
const Settings = {
    async load() {
        const data = await App.get('/settings');
        const s = data.data || {};
        App.setContent(`<div class="fade-in"><div class="page-header"><h1 class="page-title">Настройки</h1></div>
        <div class="card" style="max-width:700px"><form id="settings-form" onsubmit="Settings.save(event)">
            <h3 style="color:var(--text-white)" class="mb-2">Основные</h3>
            <div class="form-group"><label class="form-label">Название панели</label><input class="form-control" name="site_name" value="${App.esc(s.site_name||'DockerPanel')}"></div>
            <div class="form-group"><label class="form-label">URL панели</label><input class="form-control" name="site_url" value="${App.esc(s.site_url||'')}"></div>
            <h3 style="color:var(--text-white)" class="mt-3 mb-2">Домены</h3>
            <div class="form-group"><label class="form-label">Базовый домен</label><input class="form-control" name="base_domain" value="${App.esc(s.base_domain||'')}" placeholder="example.com"></div>
            <div class="form-group"><label class="form-label">Email для SSL</label><input class="form-control" name="ssl_email" value="${App.esc(s.ssl_email||'')}"></div>
            <h3 style="color:var(--text-white)" class="mt-3 mb-2">Лимиты</h3>
            <div class="form-row"><div class="form-group"><label class="form-label">CPU по умолчанию</label><input class="form-control" name="default_cpu_limit" value="${App.esc(s.default_cpu_limit||'1')}"></div><div class="form-group"><label class="form-label">RAM по умолчанию</label><input class="form-control" name="default_ram_limit" value="${App.esc(s.default_ram_limit||'512m')}"></div></div>
            <div class="form-group"><label class="form-label">Макс. контейнеров на пользователя</label><input class="form-control" name="max_containers_per_user" value="${App.esc(s.max_containers_per_user||'50')}"></div>
            <button type="submit" class="btn btn-primary btn-lg mt-2">💾 Сохранить</button>
        </form></div></div>`);
    },
    async save(e) {
        e.preventDefault();
        const form = e.target; const data = {};
        new FormData(form).forEach((v,k) => data[k] = v);
        try { await App.post('/settings/update', data); App.success('Настройки сохранены'); } catch(e) { App.error(e.message); }
    }
};

/**
 * DockerPanel — Users Module
 */
const Users = {
    async load() {
        const data = await App.get('/users/list');
        const users = data.data || [];
        App.setContent(`<div class="fade-in"><div class="page-header"><div><h1 class="page-title">Пользователи</h1></div><button class="btn btn-primary" onclick="Users.createDialog()">＋ Добавить</button></div>
        <div class="card"><div class="table-container"><table><thead><tr><th>Имя</th><th>Роль</th><th>Статус</th><th>Последний вход</th><th>API Token</th><th>Действия</th></tr></thead>
        <tbody>${users.map(u=>`<tr><td><strong>${App.esc(u.username)}</strong><div class="text-muted" style="font-size:11px">${App.esc(u.email||'')}</div></td><td><span class="tag">${u.role}</span></td><td>${u.is_active?'<span class="text-success">Активен</span>':'<span class="text-danger">Заблокирован</span>'}</td><td style="font-size:12px">${App.formatDate(u.last_login)}</td><td class="text-mono" style="font-size:10px;max-width:100px;overflow:hidden;text-overflow:ellipsis">${u.api_token||'-'}</td><td><button class="btn btn-sm btn-ghost" onclick="Users.editDialog(${u.id},'${App.esc(u.username)}','${App.esc(u.email||'')}','${u.role}')">✏</button><button class="btn btn-sm btn-ghost text-danger" onclick="Users.remove(${u.id})">🗑</button></td></tr>`).join('')}</tbody></table></div></div></div>`);
    },
    createDialog() {
        App.showModal('Добавить пользователя', `<div class="form-group"><label class="form-label">Логин</label><input class="form-control" id="u-name"></div><div class="form-group"><label class="form-label">Пароль</label><input class="form-control" id="u-pass" type="password"></div><div class="form-group"><label class="form-label">Email</label><input class="form-control" id="u-email"></div><div class="form-group"><label class="form-label">Роль</label><select class="form-control" id="u-role"><option value="user">User</option><option value="admin">Admin</option><option value="viewer">Viewer</option></select></div>`, {
            confirmText:'Создать', onConfirm: async()=>{ try { const r = await App.post('/users/create',{username:document.getElementById('u-name').value,password:document.getElementById('u-pass').value,email:document.getElementById('u-email').value,role:document.getElementById('u-role').value}); App.success('Создан. API Token: '+r.data?.api_token); App.closeModal(); this.load(); } catch(e){ App.error(e.message); }}
        });
    },
    editDialog(id,name,email,role) {
        App.showModal(`Редактировать: ${name}`, `<div class="form-group"><label class="form-label">Email</label><input class="form-control" id="ue-email" value="${email}"></div><div class="form-group"><label class="form-label">Новый пароль (оставьте пустым)</label><input class="form-control" id="ue-pass" type="password"></div><div class="form-group"><label class="form-label">Роль</label><select class="form-control" id="ue-role"><option value="user" ${role==='user'?'selected':''}>User</option><option value="admin" ${role==='admin'?'selected':''}>Admin</option><option value="viewer" ${role==='viewer'?'selected':''}>Viewer</option></select></div>`, {
            confirmText:'Сохранить', onConfirm: async()=>{ try { await App.post('/users/update',{id,email:document.getElementById('ue-email').value,password:document.getElementById('ue-pass').value,role:document.getElementById('ue-role').value}); App.success('Обновлён'); App.closeModal(); this.load(); } catch(e){ App.error(e.message); }}
        });
    },
    async remove(id) { App.confirm('Удалить пользователя?', async()=>{ try { await App.post('/users/delete',{id}); App.success('Удалён'); this.load(); } catch(e){ App.error(e.message); }}); }
};

/**
 * DockerPanel — Monitor Module (used on containers detail page)
 */
const Monitor = {
    charts: {},
    intervals: {},
    
    async render(containerId) {
        App.showModal('📊 Мониторинг', `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
                <div class="card p-3"><canvas id="chart-cpu"></canvas></div>
                <div class="card p-3"><canvas id="chart-mem"></canvas></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="card p-3"><canvas id="chart-net"></canvas></div>
                <div class="card p-3"><canvas id="chart-disk"></canvas></div>
            </div>
        `, {
            width: '900px',
            hideCancel: true,
            confirmText: 'Закрыть',
            onConfirm: () => this.stop(containerId)
        });

        setTimeout(() => this.initCharts(containerId), 200);
    },

    initCharts(id) {
        const createChart = (ctxId, label, color) => {
            const ctx = document.getElementById(ctxId).getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data: { labels: [], datasets: [{ label, data: [], borderColor: color, tension: 0.4, fill: false }] },
                options: { responsive: true, animation: { duration: 0 }, scales: { x: { display: false }, y: { beginAtZero: true } } }
            });
        };

        this.charts[id] = {
            cpu: createChart('chart-cpu', 'CPU Usage (%)', '#00d4ff'),
            mem: createChart('chart-mem', 'Memory Usage (MB)', '#7c3aed'),
            net: createChart('chart-net', 'Network I/O (MB)', '#10b981'),
            disk: createChart('chart-disk', 'Block I/O (MB)', '#f59e0b')
        };

        this.updateStats(id);
        this.intervals[id] = setInterval(() => this.updateStats(id), 2000);
    },

    async updateStats(id) {
        try {
            const data = await App.get(`/monitor/stats?id=${id}`);
            if (!data.data || !this.charts[id]) return;
            const stats = data.data;

            const time = new Date().toLocaleTimeString();
            const addData = (chart, value) => {
                chart.data.labels.push(time);
                chart.data.datasets[0].data.push(value);
                if (chart.data.labels.length > 20) {
                    chart.data.labels.shift();
                    chart.data.datasets[0].data.shift();
                }
                chart.update();
            };

            const parseMB = (str) => parseFloat((str||'0').replace(/[^\d.]/g, ''));
            const parsePercent = (str) => parseFloat((str||'0').replace('%', ''));

            addData(this.charts[id].cpu, parsePercent(stats.CPUPerc));
            addData(this.charts[id].mem, parseMB(stats.MemUsage.split(' / ')[0]));
            
            const netParts = stats.NetIO.split(' / ');
            addData(this.charts[id].net, parseMB(netParts[0]) + parseMB(netParts[1]));

            const diskParts = stats.BlockIO.split(' / ');
            addData(this.charts[id].disk, parseMB(diskParts[0]) + parseMB(diskParts[1]));
        } catch (e) {
            console.error('Monitor error:', e);
        }
    },

    stop(id) {
        if (this.intervals[id]) clearInterval(this.intervals[id]);
        if (this.charts[id]) {
            Object.values(this.charts[id]).forEach(c => c.destroy());
            delete this.charts[id];
        }
        App.closeModal();
    }
};

/**
 * DockerPanel — Logs Module
 */
const Logs = {
    async show(containerId) {
        try {
            const data = await App.get(`/logs/container?id=${containerId}&tail=500`);
            App.showModal('Логи контейнера', `
                <div class="d-flex gap-1 mb-2">
                    <button class="btn btn-sm btn-secondary" onclick="Logs.refresh('${containerId}')">🔄 Обновить</button>
                    <a href="/logs/download?id=${containerId}" class="btn btn-sm btn-secondary">⬇ Скачать</a>
                    <button class="btn btn-sm btn-danger" onclick="Logs.clear('${containerId}')">🗑 Очистить</button>
                </div>
                <div class="log-viewer" id="log-content">${App.esc(data.data?.logs || 'Нет логов')}</div>
            `, { width: '900px' });
        } catch(e) { App.error(e.message); }
    },
    async refresh(id) { try { const d = await App.get(`/logs/container?id=${id}&tail=500`); document.getElementById('log-content').textContent = d.data?.logs||''; } catch(e){} },
    async clear(id) { try { await App.post('/logs/clear',{id}); App.success('Очищено'); document.getElementById('log-content').textContent=''; } catch(e){ App.error(e.message); } }
};
