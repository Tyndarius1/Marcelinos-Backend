<?php

namespace App\Support;

class EnvEditor
{
    /**
     * @param  array<string, scalar|null>  $pairs
     */
    public static function updateMany(array $pairs): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $contents = (string) file_get_contents($envPath);

        foreach ($pairs as $key => $value) {
            $normalized = self::normalizeValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, "{$key}={$normalized}", $contents);
                continue;
            }

            $contents .= PHP_EOL."{$key}={$normalized}";
        }

        file_put_contents($envPath, $contents);
    }

    private static function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = trim((string) $value);

        if ($string === '') {
            return '';
        }

        if (preg_match('/\s/', $string) === 1 || str_contains($string, '#')) {
            return '"'.addcslashes($string, '"').'"';
        }

        return $string;
    }
}

