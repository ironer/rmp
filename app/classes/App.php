<?php

class App
{

	public $id;
	public $request;
	public $stop = FALSE;

	private $data = array();
	private $router = 'router';
	private $model = 'model';
	private $controller = 'controller';
	private $view = NULL;
	private $rmcv = array(
		'routers' => array(),
		'models' => array(),
		'controllers' => array(),
		'views' => array()
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
			$this->rmcv['routers'][$this->router] = require(ROUTERS . "/$this->router.php");

			if (get_class($this->rmcv['routers'][$this->router]) !== 'Router') {
				throw new Exception("Aplikace '$this->id' ocekava odkaz na router. '$this->router.php' nevraci objekt tridy 'Router'.");
			}
		} while (($_router = $this->rmcv['routers'][$this->router]->go()) !== $this->router);

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

		App::lg("Controller...", $this);

		return $this;
	}


	public function getView()
	{
		if ($this->stop || empty($this->view)) return $this;

		App::lg("View...", $this);

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
	public static $timeDebug = array();


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
			require_once(CLASSES . '/Dumper.php');
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

			if (TIMEDEBUG) {
				$dumpVars = array();
				foreach($objects as $curObj) {
					$dumpVars[] = Dumper::dump($curObj, array('html' => TRUE));
				}
				App::$timeDebug[] = implode('<hr>', $dumpVars);
				$cnt = count(App::$timeDebug);
				$tdParam = "id=\"tdId_" . $cnt . "\"";
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
		require_once(CLASSES . '/Dumper.php');
		$vars = func_get_args();
		echo '<hr>';
		foreach ($vars as $var) {
			echo Dumper::dump($var, array('location' => TRUE, 'loclink' => LOCAL));
			echo '<hr>';
		}
	}

}
