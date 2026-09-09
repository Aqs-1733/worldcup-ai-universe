<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layout'): string
    {
        $viewFile = ROOT_PATH . '/views/' . $view . '.php';
        $layoutFile = ROOT_PATH . '/views/' . $layout . '.php';

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        $viewFile = ROOT_PATH . '/views/' . $view . '.php';
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        return (string) ob_get_clean();
    }
}
