<?php

define('PRODUCTION', false);
define('NOW', microtime(true));
define('MEMORY', memory_get_usage());

$app = require_once(__DIR__ . '/app/default.php');
$app->go();

if (!PRODUCTION) {
    echo '<hr>';
    var_dump($app);
    echo '<hr>Doba generovani: <b>' . App::runtime() . '</b>';
    echo ' / Max. pouzita pamet: <b>' . App::maxMem() . '</b> / Max. alokovana pamet: <b>' . App::maxMem(true) . '</b>';
}
