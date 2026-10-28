<?php

// Run from the existing app container; never migrates/seeds or opens a DB.
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2);
foreach ([$root . '/storage/app/public', $root . '/storage/app/public/avatars'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) throw new RuntimeException('Cannot create avatar directory.');
    if (PHP_OS_FAMILY !== 'Windows' && function_exists('posix_geteuid') && posix_geteuid() === 0) {
        chown($directory, 'www-data');
        chgrp($directory, 'www-data');
        chmod($directory, 0775);
    }
}
$link = $root . '/public/storage';
$target = $root . '/storage/app/public';
if (file_exists($link) || is_link($link)) {
    if (realpath($link) !== realpath($target)) throw new RuntimeException('public/storage already points elsewhere; refusing to overwrite it.');
} elseif (!symlink('../storage/app/public', $link)) {
    throw new RuntimeException('Cannot create public/storage symlink.');
}
echo "Avatar public disk directories/link ready. No database changes.\n";
