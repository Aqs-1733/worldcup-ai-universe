<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:mixed}> */
    private array $routes = [];

    public function get(string $pattern, mixed $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, mixed $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => '/' . trim($pattern, '/'),
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        if ($path === '//') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            return $this->call($route['handler'], $params);
        }

        http_response_code(404);
        return View::render('errors/404', ['title' => '页面不存在']);
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $paramNames = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $pattern);

        if ($regex === null || preg_match('#^' . $regex . '$#', $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);
        return array_combine($paramNames, array_map('urldecode', $matches)) ?: [];
    }

    /** @param array<string, string> $params */
    private function call(mixed $handler, array $params): mixed
    {
        if ($handler instanceof Closure) {
            return $handler(...array_values($params));
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            return (new $class())->{$method}(...array_values($params));
        }

        throw new RuntimeException('Invalid route handler.');
    }
}
