<?php

$dir = __DIR__.'/app/Http/Controllers/Api';

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir)
);

$count = 0;

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getRealPath());

        // We want to replace:
        // ], $e->getCode() ?: 400);
        // or
        // ], $e->getCode() ?: 401);
        // or
        // ], $e->getCode() ?: 404);

        // Actually, it's safer to just replace:
        // ], $e->getCode() ?: 400);
        // with:
        // ], (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() <= 599) ? $e->getCode() : 400);

        $pattern = "/\], \\\$e->getCode\(\) \?: (\d{3})\);/";

        $newContent = preg_replace_callback($pattern, function ($matches) {
            $default = $matches[1];

            return '], (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() <= 599) ? $e->getCode() : '.$default.');';
        }, $content);

        // wait, what if $e->getCode() returns a string like "404"? is_int() would fail.
        // Let's cast it to int.
        // ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);

        $newContent = preg_replace_callback($pattern, function ($matches) {
            $default = $matches[1];

            return '], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : '.$default.');';
        }, $content);

        if ($newContent !== $content) {
            file_put_contents($file->getRealPath(), $newContent);
            $count++;
            echo 'Updated: '.$file->getFilename()."\n";
        }
    }
}

echo "Updated $count files.\n";
