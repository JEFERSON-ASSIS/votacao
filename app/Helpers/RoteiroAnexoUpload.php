<?php
declare(strict_types=1);

namespace App\Helpers;

final class RoteiroAnexoUpload
{
    private const MAX_BYTES = 15 * 1024 * 1024;
    private const EXTENSOES = [
        'pdf', 'doc', 'docx', 'txt', 'odt', 'rtf',
        'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'webp', 'gif',
    ];
    private const IMAGENS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** @param array<string, mixed> $upload @return array<string, mixed> */
    public static function salvar(array $upload, int $sessaoId, int $passoId): array
    {
        $erro = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE
                ? 'O arquivo excede o limite permitido.'
                : 'Não foi possível receber o arquivo enviado.');
        }
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $nome = trim((string) ($upload['name'] ?? ''));
        $tamanho = (int) ($upload['size'] ?? 0);
        if (!is_uploaded_file($tmp) || $tamanho < 1 || $tamanho > self::MAX_BYTES) {
            throw new \RuntimeException('O arquivo deve ter no máximo 15 MB.');
        }
        $ext = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSOES, true)) {
            throw new \RuntimeException('Formato não permitido. Envie documento, planilha, apresentação ou imagem.');
        }
        $mime = (string) ($upload['type'] ?? 'application/octet-stream');
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) (finfo_file($finfo, $tmp) ?: $mime);
                finfo_close($finfo);
            }
        }
        if (in_array($mime, ['application/x-dosexec', 'application/x-executable', 'application/x-sharedlib', 'text/x-php', 'application/x-httpd-php'], true)) {
            throw new \RuntimeException('O tipo real do arquivo não é permitido.');
        }
        if (in_array($ext, self::IMAGENS, true) && !str_starts_with($mime, 'image/')) {
            throw new \RuntimeException('O conteúdo enviado não corresponde a uma imagem válida.');
        }

        $relDir = 'uploads/roteiro/' . $sessaoId . '/' . $passoId;
        $dir = dirname(__DIR__, 2) . '/public/' . $relDir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível preparar a pasta de anexos.');
        }
        $arquivo = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $arquivo)) {
            throw new \RuntimeException('Não foi possível salvar o arquivo.');
        }
        return [
            'tipo' => in_array($ext, self::IMAGENS, true) ? 'IMAGEM' : 'ARQUIVO',
            'arquivo' => $relDir . '/' . $arquivo,
            'nome_original' => mb_substr(basename($nome), 0, 255),
            'mime_type' => mb_substr($mime, 0, 120),
            'tamanho_bytes' => $tamanho,
        ];
    }

    public static function excluir(?string $arquivo): void
    {
        $arquivo = str_replace('\\', '/', trim((string) $arquivo));
        if ($arquivo === '' || !str_starts_with($arquivo, 'uploads/roteiro/') || str_contains($arquivo, '..')) {
            return;
        }
        $base = realpath(dirname(__DIR__, 2) . '/public/uploads/roteiro');
        $alvo = realpath(dirname(__DIR__, 2) . '/public/' . $arquivo);
        if ($base && $alvo && str_starts_with($alvo, $base . DIRECTORY_SEPARATOR) && is_file($alvo)) {
            unlink($alvo);
        }
    }
}
