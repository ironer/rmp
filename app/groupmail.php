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

require_once(CLASSES . '/App.php');

if (DEBUG) {
	require_once(LIBS . '/TimeDebug/TimeDebug.php');
	TimeDebug::init(ADVANCEDLOG, LOCAL, ROOT, NOW, 0);
	App::dump(TimeDebug::$message);
}

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

