<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Flash;

define('ROOT_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

Env::load(ROOT_PATH . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Shanghai'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function app_name(): string
{
    return (string) env('APP_NAME', basename(ROOT_PATH));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return $path === '//' ? '/' : $path;
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function flash(?string $key = null): mixed
{
    return $key === null ? Flash::all() : Flash::get($key);
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 302);
    exit;
}

function is_chinese_text(?string $text): bool
{
    return $text !== null && preg_match('/\p{Han}/u', $text) === 1;
}

function display_name(?string $cn, ?string $original): string
{
    $cn = trim((string) $cn);
    $original = trim((string) $original);
    if ($cn !== '' && $original !== '' && $cn !== $original) {
        return $cn . ' / ' . $original;
    }
    return $cn !== '' ? $cn : $original;
}

function flag_name(array $row, string $prefix = ''): string
{
    $flag = (string) ($row[$prefix . 'flag_emoji'] ?? $row['flag_emoji'] ?? '');
    $cn = (string) ($row[$prefix . 'name_cn'] ?? $row['name_cn'] ?? $row[$prefix . 'team_name_cn'] ?? '');
    $original = (string) ($row[$prefix . 'name_original'] ?? $row['name_original'] ?? $row[$prefix . 'team_name_original'] ?? '');
    $name = display_name($cn, $original);
    return trim(($flag ? $flag . ' ' : '') . $name);
}

function flag_html(?string $flagUrl, ?string $flagEmoji = '', ?string $alt = ''): string
{
    $alt = $alt !== '' ? $alt : 'flag';
    if ($flagUrl) {
        return '<img class="inline-flag" src="' . e($flagUrl) . '" alt="' . e($alt) . '" loading="lazy">';
    }
    if ($flagEmoji) {
        return '<span class="emoji-flag">' . e($flagEmoji) . '</span>';
    }
    return '<span class="emoji-flag empty-flag"></span>';
}

function team_name_html(?string $flagUrl, ?string $flagEmoji, ?string $cn, ?string $original): string
{
    $name = display_name($cn, $original);
    return '<span class="team-name-inline">' . flag_html($flagUrl, $flagEmoji, $name) . '<span>' . e($name) . '</span></span>';
}

function date_label(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    return date('Y-m-d H:i', strtotime($datetime));
}
