<?php

include 'metadata.php';

$extendHooks = [];
$invokeHooks = [];
$publicExtensionMethodList = [];
$hookCalls = [];

// find project root
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

// recursively search files for something
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
                    searchForPublicExtensionMethods($path);
                } elseif ($what == 'hookCalls') {
                    searchForHookCalls($path);
                } else {
                    throw new Exception("Unknown search type: $what");
                }
            }
        }
    }
}

// search for ->extend() and ->invokeWithExtensions()
function searchForHooks($path) {
    global $extendHooks;
    global $invokeHooks;
    $c = file_get_contents($path);
    $c = str_replace("[\n ]", '', $c);
    preg_match_all('#->extend\([\'"](.+?)[\'"]#', $c, $m);
    for ($i = 0; $i < count($m[0]); $i++) {
        $name = $m[1][$i];
        $extendHooks[$name] = true;
    }
    preg_match_all('#->invokeWithExtensions\([\'"](.+?)[\'"]#', $c, $m);
    for ($i = 0; $i < count($m[0]); $i++) {
        $name = $m[1][$i];
        $invokeHooks[$name] = true;
    }
}

// search for implementations of extension methods that are public
function searchForPublicExtensionMethods($path) {
    global $publicExtensionMethodList;
    global $projectRoot;
    global $doNotMakeHookProtected;
    global $allHooks;
    $c = file_get_contents($path);
    preg_match('#vendor/(.+?)/(.+?)/#', $path, $m);
    $module = $m[1] . '/' . $m[2];
    $moduleFile = str_replace("$projectRoot/vendor/$module/", '', $path);
    foreach (array_keys($allHooks) as $hook) {
        if (in_array($hook, $doNotMakeHookProtected)) {
            continue;
        }
        if (str_contains($c, 'public function ' . $hook . '(')) {
            // echo "Found $hook() in $file\n";
            $publicExtensionMethodList[$module][$moduleFile][$hook] = 1;
        }
    }
}

// search for calls to hooks - means they shouldn't be make public
function searchForHookCalls($path) {
    global $allHooks;
    global $hookCalls;
    $c = file_get_contents($path);
    foreach (array_keys($allHooks) as $hook) {
        if (str_contains($c, '->' . $hook . '(')) {
            $hookCalls[$hook] = true;
        }
    }
}

$projectRoot = findProjectRoot();
foreach ($accounts as $account) {
    searchFiles('hooks', "$projectRoot/vendor/$account");
}

$allHooks = array_merge(array_keys($extendHooks), array_keys($invokeHooks));
$allHooks = array_fill_keys($allHooks, true);
ksort($allHooks);

// used to populate $doNotMakeHookProtected
foreach ($accounts as $account) {
    searchFiles('hookCalls', "$projectRoot/vendor/$account");
}
ksort($hookCalls);
foreach (array_keys($hookCalls) as $hook) {
    // echo "'$hook',\n";
}

// used to populate $makeHookProtected
foreach (array_keys($allHooks) as $hook) {
    if (!array_key_exists($hook, $hookCalls)) {
        // echo "'$hook',\n";
    }
}

// find extension methods in files
foreach ($accounts as $account) {
    searchFiles('extensionMethods', "$projectRoot/vendor/$account");
}

$updatedModules = [];

// ksort $publicExtensionMethodList
$out = '';
ksort($publicExtensionMethodList);
foreach (array_keys($publicExtensionMethodList) as $module) {
    $out .= "\n$module\n";
    ksort($publicExtensionMethodList[$module]);
    foreach (array_keys($publicExtensionMethodList[$module]) as $file) {
        $out .= "\n  $file\n";
        $c = file_get_contents("$projectRoot/vendor/$module/$file");
        $oc = $c;
        ksort($publicExtensionMethodList[$module][$file]);
        foreach (array_keys($publicExtensionMethodList[$module][$file]) as $hook) {
            if (!in_array($hook, $makeHookProtected)) {
                continue;
            }
            $out .= "\n    $hook\n";
            $c = str_replace('public function ' . $hook . '(', 'protected function ' . $hook . '(', $c);
            if ($c == $oc) {
                continue;
            }
            file_put_contents("$projectRoot/vendor/$module/$file", $c);
            $updatedModules[$module] = true;
        }
    }
}
// file_put_contents('make-protected.txt', $out);

echo "Updated modules:\n";
foreach (array_keys($updatedModules) as $module) {
    echo "$module\n";
}
