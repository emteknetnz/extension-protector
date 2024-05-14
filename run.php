<?php

function searchFiles($dir) {
    $files = scandir($dir);
    
    foreach($files as $file) {
        $path = $dir . '/' . $file;
        
        // Ignore current and parent directory pointers
        if($file == '.' || $file == '..') {
            continue;
        }
        
        if(is_dir($path)) {
            searchFiles($path);
        } else {
            if(pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                searchForString($path);
            }
        }
    }
}

function searchForString($file) {
    $content = file_get_contents($file);
    if(strpos($content, "->extend('") !== false) {
        echo "Found in file: $file\n";
    }
}

// Start searching from the current directory
$searchDir = '.';
searchFiles('/var/www/vendor/silverstripe/framework/src');
