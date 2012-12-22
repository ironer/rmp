<?php

define('PRODUCTION', FALSE);
define('NOW', microtime(TRUE));
define('MEMORY', memory_get_usage());
define('ROOT', __DIR__);
define('APP', ROOT . "/app");

$app = require_once(APP . '/default.php');
$app->go();

if (!PRODUCTION) {
    App::dump($app);
    echo 'Doba generovani: <b>' . App::runtime() . '</b>';
    echo ' / Max. pouzita pamet: <b>' . App::maxMem() . '</b> / Max. alokovana pamet: <b>' . App::maxMem(TRUE) . '</b>';
}
