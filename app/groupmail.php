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

if (DEBUG) {
	require_once(LIBS . '/TimeDebug/TimeDebug.php');
	TimeDebug::init(ADVANCEDLOG, LOCAL, ROOT, NOW, 0);
}

require_once(CLASSES . '/App.php');
$app = new App('GM', null, false, "asd asd asadasdass\nssssssss sssdsdsds dada\nasd asd asadasdass\nssssssss sssdsdsds dada\nasd asd asadasdass", array('1' => 'test', 2 => "asd asd asadasdass\nssssssss sssdsdsds dada\nasd asd as", 'zluva' => "asd asd asadasdass\nssssssss sssdsdsds dada\nasd asd as", 7 => array(array(), array(1 => 'nevidim'), 'test' => 'zluvy')), 178);

App::dump(TimeDebug::$request);

if (!empty($_GET['mail'])) {
	return $app->route('mailrouter')->getModel()->process();
}  elseif (!empty($_GET['imap'])) {
	return $app->route('imaprouter')->getModel()->process();
}  elseif (!empty($_GET['read'])) {
	return $app->route('readrouter')->getModel()->process();
}  else {
	$app->route();
//	App::dump($app);
	return $app->getModel()->process();
}

