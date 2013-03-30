<?php

class Router {

	// TODO: napsat firewall
	// TODO: napsat lazy decrypter vstupu pouzivajici blowfish nebo twofish

	public $id;
	public $container;

	public $routes = array();
	private $usedRoute = array();


	public function __construct($id, $container) {
		if ($container instanceof App) {
			$this->id = $id;
			$this->container = $container;
			App::lg("Vytvoren router '$this->id'", $this);
		} else {
			throw new Exception("Konstruktor routeru ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
		}
	}


	public function go() {
		App::lg("Routovani...", $this);

		return $this->id;
	}

}