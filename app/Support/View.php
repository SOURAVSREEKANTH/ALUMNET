<?php

declare(strict_types=1);

namespace AlumniConnect\Support;

use AlumniConnect\Security\Session;
use RuntimeException;

final class View
{
    public static function escape(string|int|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function render(string $view, array $data = []): void
    {
        $root = dirname(__DIR__, 2);
        $viewPath = $root . '/app/Views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new RuntimeException('The requested page template is unavailable.');
        }

        extract($data, EXTR_SKIP);
        $pageTitle = $pageTitle ?? 'AlumniConnect';
        $currentUser = $currentUser ?? null;
        $isHome = $isHome ?? false;

        ob_start();
        require $viewPath;
        $pageContent = ob_get_clean();
        $flashes = Session::takeFlashesIfActive();
        require $root . '/app/Views/layout.php';
    }

    public static function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }

    public static function error(int $status, string $title, string $message): void
    {
        http_response_code($status);
        self::render('pages/error', [
            'pageTitle' => $title . ' — AlumniConnect',
            'errorTitle' => $title,
            'errorMessage' => $message,
        ]);
    }
}
