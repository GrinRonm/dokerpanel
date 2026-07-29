<div class="fade-in" style="height: 100%; display: flex; flex-direction: column;">
    <div class="page-header" style="margin-bottom: 12px;">
        <div>
            <h1 class="page-title">Терминал контейнера</h1>
            <p class="page-subtitle text-mono" id="term-container-id"><?= htmlspecialchars($_GET['id'] ?? '') ?></p>
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

<script>
    // Инициализация при загрузке
    document.addEventListener('DOMContentLoaded', () => {
        const cId = '<?= htmlspecialchars($_GET['id'] ?? '') ?>';
        if (cId && typeof TerminalModule !== 'undefined') {
            document.getElementById('term-host').textContent = cId.substring(0, 12);
            // Небольшая задержка для рендера контейнера
            setTimeout(() => {
                TerminalModule.init(cId);
            }, 100);
        }
    });

    // Скрыть меню
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.querySelector('.sidebar');
        const main = document.querySelector('.main-content');
        if (sidebar) sidebar.style.display = 'none';
        if (main) main.style.marginLeft = '0';
        
        // Перехватим навигацию чтобы не сломать терминал
        if (typeof App !== 'undefined') {
            App.bindNavigation = function() {}; // Отключаем клики по ссылкам
        }
    });
</script>
