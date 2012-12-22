<?php

class Router
{
    private $app;
    public $routes = array();
    private $usedRoute = array();


    public function __construct($app)
    {
        if (get_class($app) == 'App') {
            $this->app = $app;
        } else {
            throw new Exception("Konstruktor ocekava odkaz na kontajner. Argument '$app' neni objekt tridy 'App'.");
        }
    }


    public function go()
    {
        App::lg('Routuju!');
    }

}