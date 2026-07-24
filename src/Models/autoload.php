<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

// Autoload for Model classes without namespace
spl_autoload_register(function ($class) {
    $modelDir = __DIR__;
    $classFile = $modelDir . DIRECTORY_SEPARATOR . $class . '.php';
    
    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    
    return false;
});
