<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0 text-white">Журнал аудита</h2>
    <div>
        <button class="btn btn-primary btn-sm" onclick="Audit.load()">
            <i class="bi bi-arrow-clockwise me-1"></i>Обновить
        </button>
    </div>
</div>

<div class="card bg-card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-borderless align-middle mb-0">
                <thead class="text-muted" style="font-size: 0.85rem; background: var(--bg-secondary);">
                    <tr>
                        <th class="ps-4">Время</th>
                        <th>Пользователь</th>
                        <th>Действие</th>
                        <th>Тип объекта</th>
                        <th>ID объекта</th>
                        <th>Детали</th>
                        <th>IP Адрес</th>
                    </tr>
                </thead>
                <tbody id="auditList">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Загрузка...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
