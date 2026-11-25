<?php

declare(strict_types=1);

/**
 * Run from the Laravel project root after extracting this package:
 * php fix_three_test_failures/tools/apply-three-test-fixes.php
 *
 * Replaces ONLY four known test blocks; never overwrites the entire test file.
 */

$projectRoot = getcwd();
$packageRoot = dirname(__DIR__);

function section(string $text, string $start, string $end, string $file): array
{
    $a = strpos($text, $start);
    if ($a === false) {
        throw new RuntimeException("Cannot locate start marker in {$file}: {$start}");
    }

    $b = strpos($text, $end, $a + strlen($start));
    if ($b === false) {
        throw new RuntimeException("Cannot locate end marker in {$file}: {$end}");
    }

    return [$a, $b];
}

function replaceBlock(
    string $current,
    string $replacementFile,
    string $sourceStart,
    string $targetStart,
    string $ending,
    string $targetFile
): string {
    $replacement = file_get_contents($replacementFile);
    if ($replacement === false) {
        throw new RuntimeException("Cannot open patch file {$replacementFile}");
    }

    [$sourceA, $sourceB] = section($replacement, $sourceStart, $ending, $replacementFile);
    [$targetA, $targetB] = section($current, $targetStart, $ending, $targetFile);

    $newSection = substr($replacement, $sourceA, $sourceB - $sourceA);

    return substr($current, 0, $targetA).$newSection.substr($current, $targetB);
}

$backendPath = $projectRoot.'/tests/Feature/BackendIntegrationFixesTest.php';
$multiPath = $projectRoot.'/tests/Feature/MultiStoreCheckoutTest.php';

try {
    foreach ([$backendPath, $multiPath] as $path) {
        if (! is_file($path)) {
            throw new RuntimeException('Not found: '.$path.' (run from Laravel project root)');
        }
    }

    $backend = file_get_contents($backendPath);
    $multi = file_get_contents($multiPath);
    if ($backend === false || $multi === false) {
        throw new RuntimeException('Could not read the existing Pest tests.');
    }

    $backendPatch = $packageRoot.'/tests/Feature/BackendIntegrationFixesTest.php';
    $multiPatch = $packageRoot.'/tests/Feature/MultiStoreCheckoutTest.php';

    $backend = replaceBlock(
        $backend, $backendPatch,
        'function makePatchUser(', 'function makePatchUser(',
        "it('returns available products from an open active store",
        $backendPath
    );

    $backend = replaceBlock(
        $backend, $backendPatch,
        "it('prevents buyers and admins from entering seller-only endpoints'",
        "it('prevents buyers and admins from entering seller-only endpoints'",
        "it('supports authenticated seller image uploads",
        $backendPath
    );

    $backend = replaceBlock(
        $backend, $backendPatch,
        "it('supports authenticated seller image uploads",
        "it('supports authenticated seller image uploads",
        "it('renders the branded HTML receipt",
        $backendPath
    );

    $targetMailStart = str_contains($multi, "it('passes order receipt email to Laravel Mail'")
        ? "it('passes order receipt email to Laravel Mail'"
        : "it('passes the order receipt to Laravel Mail as HTML and plain text'";

    $multi = replaceBlock(
        $multi, $multiPatch,
        "it('passes the order receipt to Laravel Mail as HTML and plain text'",
        $targetMailStart,
        "it('dispatches due notification outbox jobs'",
        $multiPath
    );

    $stamp = date('Ymd-His');
    foreach ([
        $backendPath => $backend,
        $multiPath => $multi,
    ] as $path => $content) {
        $backup = $path.'.backup-'.$stamp;
        if (! copy($path, $backup)) {
            throw new RuntimeException('Unable to make backup: '.$backup);
        }
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Unable to write: '.$path);
        }
        echo "Updated: {$path}\nBackup: {$backup}\n";
    }

    echo "Now run: php artisan test\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage().PHP_EOL);
    exit(1);
}
