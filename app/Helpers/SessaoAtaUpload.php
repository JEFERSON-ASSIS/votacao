<?php
declare(strict_types=1);

namespace App\Helpers;

final class SessaoAtaUpload
{
    private const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'txt', 'odt', 'rtf'];

    public static function handleUpload(int $sessaoId, string $field, ?string $currentPath, bool $remove): ?string
    {
        if ($remove && $currentPath !== null && $currentPath !== '') {
            self::deleteFile($currentPath);
            return null;
        }

        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return $currentPath !== '' ? $currentPath : null;
        }

        $file = $_FILES[$field];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $currentPath !== '' ? $currentPath : null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $currentPath !== '' ? $currentPath : null;
        }

        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return $currentPath !== '' ? $currentPath : null;
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/sessoes/' . $sessaoId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME)) ?: 'ata';
        $relative = 'uploads/sessoes/' . $sessaoId . '/' . $field . '_' . date('Ymd_His') . '_' . $safe . '.' . $ext;
        $target = dirname(__DIR__, 2) . '/public/' . $relative;

        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return $currentPath !== '' ? $currentPath : null;
        }

        if ($currentPath !== null && $currentPath !== '' && $currentPath !== $relative) {
            self::deleteFile($currentPath);
        }

        return $relative;
    }

    public static function copyFromPath(?string $sourceRelative, int $targetSessaoId, string $fieldPrefix): ?string
    {
        $sourceRelative = trim((string) $sourceRelative);
        if ($sourceRelative === '') {
            return null;
        }

        $source = dirname(__DIR__, 2) . '/public/' . ltrim($sourceRelative, '/');
        if (!is_file($source)) {
            return null;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return null;
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/sessoes/' . $targetSessaoId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $relative = 'uploads/sessoes/' . $targetSessaoId . '/' . $fieldPrefix . '_copia_' . date('Ymd_His') . '.' . $ext;
        $target = dirname(__DIR__, 2) . '/public/' . $relative;

        if (!@copy($source, $target)) {
            return null;
        }

        return $relative;
    }

    public static function deleteFile(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return;
        }
        $full = dirname(__DIR__, 2) . '/public/' . ltrim($relativePath, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public static function publicUrl(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return null;
        }
        return base_url($relativePath);
    }

    public static function isPdf(?string $relativePath): bool
    {
        return strtolower(pathinfo((string) $relativePath, PATHINFO_EXTENSION)) === 'pdf';
    }

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        return self::ALLOWED_EXT;
    }
}
