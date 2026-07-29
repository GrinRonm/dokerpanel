/**
 * DockerPanel — Dashboard Module
 */

const Dashboard = {
    async load() {
        const data = await App.get('/dashboard/stats');
        const d = data.data;
        const docker = d.docker || {};
        const system = d.system || {};
        const cpu = system.cpu || {};
        const mem = system.memory || {};
        const disk = system.disk || {};

        App.setContent(`
            <div class="fade-in">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Dashboard</h1>
                        <p class="page-subtitle">Обзор системы и контейнеров</p>
                    </div>
                    <div class="action-bar">
                        <button class="btn btn-primary" onclick="App.navigateTo('containers/create')">
                            ＋ Создать контейнер
                        </button>
                        <button class="btn btn-secondary" onclick="Containers.importExisting()">
                            📥 Импорт
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">📦</div>
                        <div class="stat-info">
                            <div class="stat-value">${docker.containers_total || 0}</div>
                            <div class="stat-label">Контейнеров</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">▶</div>
                        <div class="stat-info">
                            <div class="stat-value">${docker.containers_running || 0}</div>
                            <div class="stat-label">Запущено</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">■</div>
                        <div class="stat-info">
                            <div class="stat-value">${docker.containers_stopped || 0}</div>
                            <div class="stat-label">Остановлено</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">🖼</div>
                        <div class="stat-info">
                            <div class="stat-value">${docker.images_count || 0}</div>
                            <div class="stat-label">Образов</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon yellow">💾</div>
                        <div class="stat-info">
                            <div class="stat-value">${docker.volumes_count || 0}</div>
                            <div class="stat-label">Volumes</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">🌐</div>
                        <div class="stat-info">
                            <div class="stat-value">${docker.networks_count || 0}</div>
                            <div class="stat-label">Сетей</div>
                        </div>
                    </div>
                </div>

                <!-- System Resources -->
                <div class="grid-3 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">CPU</span>
                            <span class="text-accent">${cpu.usage_percent || 0}%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill ${cpu.usage_percent > 80 ? 'red' : cpu.usage_percent > 50 ? 'yellow' : 'green'}" 
                                 style="width: ${cpu.usage_percent || 0}%"></div>
                        </div>
                        <div class="mt-1 text-muted" style="font-size:12px">
                            ${cpu.cores || 0} ядер · Load: ${cpu.load_1 || 0}
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">RAM</span>
                            <span class="text-accent">${mem.percent || 0}%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill ${mem.percent > 80 ? 'red' : mem.percent > 50 ? 'yellow' : 'green'}" 
                                 style="width: ${mem.percent || 0}%"></div>
                        </div>
                        <div class="mt-1 text-muted" style="font-size:12px">
                            ${mem.used_formatted || '0'} / ${mem.total_formatted || '0'}
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Диск</span>
                            <span class="text-accent">${disk.percent || 0}%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill ${disk.percent > 80 ? 'red' : disk.percent > 50 ? 'yellow' : 'green'}" 
                                 style="width: ${disk.percent || 0}%"></div>
                        </div>
                        <div class="mt-1 text-muted" style="font-size:12px">
                            ${disk.used_formatted || '0'} / ${disk.total_formatted || '0'}
                        </div>
                    </div>
                </div>

                <!-- Recent Actions -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Последние действия</span>
                    </div>
                    ${this.renderActions(d.recent_actions || [])}
                </div>
            </div>
        `);

        // Автообновление каждые 10 секунд
        App.addInterval(() => this.refreshStats(), 10000);
    },

    renderActions(actions) {
        if (!actions.length) return '<div class="empty-state"><div class="empty-state-text">Нет действий</div></div>';
        
        return actions.map(a => `
            <div class="d-flex align-center gap-1" style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.03)">
                <span style="font-size:12px;color:var(--text-muted);width:140px">${App.formatDate(a.created_at)}</span>
                <span style="font-size:13px;flex:1">${App.esc(a.action)}</span>
                <span class="tag">${App.esc(a.target_type || '')}</span>
                <span style="font-size:12px;color:var(--text-muted)">${App.esc(a.username || 'system')}</span>
            </div>
        `).join('');
    },

    async refreshStats() {
        try {
            const data = await App.get('/dashboard/stats');
            // Update badges
            const docker = data.data?.docker || {};
            const badge = document.getElementById('containers-badge');
            if (badge) badge.textContent = docker.containers_running || 0;
        } catch (e) {}
    }
};
