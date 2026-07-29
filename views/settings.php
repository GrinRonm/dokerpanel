<div class="row">
    <div class="col-md-12">
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-gear-fill me-2 text-primary"></i>Общие настройки</h5>
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Имя панели</label>
                            <input type="text" class="form-control" name="panel_name" placeholder="DockerPanel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Язык по умолчанию</label>
                            <select class="form-select" name="language">
                                <option value="ru">Русский</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Сохранить настройки</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle-fill me-2 text-info"></i>Информация о Docker</h5>
            </div>
            <div class="card-body" id="dockerInfoContainer">
                <div class="d-flex justify-content-center my-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-cloud-arrow-down-fill me-2 text-success"></i>Обновление (GitHub)</h5>
            </div>
            <div class="card-body" id="updateContainer">
                <div class="d-flex justify-content-center my-4" id="updateSpinner"><div class="spinner-border text-success"></div></div>
                <div id="updateStatus" class="d-none text-center py-3">
                    <h6 class="text-muted mb-2">Текущая версия:</h6>
                    <h4 class="fw-bold text-dark mb-4" id="currentVersionBadge">-</h4>
                    
                    <h6 class="text-muted mb-2">Последняя версия на GitHub:</h6>
                    <h4 class="fw-bold text-primary mb-4" id="latestVersionBadge">-</h4>
                    
                    <div id="updateActionArea"></div>
                </div>
            </div>
        </div>
    </div>
</div>
