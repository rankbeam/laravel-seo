<?php

declare(strict_types=1);

// Write an isolated ini, without changing the host's PHP installation.
$output = $argv[1] ?? '';
if ($output === '' || file_exists($output)) {
    throw new RuntimeException('Pass a new output ini path.');
}
$files = array_filter(array_merge([php_ini_loaded_file()], preg_split('/,\s*/', php_ini_scanned_files() ?: '') ?: []));
$ini = '';
foreach ($files as $file) {
    $contents = file_get_contents(trim($file));
    $ini .= preg_replace('/^[ \t]*(?:zend_)?extension[ \t]*=[^\r\n]*intl[^\r\n]*/mi', '; intl omitted for this consumer only', $contents)."\n";
}
$ini .= "\n[PHP]\nextension_dir=\"".ini_get('extension_dir')."\"\n";
file_put_contents($output, $ini);
echo "Use -c {$output} with PHP_INI_SCAN_DIR pointing at an empty directory.\n";
