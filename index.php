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

$a = array(array('12'));
TimeDebug::dump($a);
TimeDebug::dump($a[0][0]);

if (DEBUG) {
	TimeDebug::lg('Zobrazeni debuggeru', $app);
	echo '<hr>Generovani odpovedi: <b>' . TimeDebug::runtime() . '</b>'
			. ' / Max. pamet: <b>' . TimeDebug::maxMem() . '</b> / Max. alokovana: <b>' . TimeDebug::maxMem(TRUE) . '</b>';
	echo ' / <a href="' . WEBROOT . '">homepage</a> / <a href="' . WEBROOT . "?mail=1\">odeslat email</a> / <a href=\"" . WEBROOT . "?imap=1\">zpracovat imap</a>\n";

	if (!ADVANCEDLOG) TimeDebug::dump($app);

	echo "</div>\n</div>\n</div>\n</body>\n</html>";
}