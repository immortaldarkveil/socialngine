<?php

/**
 * LocalValetDriver for Socialngine (CodeIgniter 3)
 *
 * Herd/Valet expects apps to have a public/ directory.
 * This driver tells Herd to serve from the project root instead,
 * where index.php lives for this CI3 application.
 */
class LocalValetDriver extends \Valet\Drivers\BasicValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return file_exists($sitePath . '/index.php')
            && is_dir($sitePath . '/app/core/system');
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)/*: string|false*/
    {
        if (file_exists($staticFilePath = $sitePath . $uri)
            && is_file($staticFilePath)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): string
    {
        return $sitePath . '/index.php';
    }
}
