<?php

class Router
{

    public $id;
    public $container;

    public $routes = array();
    private $usedRoute = array();


    public function __construct($_id, $_container)
    {
        if (get_class($_container) === 'App') {
            $this->id = $_id;
            $this->container = $_container;
            App::lg("Vytvoren router '$this->id'", $this);
        } else {
            throw new Exception("Konstruktor routeru ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
        }
    }


    public function go()
    {
        App::lg("Routovani...", $this);

//        App::dump($_SERVER);

        return $this->id;
    }

}