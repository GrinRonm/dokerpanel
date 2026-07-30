/**
 * DockerPanel — Core JavaScript
 * SPA-like navigation, AJAX, notifications, modals
 */

const App = {
    csrfToken: '',
    currentPage: '',
    refreshIntervals: [],
    ws: null,

    /**
     * Инициализация приложения
     */
    init() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        this.bindNavigation();
        this.bindGlobalEvents();
        this.startSystemMonitor();

        // Определить текущую страницу
        const path = window.location.pathname.replace(/^\//, '') || 'dashboard';
        this.navigateTo(path, false);
    },

    /**
     * SPA-навигация
     */
    bindNavigation() {
        document.querySelectorAll('.nav-item[data-page]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                this.navigateTo(page);
            });
        });

        window.addEventListener('popstate', (e) => {
            const page = window.location.pathname.replace(/^\//, '') || 'dashboard';
            this.navigateTo(page, false);
        });
    },

    /**
     * Навигация к странице
     */
    navigateTo(page, pushState = true) {
        // Очистить интервалы
        this.clearIntervals();

        // Обновить URL
        if (pushState) {
            history.pushState({page}, '', '/' + page);
        }

        // Обновить активный пункт меню
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        const activeNav = document.querySelector(`.nav-item[data-page="${page}"]`) ||
                         document.querySelector(`.nav-item[data-page="${page.split('/')[0]}"]`);
        if (activeNav) activeNav.classList.add('active');

        this.currentPage = page;

        // Загрузить контент страницы
        this.loadPage(page);
    },

    /**
     * Загрузить контент страницы через AJAX
     */
    async loadPage(page) {
        const content = document.getElementById('page-content');
        if (!content) return;

        content.innerHTML = '<div class="loading-overlay"><div class="spinner spinner-lg"></div><span>Загрузка...</span></div>';

        // Вызов соответствующего модуля
        const moduleName = page.split('?')[0].split('/')[0];
        const moduleMap = {
            'dashboard': () => Dashboard.load(),
            'containers': () => Containers.load(page.split('?')[0]),
            'files': () => FileManager.load(),
            'images': () => Images.load(),
            'networks': () => Networks.load(),
            'volumes': () => Volumes.load(),
            'compose': () => Compose.load(),
            'templates': () => Templates.load(),
            'domains': () => Domains.load(),
            'backups': () => Backups.load(),
            'settings': () => Settings.load(),
            'users': () => Users.load(),
            'audit': () => Audit.load(),
        };

        if (moduleMap[moduleName]) {
            try {
                await moduleMap[moduleName]();
            } catch (e) {
                content.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⚠️</div><div class="empty-state-text">Ошибка загрузки: ${e.message}</div></div>`;
                console.error(e);
            }
        }
    },

    /**
     * AJAX-запрос
     */
    async api(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.csrfToken,
                'Accept': 'application/json',
            },
        };

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.body = JSON.stringify(options.body);
        }

        if (options.body instanceof FormData) {
            delete defaults.headers['Content-Type'];
        }

        const config = { ...defaults, ...options };
        config.headers = { ...defaults.headers, ...options.headers };

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok || data.success === false) {
                throw new Error(data.message || data.error || 'Ошибка запроса');
            }
            
            return data;
        } catch (e) {
            if (e.message.includes('Unauthorized')) {
                window.location.href = '/auth/login';
                return;
            }
            throw e;
        }
    },

    /**
     * GET-запрос
     */
    async get(url) {
        return this.api(url, { method: 'GET' });
    },

    /**
     * POST-запрос
     */
    async post(url, body = {}) {
        return this.api(url, { method: 'POST', body });
    },

    // ==========================================
    // Уведомления
    // ==========================================

    toast(message, type = 'info', duration = 4000) {
        const container = document.getElementById('toast-container') || this.createToastContainer();
        
        const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type]}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    },

    success(msg) { this.toast(msg, 'success'); },
    error(msg) { this.toast(msg, 'error', 6000); },
    warning(msg) { this.toast(msg, 'warning'); },
    info(msg) { this.toast(msg, 'info'); },

    // ==========================================
    // Модальные окна
    // ==========================================

    showModal(title, content, options = {}) {
        let overlay = document.getElementById('modal-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'modal-overlay';
            overlay.className = 'modal-overlay';
            document.body.appendChild(overlay);
        }

        const footer = options.footer || `
            <button class="btn btn-secondary" onclick="App.closeModal()">Отмена</button>
            ${options.confirmText ? `<button class="btn btn-primary" id="modal-confirm">${options.confirmText}</button>` : ''}
        `;

        overlay.innerHTML = `
            <div class="modal" style="${options.width ? 'max-width:' + options.width : ''}">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close" onclick="App.closeModal()">✕</button>
                </div>
                <div class="modal-body">${content}</div>
                <div class="modal-footer">${footer}</div>
            </div>
        `;

        requestAnimationFrame(() => overlay.classList.add('active'));

        // Закрытие по клику на фон
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.closeModal();
        });

        // Callback для кнопки подтверждения
        if (options.onConfirm) {
            document.getElementById('modal-confirm')?.addEventListener('click', () => {
                options.onConfirm();
            });
        }

        return overlay;
    },

    closeModal() {
        const overlay = document.getElementById('modal-overlay');
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 300);
        }
    },

    /**
     * Модальное подтверждение
     */
    confirm(message, onConfirm) {
        this.showModal('Подтверждение', `<p>${message}</p>`, {
            confirmText: 'Подтвердить',
            onConfirm: () => {
                this.closeModal();
                onConfirm();
            }
        });
    },

    // ==========================================
    // Интервалы обновления
    // ==========================================

    addInterval(fn, ms) {
        const id = setInterval(fn, ms);
        this.refreshIntervals.push(id);
        return id;
    },

    clearIntervals() {
        this.refreshIntervals.forEach(id => clearInterval(id));
        this.refreshIntervals = [];
    },

    // ==========================================
    // Мониторинг системы (sidebar badge)
    // ==========================================

    async startSystemMonitor() {
        const update = async () => {
            try {
                const data = await this.get('/dashboard/stats');
                if (data?.data?.docker) {
                    const badge = document.getElementById('containers-badge');
                    if (badge) badge.textContent = data.data.docker.containers_running;
                }
            } catch (e) {}
        };
        update();
        setInterval(update, 30000);
    },

    // ==========================================
    // Утилиты
    // ==========================================

    bindGlobalEvents() {
        // ESC закрывает модальные окна
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeModal();
        });

        // Мобильное меню
        document.getElementById('menu-toggle')?.addEventListener('click', () => {
            document.querySelector('.sidebar')?.classList.toggle('open');
        });
    },

    /**
     * Форматирование даты
     */
    formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleString('ru-RU', { 
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit' 
        });
    },

    /**
     * Форматирование байт
     */
    formatBytes(bytes, decimals = 2) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
    },

    /**
     * Шаблон HTML
     */
    html(strings, ...values) {
        return strings.reduce((result, str, i) => {
            let value = values[i] !== undefined ? values[i] : '';
            if (typeof value === 'string') {
                value = value.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }
            return result + str + value;
        }, '');
    },

    /**
     * Escaping для HTML (safe)
     */
    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    /**
     * Set page content
     */
    setContent(html) {
        const content = document.getElementById('page-content');
        if (content) {
            content.innerHTML = html;
            content.querySelectorAll('.fade-in').forEach(el => {
                el.style.animationDelay = '0s';
            });
        }
    }
};

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', () => App.init());
