<?php

define('CLASSES', APP . '/classes');
define('CONTROLLERS', APP . '/controllers');
define('MODELS', APP . '/models');
define('ROUTERS', APP . '/routers');
define('VIEWS', APP . '/views');

define('CSS', ROOT . '/css');
define('IMG', ROOT . '/img');
define('JS', ROOT . '/js');
define('LOGS', ROOT . '/logs');
define('TEMP', ROOT . '/temp');

require_once(CLASSES . '/App.php');
$app = new App('MLM+');

return $app->route()->getModel()->control()->view();