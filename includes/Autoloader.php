<?php

declare(strict_types=1);

namespace PropertyBookingSystem;

final class Autoloader
{
    private const BASE_NAMESPACE = 'PropertyBookingSystem\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $className): void
    {
        if (strpos($className, self::BASE_NAMESPACE) !== 0) {
            return;
        }

        $relativeClass = substr($className, strlen(self::BASE_NAMESPACE));
        if ($relativeClass === false) {
            return;
        }

        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $relativePath;

        if (is_readable($filePath)) {
            require_once $filePath;
        }
    }
}
