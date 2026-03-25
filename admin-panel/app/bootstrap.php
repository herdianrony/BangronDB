<?php

$appRoot = dirname(__DIR__);
$repoRoot = dirname($appRoot);

require_once $appRoot.'/app/helpers.php';

$localVendor = $appRoot.'/vendor/autoload.php';
$repoVendor = $repoRoot.'/vendor/autoload.php';
$loadedAny = false;
if (file_exists($localVendor)) {
    require_once $localVendor;
    $loadedAny = true;
}
if (file_exists($repoVendor)) {
    require_once $repoVendor;
    $loadedAny = true;
}
if (!$loadedAny) {
    throw new RuntimeException('Composer autoload not found. Run composer install in admin-panel and root.');
}

// Load Flight framework class only (do not load vendor demo index.php).
if (!class_exists('Flight')) {
    require_once $appRoot.'/vendor/flightphp/core/flight/Flight.php';
}

spl_autoload_register(function ($class) use ($appRoot) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = $appRoot.'/app/'.$relative.'.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

AppState::$config = [
    'app.name' => 'BangronDB Studio',
    'paths.root' => $appRoot,
    'paths.repo' => $repoRoot,
    'paths.storage' => $appRoot.'/storage',
    'paths.storage_admin' => $appRoot.'/storage/admin',
    'paths.storage_tenants' => $appRoot.'/storage/tenants',
    'system.db_name' => 'admin',
    'installed.lock' => $appRoot.'/storage/installed.lock',
];

load_env($appRoot.'/.env');

if (!headers_sent()) {
    session_name('bangron_admin');
    session_start();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

ensure_dir(storage_path());
ensure_dir(admin_storage_path());
ensure_dir(tenant_path());

if (class_exists(\App\Services\SetupService::class)) {
    (new \App\Services\SetupService())->ensureBaselineSchema();
}

require_once $appRoot.'/app/routes.php';
