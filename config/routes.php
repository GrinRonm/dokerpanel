<?php
/**
 * DockerPanel — Routes Configuration
 * 
 * Формат: 'route' => ['Controller', 'method']
 * Маршруты проверяются AuthMiddleware (кроме auth/*)
 */

return [
    // Авторизация
    'auth/login'    => ['AuthController', 'login'],
    'auth/logout'   => ['AuthController', 'logout'],
    'auth/check'    => ['AuthController', 'check'],

    // Dashboard
    ''              => ['DashboardController', 'index'],
    'dashboard'     => ['DashboardController', 'index'],
    'dashboard/stats' => ['DashboardController', 'stats'],

    // Контейнеры
    'containers'            => ['ContainerController', 'index'],
    'containers/list'       => ['ContainerController', 'list'],
    'containers/create'     => ['ContainerController', 'create'],
    'containers/detail'     => ['ContainerController', 'detail'],
    'containers/start'      => ['ContainerController', 'start'],
    'containers/stop'       => ['ContainerController', 'stop'],
    'containers/restart'    => ['ContainerController', 'restart'],
    'containers/remove'     => ['ContainerController', 'remove'],
    'containers/exec'       => ['ContainerController', 'exec'],
    'containers/rename'     => ['ContainerController', 'rename'],
    'containers/update'     => ['ContainerController', 'update'],
    'containers/stats'      => ['ContainerController', 'stats'],
    'containers/top'        => ['ContainerController', 'top'],
    'containers/import'     => ['ContainerController', 'import'],
    'containers/terminal'   => ['ContainerController', 'terminal'],

    // Images
    'images'            => ['ImageController', 'index'],
    'images/list'       => ['ImageController', 'list'],
    'images/pull'       => ['ImageController', 'pull'],
    'images/remove'     => ['ImageController', 'remove'],
    'images/search'     => ['ImageController', 'search'],
    'images/history'    => ['ImageController', 'history'],

    // Networks
    'networks'              => ['NetworkController', 'index'],
    'networks/list'         => ['NetworkController', 'list'],
    'networks/create'       => ['NetworkController', 'create'],
    'networks/remove'       => ['NetworkController', 'remove'],
    'networks/connect'      => ['NetworkController', 'connect'],
    'networks/disconnect'   => ['NetworkController', 'disconnect'],

    // Volumes
    'volumes'           => ['VolumeController', 'index'],
    'volumes/list'      => ['VolumeController', 'list'],
    'volumes/create'    => ['VolumeController', 'create'],
    'volumes/remove'    => ['VolumeController', 'remove'],
    'volumes/prune'     => ['VolumeController', 'prune'],

    // Docker Compose
    'compose'           => ['ComposeController', 'index'],
    'compose/list'      => ['ComposeController', 'list'],
    'compose/create'    => ['ComposeController', 'create'],
    'compose/update'    => ['ComposeController', 'update'],
    'compose/delete'    => ['ComposeController', 'delete'],
    'compose/up'        => ['ComposeController', 'up'],
    'compose/down'      => ['ComposeController', 'down'],
    'compose/restart'   => ['ComposeController', 'restart'],
    'compose/logs'      => ['ComposeController', 'logs'],
    'compose/validate'  => ['ComposeController', 'validate'],

    // Templates
    'templates'         => ['TemplateController', 'index'],
    'templates/list'    => ['TemplateController', 'list'],
    'templates/deploy'  => ['TemplateController', 'deploy'],

    // File Manager
    'files'             => ['FileManagerController', 'index'],
    'files/list'        => ['FileManagerController', 'list'],
    'files/read'        => ['FileManagerController', 'read'],
    'files/write'       => ['FileManagerController', 'write'],
    'files/delete'      => ['FileManagerController', 'delete'],
    'files/mkdir'       => ['FileManagerController', 'mkdir'],
    'files/rename'      => ['FileManagerController', 'rename'],
    'files/copy'        => ['FileManagerController', 'copy'],
    'files/move'        => ['FileManagerController', 'move'],
    'files/upload'      => ['FileManagerController', 'upload'],
    'files/download'    => ['FileManagerController', 'download'],
    'files/archive'     => ['FileManagerController', 'archive'],
    'files/extract'     => ['FileManagerController', 'extract'],

    // Logs
    'logs'              => ['LogController', 'index'],
    'logs/container'    => ['LogController', 'container'],
    'logs/download'     => ['LogController', 'download'],
    'logs/clear'        => ['LogController', 'clear'],

    // Monitor
    'monitor'           => ['MonitorController', 'index'],
    'monitor/stats'     => ['MonitorController', 'stats'],
    'monitor/system'    => ['MonitorController', 'system'],

    // Domains
    'domains'           => ['DomainController', 'index'],
    'domains/list'      => ['DomainController', 'list'],
    'domains/create'    => ['DomainController', 'create'],
    'domains/remove'    => ['DomainController', 'remove'],
    'domains/ssl'       => ['DomainController', 'ssl'],

    // Backups
    'backups'           => ['BackupController', 'index'],
    'backups/list'      => ['BackupController', 'list'],
    'backups/create'    => ['BackupController', 'create'],
    'backups/restore'   => ['BackupController', 'restore'],
    'backups/delete'    => ['BackupController', 'delete'],
    'backups/download'  => ['BackupController', 'download'],
    'backups/schedule'  => ['BackupController', 'schedule'],

    // Settings
    'settings'          => ['SettingsController', 'index'],
    'settings/update'   => ['SettingsController', 'update'],
    'settings/docker'   => ['SettingsController', 'docker'],
    'settings/check_update' => ['SettingsController', 'checkUpdate'],
    'settings/start_update' => ['SettingsController', 'startUpdate'],

    // Users
    'users'             => ['UserController', 'index'],
    'users/list'        => ['UserController', 'list'],
    'users/create'      => ['UserController', 'create'],
    'users/update'      => ['UserController', 'update'],
    'users/delete'      => ['UserController', 'delete'],

    // API (REST)
    'api/v1/containers'         => ['ApiController', 'containers'],
    'api/v1/containers/create'  => ['ApiController', 'containerCreate'],
    'api/v1/containers/action'  => ['ApiController', 'containerAction'],
    'api/v1/images'             => ['ApiController', 'images'],
    'api/v1/networks'           => ['ApiController', 'networks'],
    'api/v1/volumes'            => ['ApiController', 'volumes'],
    'api/v1/system'             => ['ApiController', 'system'],
];
