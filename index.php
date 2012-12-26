<?php

define('DEBUG', TRUE);
define('NOW', microtime(TRUE));
define('MEMORY', memory_get_usage());
define('ROOT', __DIR__);
define('APP', ROOT . "/app");
define('WEBROOT', (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST']
    . (strlen($_webdir = dirname($_SERVER['SCRIPT_NAME'])) === 1 ? '' : $_webdir));
unset($_webdir);

try {
    $app = require_once(APP . '/default.php');
    $app->go();
} catch (Exception $e) {
    list($message, $file, $line) = array($e->getMessage(), $e->getFile(), $e->getLine());
    echo "<pre style=\"margin: 3px 0\">Zachycena vyjimka: $message [<small><a href=\"editor://open/?file=" . rawurlencode($file)
        . "&line=$line" . '"><i>' . htmlspecialchars(substr($file, strlen(ROOT))) . "</i> <b>@$line</b></a></small>]<pre>";
}

if (DEBUG) {
    App::dump($app);
    echo 'Doba generovani: <b>' . App::runtime() . '</b>';
    echo ' / Max. pouzita pamet: <b>' . App::maxMem() . '</b> / Max. alokovana pamet: <b>' . App::maxMem(TRUE) . '</b>';
}
