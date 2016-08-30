<?php
namespace Slimpd\Modules\imageweighter;
try {
    $app->container->singleton('imageweighter', function() {
    
		return new Imageweighter();
    });
} catch(Exception $e) {
    if ($debug) {
        echo '<pre><br><br>' . $e->getMessage() . '<br><br></pre>';
    }
};
