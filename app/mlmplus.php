<?php

define('CACHE', APP . '/cache');
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
$app = new App('MLM+', null, false, "asd asd asadasdass\nssssssss sssdsdsds dada\nasd asd asadasdass\nssssssss sssdsdsds dada\nasd asd asadasdass", array('1' => 'test', 2 => "asd asd asadasdass\nssssssss sssdsdsds dada\nasd asd as", 'zluva' => "asd asd asadasdass\nssssssss sssdsdsds dada\nasd asd as", 7 => array(array(), array(1 => 'nevidim'), 'hovno' => 'velke')), 178);

if (!empty($_GET['mail'])) {
	return $app->route('mailrouter')->getModel()->control()->getView();
}  else {
	return $app->route()->getModel()->control()->getView();
}

