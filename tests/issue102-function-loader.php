<?php
declare(strict_types=1);

function load_functions(string $file, array $names): void
{
    $source = file_get_contents($file);
    foreach ($names as $name) {
        if (!preg_match('/\bfunction\s+' . preg_quote($name, '/') . '\s*\(/', $source, $hit, PREG_OFFSET_CAPTURE)) {
            throw new RuntimeException("Missing function $name");
        }

        $tokens = token_get_all('<?php ' . substr($source, $hit[0][1]));
        $body = '';
        $depth = 0;
        foreach (array_slice($tokens, 1) as $token) {
            $body .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                ++$depth;
            }
            if ($token === '}' && --$depth === 0) {
                eval($body);
                break;
            }
        }

        if (!function_exists($name)) {
            throw new RuntimeException("Incomplete function $name");
        }
    }
}
