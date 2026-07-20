<?php
declare(strict_types=1);

namespace App\Helpers;

final class UserFotoUpload
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function handle(int $userId, ?string $currentPath, bool $remove): ?string
    {
        if ($remove && $currentPath !== null && $currentPath !== '') {
            self::deleteFile($currentPath);
            return null;
        }

        if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
            return $currentPath !== '' && $currentPath !== null ? $currentPath : null;
        }

        $file = $_FILES['foto'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $currentPath !== '' && $currentPath !== null ? $currentPath : null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $currentPath !== '' && $currentPath !== null ? $currentPath : null;
        }

        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return $currentPath !== '' && $currentPath !== null ? $currentPath : null;
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/vereadores/' . $userId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $relative = 'uploads/vereadores/' . $userId . '/foto_' . date('Ymd_His') . '.' . $ext;
        $target = dirname(__DIR__, 2) . '/public/' . $relative;

        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return $currentPath !== '' && $currentPath !== null ? $currentPath : null;
        }

        if ($currentPath !== null && $currentPath !== '' && $currentPath !== $relative) {
            self::deleteFile($currentPath);
        }

        return $relative;
    }

    public static function deleteFile(?string $relative): void
    {
        $relative = trim((string) $relative);
        if ($relative === '') {
            return;
        }
        $path = dirname(__DIR__, 2) . '/public/' . ltrim($relative, '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
