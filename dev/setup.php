#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);

# Add git hooks
foreach (glob(__DIR__ . '/hooks/*') as $path) {
    $name = basename($path);
    $target = realpath("dev/hooks/$name");
    $link = $root . "/.git/hooks/$name";

    if (! is_link($link) && ! @file_exists($link)) {
        echo "Updating hooks: linking $name" . PHP_EOL;
        symlink($target, $link);
        chmod($target, 0770);
    }
}
