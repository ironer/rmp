<?php

class Router
{
    public $id;
    public $app;
    private $used = FALSE;
    public $routes = array();
    private $usedRoute = array();


    public function __construct($_id, $_app)
    {
        if (get_class($_app) === 'App') {
            $this->id = $_id;
            $this->app = $_app;
            App::lg("  " . $this->app->id . ": Vytvoren router '$this->id'");
        } else {
            throw new Exception("Konstruktor routeru ocekava odkaz na kontajner. Argument[0] neni objekt tridy 'App'.");
        }
    }


    public function go()
    {
        if (!$this->used) {
            $this->used = TRUE;
            App::lg("    $this->id: Routovani...");
        }

        return 'dalsi';
    }

}