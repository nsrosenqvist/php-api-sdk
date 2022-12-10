#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    exit(1);
}

# Set root as working directory
chdir(dirname(__DIR__));

# Add git hooks
foreach (glob(__DIR__ . '/hooks/*') as $path) {
    $name = basename($path);
    $target = "dev/hooks/{$name}";
    $link = ".git/hooks/$name";

    if (! is_link($link) && ! @file_exists($link)) {
        echo "Updating hooks: linking $name" . PHP_EOL;
        symlink($target, $link);
        chmod($target, 0770);
    }
}
