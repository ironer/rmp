<?php

define('CLASSES', APP . '/classes');
define('CONTROLLERS', APP . '/controllers');
define('MODELS', APP . '/models');
define('ROUTERS', APP . '/routers');
define('SERVICES', APP . '/services');
define('VIEWS', APP . '/views');

define('CSS', '/css');
define('IMG', '/img');
define('JS', '/js');
define('LOGS', '/logs');
define('TEMP', '/temp');

require_once(CLASSES . '/App.php');
$app = new App('MLM+', null, false, "adasdass\nsssssssssssdsdsdsdadas", array(1 => 'test', 2 => 'zluvy'), 178);

if (!empty($_GET['mail'])) {
	return $app->route('mailrouter')->getModel()->control()->getView();
}  else {
	return $app->route()->getModel()->control()->getView();
}

