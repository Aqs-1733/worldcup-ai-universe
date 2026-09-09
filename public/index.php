<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Core\Router;

$router = new Router();

$router->get('/', [PageController::class, 'home']);
$router->get('/teams', [PageController::class, 'teams']);
$router->get('/teams/{id}', [PageController::class, 'team']);
$router->get('/players', [PageController::class, 'players']);
$router->get('/players/{id}', [PageController::class, 'player']);
$router->get('/worldcup', [PageController::class, 'worldcup']);
$router->get('/news', [PageController::class, 'news']);
$router->post('/news/refresh', [PageController::class, 'refreshNews']);
$router->get('/api/news/refresh', [PageController::class, 'refreshNewsApi']);
$router->get('/news/{id}', [PageController::class, 'newsShow']);
$router->get('/fan-space', [PageController::class, 'fanSpace']);
$router->post('/fan-space', [PageController::class, 'saveFanSpace']);
$router->get('/onboarding', [PageController::class, 'onboarding']);
$router->post('/onboarding', [PageController::class, 'saveOnboarding']);
$router->get('/ai-studio', [PageController::class, 'aiStudio']);
$router->post('/ai-studio', [PageController::class, 'aiStudioPost']);
$router->get('/vision', [PageController::class, 'vision']);
$router->post('/vision', [PageController::class, 'visionPost']);

$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'loginPost']);
$router->get('/register', [AuthController::class, 'register']);
$router->post('/register', [AuthController::class, 'registerPost']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/{table}', [AdminController::class, 'table']);
$router->post('/admin/{table}/save', [AdminController::class, 'save']);
$router->post('/admin/{table}/delete', [AdminController::class, 'delete']);

echo $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
