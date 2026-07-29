<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <meta name="description" content="DockerPanel — Управление Docker-контейнерами">
    <title>DockerPanel</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="/public/css/app.css">
    
    <!-- xterm.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
    
    <!-- CodeMirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/dracula.min.css">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-icon">🐳</div>
                    <span>DockerPanel</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">Обзор</div>
                <a class="nav-item" data-page="dashboard" href="/dashboard">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                
                <div class="nav-section">Docker</div>
                <a class="nav-item" data-page="containers" href="/containers">
                    <span class="nav-icon">📦</span>
                    <span class="nav-text">Контейнеры</span>
                    <span class="nav-badge" id="containers-badge">0</span>
                </a>
                <a class="nav-item" data-page="images" href="/images">
                    <span class="nav-icon">🖼</span>
                    <span class="nav-text">Образы</span>
                </a>
                <a class="nav-item" data-page="networks" href="/networks">
                    <span class="nav-icon">🌐</span>
                    <span class="nav-text">Сети</span>
                </a>
                <a class="nav-item" data-page="volumes" href="/volumes">
                    <span class="nav-icon">💾</span>
                    <span class="nav-text">Volumes</span>
                </a>
                <a class="nav-item" data-page="compose" href="/compose">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Compose</span>
                </a>
                
                <div class="nav-section">Инструменты</div>
                <a class="nav-item" data-page="templates" href="/templates">
                    <span class="nav-icon">🧩</span>
                    <span class="nav-text">Шаблоны</span>
                </a>
                <a class="nav-item" data-page="domains" href="/domains">
                    <span class="nav-icon">🔗</span>
                    <span class="nav-text">Домены</span>
                </a>
                <a class="nav-item" data-page="backups" href="/backups">
                    <span class="nav-icon">💿</span>
                    <span class="nav-text">Бэкапы</span>
                </a>
                
                <div class="nav-section">Система</div>
                <a class="nav-item" data-page="users" href="/users">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Пользователи</span>
                </a>
                <a class="nav-item" data-page="settings" href="/settings">
                    <span class="nav-icon">⚙</span>
                    <span class="nav-text">Настройки</span>
                </a>
                <a class="nav-item" href="/auth/logout">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-text">Выход</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="top-header">
                <button id="menu-toggle" class="btn btn-ghost" style="display:none">☰</button>
                <div class="header-search">
                    <span class="search-icon">🔍</span>
                    <input type="text" placeholder="Поиск контейнеров..." id="global-search">
                </div>
                <div class="header-right">
                    <div class="header-user">
                        <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                        <span style="font-size:13px"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </header>

            <!-- Page Content (динамически обновляется через JS) -->
            <div class="page-content" id="page-content">
                <?= $pageContent ?? '' ?>
            </div>
        </main>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-web-links@0.9.0/lib/xterm-addon-web-links.min.js"></script>
    
    <!-- CodeMirror -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/shell/shell.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script src="/public/js/app.js?v=2" defer></script>
    <script src="/public/js/dashboard.js?v=2" defer></script>
    <script src="/public/js/containers.js?v=2" defer></script>
    <script src="/public/js/terminal.js?v=2" defer></script>
    <script src="/public/js/filemanager.js?v=2" defer></script>
    <script src="/public/js/images.js?v=2" defer></script>
    <script src="/public/js/modules.js?v=2" defer></script>
    <script src="/public/js/settings.js?v=2" defer></script>
</body>
</html>
