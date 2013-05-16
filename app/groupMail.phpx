<?php

define('CACHE', APP . '/cache');
define('CLASSES', APP . '/classes');
define('LIBS', APP . '/libs');
define('MODELS', APP . '/models');
define('PROCESSORS', APP . '/processors');
define('ROUTERS', APP . '/routers');
define('SERVICES', APP . '/services');
define('TEMPLATES', APP . '/templates');

define('CSS', '/css');
define('IMG', '/img');
define('JS', '/js');
define('LOGS', '/logs');
define('TEMP', '/temp');

require_once(LIBS . '/autoload.php');

spl_autoload_register(
	function($className) {
		if (file_exists(CLASSES . "/$className.php")) require_once(CLASSES . "/$className.php");
	}
);

if (DEBUG) {
	TimeDebug::init(CACHE,
		array(
			'advancedLog' => ADVANCEDLOG,
			'local' => LOCAL,
			'root' => ROOT,
			'startTime' => NOW,
			'pathConstants' => array('CLASSES', 'LIBS', 'MODELS', 'PROCESSORS', 'ROUTERS', 'SERVICES', 'TEMPLATES', 'APP'),
			'urlLength' => 4000,
		)
	);
}

App::dump(TimeDebug::$request);

$app = new App('GMasdasdasdasdasd', array(array(1,2,3,4), 'zlu asd asd asd asd asd asd asd asd asd asd asd asd asd asd asd as dva', TRUE), array(array(1,2,3,4), 'zlu asd asd asd asd asd asd asd asd asd asd asd asd asd asd asd as dva', TRUE));

if (!empty($_GET['mail'])) {
	return $app->route('mailRouter')->getModel()->process();
} elseif (!empty($_GET['imap'])) {
	return $app->route('imapRouter')->getModel()->process();
} elseif (!empty($_GET['read'])) {
	return $app->route('readRouter')->getModel()->process();
} elseif (!empty($_GET['xls'])) {
	return $app->route('xlsRouter')->getModel()->process();
} else {
	return $app->route()->getModel()->process();
}

