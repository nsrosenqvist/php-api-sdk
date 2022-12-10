#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use Minicli\App;
use Minicli\Input;
use Minicli\Command\CommandCall;

// Create app
$app = new App();
$root = dirname(__DIR__);
chdir($root);

$app->registerCommand('default', function (CommandCall $input) use ($app, $root) {
    $version = trim(file_get_contents("{$root}/VERSION"));
    $composer = json_decode(file_get_contents("{$root}/composer.json"));

    // Print command info
    $title = $composer->extra->title ?? $composer->name;
    $description = $composer->description;
    $name = ($title !== $composer->name) ? " ({$composer->name})" : '';

    $app->printer->newline();
    $app->printer->out("{$title}{$name} v{$version}", 'bold');
    $app->printer->newline();
    $app->printer->out($description, 'dim');
    $app->printer->newline();
    $app->printer->info(' Create new release ', true);

    // Print current version
    $app->printer->info("Current version: {$version}");

    // Print latest git tag
    $latest = @shell_exec('git describe --tags --abbrev=0 2> /dev/null');

    if (! is_null($latest)) {
        $app->printer->info("Latest tag: {$latest}");
    }

    // Get new release tag
    $app->printer->display('Enter new semver version number or CTRL+C to abort:');
    $tag = trim((new Input('Version: '))->read());

    if (empty($tag)) {
        $app->printer->error('Tag must not be empty');
        exit(2);
    }

    // Confirm creation
    $app->printer->newline();
    $app->printer->out('You will create a release with the tag ', 'info');
    $app->printer->out(" {$tag} ", 'info_alt');
    $app->printer->newline();
    $app->printer->info("Make sure to unstage or stash changes you don't want included in the release commit");
    $app->printer->display('Do you wish to continue?');
    $continue = trim((new Input('Y/n: '))->read());

    if (! empty($continue) && strtoupper(substr($continue, 0, 1)) !== 'Y') {
        $app->printer->newline();
        $app->printer->out("Aborting", 'dim');
        $app->printer->newline();
        exit(0);
    }

    /* ---------------------------------------------- */
    /*  Proceed with release                          */
    /* ---------------------------------------------- */
    $app->printer->out("Creating release ", 'info');
    $app->printer->out(" {$tag} ", 'info_alt');
    $app->printer->newline();
    $app->printer->newline();

    // Lint
    $app->printer->out('Verify code is adhering to standards ... ', 'dim');
    exec('composer lint 2> /dev/null', $output, $result);

    if ($result > 0) {
        $app->printer->out('X', 'error');
        $app->printer->newline();
        $app->printer->error(' Linting failed! Run lint independently and review errors ', true);
        exit(3);
    } else {
        $app->printer->out('✔', 'success');
        $app->printer->newline();
    }

    // Run tests
    $app->printer->out('Verify code is passing tests ... ', 'dim');
    exec('composer test 2> /dev/null', $output, $result);

    if ($result > 0) {
        $app->printer->out('X', 'error');
        $app->printer->newline();
        $app->printer->error(' Tests failed! Run tests independently and review errors ', true);
        exit(3);
    } else {
        $app->printer->out('✔', 'success');
        $app->printer->newline();
    }

    // Update VERSION
    $app->printer->out("Writing to VERSION ... ", 'dim');
    file_put_contents("{$root}/VERSION", $tag);
    $app->printer->out('✔', 'success');
    $app->printer->newline();

    // Commit changes
    $app->printer->out("Committing updates ... ", 'dim');
    shell_exec('git add VERSION');
    shell_exec(sprintf('git commit -m %s', escapeshellarg("Release {$tag}")));
    $app->printer->out('✔', 'success');
    $app->printer->newline();

    // Tag release
    $app->printer->out("Tagging release ... ", 'dim');
    shell_exec(sprintf('git tag %s', escapeshellarg($tag)));
    $app->printer->out('✔', 'success');
    $app->printer->newline();
});

$app->runCommand([basename(__FILE__), 'default']);
