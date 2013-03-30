<?php
/**
 * Copyright (c) 2013 Stefan Fiedler (http://ironer.cz)
 * @author: Stefan Fiedler 2013
 */

// TODO: pri kliknuti na ocicko u hesla zmenit input na text

class App {

	public $id;
	public $stop = FALSE;

	private $request;
	private $get = array();
	private $post = array();

	private $router = 'router';
	private $model = 'model';
	private $processor = 'processor';

	private $rmp = array(
		'routers' => array(),
		'models' => array(),
		'processors' => array()
	);

	private $services = array();
	private $data = array();
	private $goto = array();
	private $response = array();


	public function __construct($id = 'myapp') {
		$this->id = $id;
		$this->request = urldecode(substr($_SERVER['REQUEST_URI'], $i = strlen(WEBPATH) + 1,
			(($j = strpos($_SERVER['REQUEST_URI'], '?')) === FALSE ? strlen($_SERVER['REQUEST_URI']) : $j - $i)));
		// TODO: osetrit a vyprazdnit $_GET a $_POST
		$this->get = $_GET;
		$this->post = $_POST;
		App::$currentApp = $this;
		App::lg("Vytvorena aplikace '$id'", $this);
	}


	public function route($router = '') {
		do {
			if ($this->stop) return $this;

			$this->router = empty($router) ? $this->router : $router;

			if (!is_file(ROUTERS . "/$this->router.php")) {
				throw new Exception("Router '$this->router' (soubor '$this->router.php') nenalezen v adresari routeru '" . ROUTERS . "'.");
			}

			App::lg("Volani routeru '$this->router'", $this);
			$this->rmp['routers'][$this->router] = require_once(ROUTERS . "/$this->router.php");

			if (get_class($this->rmp['routers'][$this->router]) !== 'Router') {
				throw new Exception("Aplikace '$this->id' ocekava odkaz na router. '$this->router.php' nevraci objekt tridy 'Router'.");
			}
		} while (($router = $this->rmp['routers'][$this->router]->go()) !== $this->router);

		return $this;
	}


	public function getModel() {
		if ($this->stop) return $this;

		App::lg("Model...", $this);

		return $this;
	}


	public function process() {
		if ($this->stop) return $this;

		// TODO: napsat jednoduchy iterator pro require_once vraceneho procesoru pripadne volani goto pole poli lambda funkci s 1 parametrem (asoc. polem)
		// TODO: vsechny metody procesoru se musi volat s jednim argumentem - asociativnim polem

		App::lg("Running processors...", $this);

		return $this;
	}


	public function go() {
		if ($this->stop) return FALSE;

		App::lg("Spusteni aplikace '$this->id'!", $this);

		return $this->id;
	}


	private static $currentApp = NULL;


	public static function lg($text = '', $object = NULL, $reset = FALSE) {
		if (DEBUG) TimeDebug::lg($text, $object, $reset);
	}


	public static function dump(&$arg0 = NULL, &$arg1 = NULL, &$arg2 = NULL, &$arg3 = NULL, &$arg4 = NULL, &$arg5 = NULL, &$arg6 = NULL, &$arg7 = NULL, &$arg8 = NULL, &$arg9 = NULL) {
		if (func_num_args() > 10) throw new Exception("Staticka metoda 'dump' muze prijmout nejvyse 10 argumentu.");
		if (DEBUG) TimeDebug::dump();
	}

}
