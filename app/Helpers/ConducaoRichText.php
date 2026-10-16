<?php
declare(strict_types=1);

namespace App\Helpers;

final class ConducaoRichText
{
    private const TAGS = '<p><div><br><strong><b><em><i><u><ul><ol><li>';

    public static function sanitize(string $html): string
    {
        $html = trim(str_replace(["\r\n", "\r"], "\n", $html));
        if ($html === '') {
            return '';
        }
        if (!preg_match('/<\/?(?:p|div|br|strong|b|em|i|u|ul|ol|li)\b/i', $html)) {
            return $html;
        }

        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? '';
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $html = strip_tags($html, self::TAGS);
        $html = preg_replace_callback(
            '/<(\/)?(p|div|br|strong|b|em|i|u|ul|ol|li)\b([^>]*)>/i',
            static function (array $match): string {
                $closing = ($match[1] ?? '') === '/';
                $tag = strtolower((string) ($match[2] ?? ''));
                $tag = $tag === 'b' ? 'strong' : ($tag === 'i' ? 'em' : $tag);
                if ($tag === 'br') {
                    return '<br>';
                }
                if ($closing) {
                    return '</' . $tag . '>';
                }

                $attributes = (string) ($match[3] ?? '');
                $alignment = '';
                if (in_array($tag, ['p', 'div'], true)) {
                    if (preg_match('/\btext-align\s*:\s*(left|center|right)\b/i', $attributes, $styleMatch)) {
                        $alignment = strtolower((string) $styleMatch[1]);
                    } elseif (preg_match('/\balign\s*=\s*["\']?(left|center|right)\b/i', $attributes, $alignMatch)) {
                        $alignment = strtolower((string) $alignMatch[1]);
                    }
                }

                return '<' . $tag . ($alignment !== '' ? ' style="text-align: ' . $alignment . '"' : '') . '>';
            },
            $html
        ) ?? '';

        return trim($html);
    }

    public static function editorHtml(string $value): string
    {
        $safe = self::sanitize(ConducaoTexto::acentuarTextoPresidente($value));
        if ($safe === '') {
            return '';
        }
        if (preg_match('/<\/?(?:p|div|br|strong|em|u|ul|ol|li)\b/i', $safe)) {
            return $safe;
        }

        return nl2br(htmlspecialchars($safe, ENT_QUOTES, 'UTF-8'));
    }

    public static function render(string $value): string
    {
        return self::editorHtml($value);
    }

    public static function plain(string $value): string
    {
        $safe = self::sanitize(ConducaoTexto::acentuarTextoPresidente($value));
        $safe = preg_replace('/<br\s*\/?\s*>/i', "\n", $safe) ?? $safe;
        $safe = preg_replace('/<\/(?:p|div|li)>/i', "\n", $safe) ?? $safe;
        $safe = strip_tags($safe);
        $safe = html_entity_decode($safe, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $safe) ?? $safe);
    }
}
