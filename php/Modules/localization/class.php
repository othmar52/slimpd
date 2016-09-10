<?php
namespace Slimpd\Modules\localization;
/* Copyright
 *
 */
try {
	$app->container->singleton('ll', function() {
		return new Localization();
	});
} catch(Exception $e) {
	if ($debug) {
		echo '<pre><br><br>' . $e->getMessage() . '<br><br></pre>';
	}
};
