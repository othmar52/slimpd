<?php
namespace Slimpd\Modules\configloader_ini;

try {
    $app->container->singleton('configLoaderINI', function() {
        return new ConfigLoaderINI(__DIR__ . '/../../../config/');
    });
} catch(Exception $e) {
    if ($debug) {
        echo '<pre><br><br>' . $e->getMessage() . '<br><br></pre>';
    }
}

