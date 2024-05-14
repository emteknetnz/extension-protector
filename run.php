<?php

$excludeHooks = [
    'init',
    'validate',
];

$extendList = [];
$invokeList = [];
$extensionMethodList = [];

function findProjectRoot() {
    $currentDir = __DIR__;
    for ($i = 0; $i < 7; $i++) {
        if (!file_exists("$currentDir/.env")) {
            $currentDir = dirname($currentDir);
        } else {
            break;
        }
        if ($i == 7) {
            throw new Exception('Could not find project root');
        }
    }
    return $currentDir;
}

function searchFiles($what, $dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        if (str_contains($path, '/tests/')) {
            continue;
        }
        if (is_dir($path)) {
            searchFiles($what, $path);
        } else {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                if ($what == 'hooks') {
                    searchForHooks($path);
                } elseif ($what == 'extensionMethods') {
                    searchForExtensionMethods($path);
                }
            }
        }
    }
}

function searchForHooks($path) {
    global $extendList;
    global $invokeList;
    $c = file_get_contents($path);
    if (preg_match('#->extend\([\'"](.+?)[\'"]#', $c, $m)) {
        $name = $m[1];
        $extendList[$name] = true;
    }
    if (preg_match('#->invokeWithExtensions\([\'"](.+?)[\'"]#', $c, $m)) {
        $name = $m[1];
        $invokeList[$name] = true;
    }
}

function searchForExtensionMethods($path) {
    global $extendList;
    global $invokeList;
    global $extensionMethodList;
    global $projectRoot;
    global $excludeHooks;
    $c = file_get_contents($path);
    preg_match('#vendor/(.+?)/(.+?)/#', $path, $m);
    $module = $m[1] . '/' . $m[2];
    $moduleFile = str_replace("$projectRoot/vendor/$module/", '', $path);
    foreach (array_keys($extendList) as $hook) {
        if (in_array($hook, $excludeHooks)) {
            continue;
        }
        if (str_contains($c, 'public function ' . $hook . '(')) {
            // echo "Found $hook() in $file\n";
            $extensionMethodList[$module][$moduleFile][$hook] = 1;
        }
    }
}

$projectRoot = findProjectRoot();
searchFiles('hooks', "$projectRoot/vendor/silverstripe");

ksort($extendList);
ksort($invokeList);
// echo "\nextendList\n";
// print_r($extendList);
// echo "\ninvokeList\n";
// print_r($invokeList);

searchFiles('extensionMethods', "$projectRoot/vendor/silverstripe");

ksort($extensionMethodList);
foreach (array_keys($extensionMethodList) as $module) {
    echo "\n$module\n";
    ksort($extensionMethodList[$module]);
    foreach (array_keys($extensionMethodList[$module]) as $file) {
        echo "\n  $file\n";
        ksort($extensionMethodList[$module][$file]);
        foreach (array_keys($extensionMethodList[$module][$file]) as $hook) {
            echo "    $hook\n";
        }
    }
}
