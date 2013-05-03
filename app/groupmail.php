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

function __autoload($className) {
	if (file_exists(CLASSES . "/$className.php")) require_once(CLASSES . "/$className.php");
	elseif (file_exists(LIBS . '/' . $className . "/$className.php")) require_once(LIBS . '/' . $className . "/$className.php");
}

if (DEBUG) {
	TimeDebug::init(CACHE,
		array(
			'advancedlog' => ADVANCEDLOG,
			'local' => LOCAL,
			'root' => ROOT,
			'starttime' => NOW,
			'pathconstants' => array('CLASSES', 'LIBS', 'MODELS', 'PROCESSORS', 'ROUTERS', 'SERVICES', 'TEMPLATES', 'APP'),
			'urllength' => 4000,
		)
	);
}

//App::dump(TimeDebug::$request);

$app = new App('GMasdasdasdasdasd', array(array(1,2,3,4),'zlu asd asd asd asd asd asd asd asd asd asd asd asd asd asd asd as dva',TRUE), array(array(1,2,3,4),'zlu asd asd asd asd asd asd asd asd asd asd asd asd asd asd asd as dva',TRUE));

if (!empty($_GET['mail'])) {
	return $app->route('mailrouter')->getModel()->process();
} elseif (!empty($_GET['imap'])) {
	return $app->route('imaprouter')->getModel()->process();
} elseif (!empty($_GET['read'])) {
	return $app->route('readrouter')->getModel()->process();
} elseif (!empty($_GET['xls'])) {
	return $app->route('xlsrouter')->getModel()->process();
} else {
	return $app->route()->getModel()->process();
}

