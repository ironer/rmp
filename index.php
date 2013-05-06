<?php

define('DEBUG', TRUE);
define('ADVANCEDLOG', TRUE);
define('LOCAL', $_SERVER['SERVER_NAME'] == 'localhost');
define('NOW', microtime(TRUE));
define('MEMORY', memory_get_usage());
define('ROOT', __DIR__);
define('APP', ROOT . "/app");
define('WEBPATH', (strlen($_webdir = dirname($_SERVER['SCRIPT_NAME'])) === 1 ? '' : $_webdir)); unset($_webdir);
define('WEBROOT', (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . WEBPATH);

try {
	$app = require_once(APP . '/groupmail.php');
	$app->go();
} catch (Exception $e) {
	list($message, $file, $line) = array(htmlspecialchars($e->getMessage()), $e->getFile(), $e->getLine());
	echo "<pre style=\"margin: 3px 0\">Zachycena vyjimka: $message [<small><a href=\"editor://open/?file=" . rawurlencode($file)
			. "&line=$line" . '"><i>' . htmlspecialchars(substr($file, strlen(ROOT))) . "</i> <b>@$line</b></a></small>]</pre>";
}

$a = array(array('test'));
App::dump($a);
$b = 'test test test test test test test test test test test test test test test test test test test test test test test test test test test test test test test test test';
App::dump($a, $b);


if (DEBUG) {
	App::lg('Zobrazeni debuggeru', $app);
	$response = TimeDebug::getResponse();
	App::dump($response);

	echo '<hr>Generovani odpovedi: <b>' . TimeDebug::runtime() . '</b>'
			. ' / Max. pamet: <b>' . TimeDebug::maxMem() . '</b> / Max. alokovana: <b>' . TimeDebug::maxMem(TRUE) . '</b>'
			. ' / <a href="' . WEBROOT . '">homepage</a> / <a href="' . WEBROOT . '?mail=1">odeslat email</a>'
			. ' / <a href="' . WEBROOT . '?imap=1">zpracovat imap</a>'
			. ' / <a href="' . WEBROOT . '?xls=1">xls</a>'
			. ' / <a href="' . WEBROOT	. '?read=1">nacist odeslani</a>' . "\n";

	if (!ADVANCEDLOG) App::dump($app);

	echo "</div>\n</div>\n</div>\n</body>\n</html>";
} else {
	echo '<hr>Generovani odpovedi: <b>' . number_format((microtime(TRUE) - NOW) * 1000, 0, ',', ' ') . ' ms</b>'
			. ' / Max. pamet: <b>' . number_format(memory_get_peak_usage() / 1024, 0, ',', ' ') . ' kB</b>'
			. ' / Max. alokovana: <b>' . number_format(memory_get_peak_usage(TRUE) / 1024, 0, ',', ' ') . ' kB</b>'
			. ' / <a href="' . WEBROOT . '">homepage</a> / <a href="' . WEBROOT . '?mail=1">odeslat email</a>'
			. ' / <a href="' . WEBROOT . '?imap=1">zpracovat imap</a>'
			. ' / <a href="' . WEBROOT . '?xls=1">xls</a>'
			. ' / <a href="' . WEBROOT	. '?read=1">nacist odeslani</a>' . "\n";
}