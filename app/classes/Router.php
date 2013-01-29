<?php

class Router
{

	// TODO: napsat firewall
	// TODO: napsat lazy decrypter vstupu pouzivajici blowfish nebo twofish

	public $id;
	public $container;

	public $routes = array();
	private $usedRoute = array();


	public function __construct($_id, $_container)
	{
		if (get_class($_container) === 'App') {
			$this->id = $_id;
			$this->container = $_container;
			if (DEBUG) TimeDebug::lg("Vytvoren router '$this->id'", $this);
		} else {
			throw new Exception("Konstruktor routeru ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
		}
	}


	public function go()
	{
		if (DEBUG) TimeDebug::lg("Routovani...", $this);

		return $this->id;
	}

}