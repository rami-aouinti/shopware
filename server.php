<?php

if (PHP_SAPI === 'cli-server') {
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . '/public' . ($url['path'] ?? '');

    // IMPORTANT: file_exists (pas is_file)
    if ($file !== __DIR__ . '/public/index.php' && file_exists($file)) {
        return false;
    }
}

require __DIR__ . '/public/index.php';
