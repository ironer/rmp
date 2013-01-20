<?php
/**
 * Copyright (c) 2013 Stefan Fiedler (http://ironer.cz)
 * App class including logging and debugging methods
 * @author: Stefan Fiedler 2013
 */

class App
{

	public $id;
	public $stop = FALSE;

	private $request;
	private $services = array();
	private $calls = array();
	private $data = array();
	private $response;

	private $router = 'router';
	private $model = 'model';
	private $dpu = 'dpu';

	private $rmd = array(
		'routers' => array(),
		'models' => array(),
		'dpus' => array()
	);


	public function __construct($_id = 'myapp') {
		$this->id = $_id;
		$this->request = urldecode(substr($_SERVER['REQUEST_URI'], strlen(WEBPATH) + 1));
		App::$currentApp = $this;
		App::lg("Vytvorena aplikace '$_id'", $this);
	}


	public function route($_router = '')
	{
		do {
			if ($this->stop) return $this;

			$this->router = empty($_router) ? $this->router : $_router;

			if (!is_file(ROUTERS . "/$this->router.php")) {
				throw new Exception("Router '$this->router' (soubor '$this->router.php') nenalezen v adresari routeru '" . ROUTERS . "'.");
			}

			App::lg("Volani routeru '$this->router'", $this);
			$this->rmd['routers'][$this->router] = require_once(ROUTERS . "/$this->router.php");

			if (get_class($this->rmd['routers'][$this->router]) !== 'Router') {
				throw new Exception("Aplikace '$this->id' ocekava odkaz na router. '$this->router.php' nevraci objekt tridy 'Router'.");
			}
		} while (($_router = $this->rmd['routers'][$this->router]->go()) !== $this->router);

		return $this;
	}


	public function getModel()
	{
		if ($this->stop) return $this;

		App::lg("Model...", $this);

		return $this;
	}


	public function control()
	{
		if ($this->stop) return $this;

		// TODO: napsat jednoduchy iterator pro require_once vraceneho dpu pripadne volani calls s 1 parametrem (asoc. polem)
		// TODO: vsechny metody controleru se musi volat s jednim argumentem - asociativnim polem

		App::lg("Running data processing units...", $this);

		return $this;
	}


	public function go()
	{
		if ($this->stop) return FALSE;

		App::lg("Spusteni aplikace '$this->id'!", $this);

		return $this->id;
	}


	private static $currentApp = NULL;
	private static $lastRuntime = NOW;
	private static $lastMemory = 0;
	private static $setDumper = TRUE;

	public static $timeDebug = array();
	public static $timeDebugData = array();
	private static $timeDebugMD5 = array();

	public static function num($val = 0, $decs = 2, $units = '', $delta = FALSE) {
		return ($delta && ($val = round($val, $decs)) > 0 ? '+' : '') . number_format($val, $decs, ',', ' ') . ($units ? " $units" : '');
	}


	public static function runtime($minus = NOW) {
		return App::num(((App::$lastRuntime = microtime(TRUE)) - $minus) * 1000, 0, 'ms', $minus !== NOW);
	}


	public static function memory($minus = 0) {
		return App::num(((App::$lastMemory = memory_get_usage()) - $minus) / 1024, 0, 'kB', $minus !== 0);
	}


	public static function maxMem($real = FALSE) {
		return App::num(memory_get_peak_usage($real) / 1024, 0, 'kB');
	}


	public static function lg($text = '', $object = NULL, $reset = FALSE) {
		if (DEBUG) {
			if (App::$setDumper) App::setDumper();
			list($file, $line, $code) = Dumper::findLocation(TRUE);

			if ($reset) {
				App::$lastRuntime = NOW;
				App::$lastMemory = 0;
			}

			if ($object && isset($object->id)) {
				$objects = array($object);
				$path = array($object->id);

				while (isset($object->container->id)) {
					array_unshift($objects, $object = $object->container);
					$path[] = $object->id;
				}

				$text = '<span class="nette-dump-path">' . htmlspecialchars(implode('/', array_reverse($path))) . '</span> ' . htmlspecialchars($text);
			} else {
				$text = htmlspecialchars($text);
			}

			$tdParam = '';

			if (TIMEDEBUG && isset($objects)) {
				$dumpVars = array();
				foreach($objects as $curObj) {
					$dumpVars[] = Dumper::dump($curObj, array('html' => TRUE));
				}
				$dump = implode('<hr>', $dumpVars);
				$dumpMD5 = md5($dump);
				if (isset(App::$timeDebugMD5[$dumpMD5])) {
					App::$timeDebug[] = App::$timeDebugMD5[$dumpMD5];
				} else {
					App::$timeDebug[] = App::$timeDebugMD5[$dumpMD5] = count(App::$timeDebugData);
					App::$timeDebugData[] = $dump;
				}
				$tdParam = "id=\"logId_" . count(App::$timeDebug) . "\"";
			}

			echo "<pre $tdParam class=\"nette-dump-row\">[" . str_pad(App::runtime(App::$lastRuntime), 8, ' ', STR_PAD_LEFT) . ' / '
					. str_pad(App::memory(App::$lastMemory), 8, ' ', STR_PAD_LEFT) . ']' . " $text [<small>";

			if (LOCAL) {
				echo '<a href="editor://open/?file=' . rawurlencode($file) . "&line=$line"
						. "\" class=\"nette-dump-editor\"><i>" . htmlspecialchars(substr($file, strlen(ROOT))) . "</i> <b>@$line</b></a>";
			} else {
				echo "<span class=\"nette-dump-editor\"><i>" . htmlspecialchars(substr($file, strlen(ROOT))) . "</i> <b>@$line</b></span>";
			}

			echo ($code ? " $code" : '') . '</small>]</pre>';
		}
	}


	public static function dump() {
		if (App::$setDumper) App::setDumper();
		$vars = func_get_args();
		echo '<hr>';
		foreach ($vars as $var) {
			echo Dumper::dump($var, array('location' => TRUE, 'loclink' => LOCAL, 'html' => TRUE));
			echo '<hr>';
		}
	}

	private static function setDumper() {
		if (!preg_match('#^Content-Type: text/html#im', implode("\n", headers_list()))) {
			header('Content-type: text/html; charset=utf-8');
			header("Cache-control: private");
			echo "<!DOCTYPE html>\n<html style=\"height: 100%\">\n<head>\n<meta charset=\"utf-8\">\n<title>Debuging session</title>\n";
			echo "<link rel=\"stylesheet\" href=\"" . WEBROOT . CSS . "/nette-dump.css\">\n";
			echo "<script src=\"" . WEBROOT . JS . "/vendor/jak_compressed.js\"></script>\n";
			echo "</head>\n<body>\n<div id=\"logContainer\">\n<div id=\"logView\">\n";
		} else {
			echo "<link rel=\"stylesheet\" href=\"" . WEBROOT . CSS . "/nette-dump.css\">\n";
		}
		require_once(CLASSES . '/Dumper.php');
		App::$setDumper = FALSE;
	}
}
