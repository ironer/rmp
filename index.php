<?php

define('DEBUG', TRUE);
define('TIMEDEBUG', TRUE);
define('LOCAL', $_SERVER['SERVER_NAME'] == 'localhost');
define('NOW', microtime(TRUE));
define('MEMORY', memory_get_usage());
define('ROOT', __DIR__);
define('APP', ROOT . "/app");
define('WEBPATH', (strlen($_webdir = dirname($_SERVER['SCRIPT_NAME'])) === 1 ? '' : $_webdir)); unset($_webdir);
define('WEBROOT', (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . WEBPATH);

try {
	$app = require_once(APP . '/mlmplus.php');
	$app->go();
} catch (Exception $e) {
	list($message, $file, $line) = array(htmlspecialchars($e->getMessage()), $e->getFile(), $e->getLine());
	echo "<pre style=\"margin: 3px 0\">Zachycena vyjimka: $message [<small><a href=\"editor://open/?file=" . rawurlencode($file)
			. "&line=$line" . '"><i>' . htmlspecialchars(substr($file, strlen(ROOT))) . "</i> <b>@$line</b></a></small>]</pre>";
}

if (DEBUG) {
	App::lg('Zobrazeni debuggeru', $app);
	echo '<hr>Generovani odpovedi: <b>' . App::runtime() . '</b>'
			. ' / Max. pouzita pamet: <b>' . App::maxMem() . '</b> / Max. alokovana pamet: <b>' . App::maxMem(TRUE) . '</b>';
	if (TIMEDEBUG) {
		echo '/ Zmena logu: <b>&larr;</b> a <b>&rarr;</b> / <a href="' . WEBROOT . '">homepage</a> / <a href="'
				. WEBROOT . "?mail=1\">odeslat email</a>\n";
		echo "<script>var _tdLogs = " . json_encode(App::$timeDebug) . ";</script>\n";
		echo "<script src=\"" . WEBROOT . JS . "/timedebug.js\"></script>\n";
	} else {
		App::dump($app);
	}
}


