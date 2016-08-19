<?php
namespace Slimpd\Modules\authentication;
try {
    $app->container->singleton('auth', function() {
		return new Authentication();
    });
} catch(Exception $e) {
    if ($debug) {
        echo '<pre><br><br>' . $e->getMessage() . '<br><br></pre>';
    }
};
