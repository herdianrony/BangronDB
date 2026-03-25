<?php

class AppState
{
    public static array $config = [];
}

function config(string $key, $default = null)
{
    return AppState::$config[$key] ?? $default;
}

function base_path(string $path = ''): string
{
    $root = config('paths.root', dirname(__DIR__));
    return $path ? $root . '/' . ltrim($path, '/\\') : $root;
}

function repo_path(string $path = ''): string
{
    $root = config('paths.repo', dirname(base_path()));
    return $path ? $root . '/' . ltrim($path, '/\\') : $root;
}

function storage_path(string $path = ''): string
{
    $root = config('paths.storage', base_path('storage'));
    return $path ? $root . '/' . ltrim($path, '/\\') : $root;
}

function tenant_path(string $path = ''): string
{
    $root = config('paths.storage_tenants', storage_path('tenants'));
    return $path ? $root . '/' . ltrim($path, '/\\') : $root;
}

function admin_storage_path(string $path = ''): string
{
    $root = config('paths.storage_admin', storage_path('admin'));
    return $path ? $root . '/' . ltrim($path, '/\\') : $root;
}

function ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function render(string $view, array $data = []): void
{
    $template = $view . '.latte';
    $viewFile = base_path('views/' . $template);
    if (!file_exists($viewFile)) {
        \Flight::halt(500, 'View not found: ' . htmlspecialchars($view));
    }

    $data['appName'] = config('app.name', 'BangronDB Studio');
    $data['currentUser'] = auth_user();

    $cacheDir = storage_path('latte_cache');
    ensure_dir($cacheDir);

    static $latte = null;
    if ($latte === null) {
        $latte = new Latte\Engine();
        $latte->setTempDirectory($cacheDir);
        $latte->setLoader(new Latte\Loaders\FileLoader(base_path('views')));
        $latte->addFunction('csrf_field', 'csrf_field');
        $latte->addFunction('flash', 'flash');
        $latte->addFunction('auth_user', 'auth_user');
        $latte->addFunction('config', 'config');
    }

    $latte->render($template, $data);
}

function redirect(string $url): void
{
    \Flight::redirect($url);
}

function request_get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function request_post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!empty($_SESSION['flash'][$key])) {
        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $value;
    }

    return null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    $token = request_post('_csrf');
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        \Flight::halt(403, 'Invalid CSRF token');
    }
}

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function load_env(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        $_ENV[$key] = $value;
    }
}

function auth_user()
{
    if (!class_exists('BangronDB\\Client')) {
        return null;
    }

    static $cachedUser = null;
    static $loaded = false;

    if ($loaded) {
        return $cachedUser;
    }

    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $authService = new \App\Services\AuthService();
    $cachedUser = $authService->getUserById($_SESSION['user_id']);

    return $cachedUser;
}
