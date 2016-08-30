<?php
namespace Slimpd;

function classAutoLoader($class) {

    $path = explode('\\', $class);
    
    if (isset($path[0]) === false) {
        return false;
    }
    
    $filename = array_slice($path, -1, 1);
    
    if ($path[0] === 'Slimpd') {
        $path = array_slice($path, 1, -1);
    }
    
    $classFile = __DIR__ 
                . DS 
                . implode(DS, $path) 
                . DS
                . $filename[0] 
                . '.php';
    if (is_file($classFile) === true && class_exists($class) === false) {
        require_once $classFile;
    }
    
}
spl_autoload_register('\Slimpd\classAutoLoader');