<?php

namespace App\Support;

class SafeHtml
{
    public static function clean(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $html = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html) ?? '';
        $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/is', '', $html) ?? '';

        return strip_tags($html, '<p><br><strong><b><em><i><u><ol><ul><li><blockquote><a><img>');
    }
}
