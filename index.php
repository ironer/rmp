<?php

$debug = true;

$start = $debug ? array(microtime(true), memory_get_usage()) : array(0,0);

$app = require_once(__DIR__ . '/app/default.php');
$app->go();

if ($debug) {
    echo '<hr>';
    var_dump($app);
    echo '<hr>Pouzita pamet: ' . number_format((memory_get_usage() - $start[1]) / 1024, 2, '.', ' ') . '&thinsp;kB / Doba generovani: ';
    echo number_format(microtime(true) - $start[0], 4, '.', ' ') . '&thinsp;s';
}
