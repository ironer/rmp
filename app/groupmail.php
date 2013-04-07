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

if (DEBUG) TimeDebug::init(ADVANCEDLOG, LOCAL, ROOT, NOW, 0,
	array('CLASSES', 'LIBS', 'MODELS', 'PROCESSORS', 'ROUTERS', 'SERVICES', 'TEMPLATES', 'APP'));

//TimeDebug::$idPrefix = 'test';
//App::dump(TimeDebug::$request);
//TimeDebug::$idPrefix = 'td';

$app = new App('GM');

if (!empty($_GET['mail'])) {
	return $app->route('mailrouter')->getModel()->process();
}  elseif (!empty($_GET['imap'])) {
	return $app->route('imaprouter')->getModel()->process();
}  elseif (!empty($_GET['read'])) {
	return $app->route('readrouter')->getModel()->process();
}  else {
	return $app->route()->getModel()->process();
}

