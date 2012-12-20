<?php

class Router {
    private $app;

    public function __construct($app) {
        $this->app = $app;
    }

    public function go() {
        App::lg('Routuju!');
        $this->app->controller = 'trysko';
        $this->app->view = 'zluva';
    }
}