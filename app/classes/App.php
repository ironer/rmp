<?php

class App {
    static public function lg($text = '') { if (!PRODUCTION) echo '[' . App::runtime() . ' / ' . App::memory() . "] $text<br>"; }
    static public function num($val = 0, $decs = 2, $units = '') { return number_format($val, $decs, ',', ' ') . ($units ? " $units" : ""); }

    static public function maxMem($real = false) { return App::num(memory_get_peak_usage($real) / 1024, 1, 'kB'); }
    static public function memory() { return App::num(memory_get_usage() / 1024, 1, ' kB'); }
    static public function runtime() { return App::num((microtime(true) - NOW) * 1000, 1, 'ms'); }

    public $router = 'default';
    public $model = 'default';
    public $controller = 'default';
    public $view = 'default';

    private $rmcv = array();

    public function route() {
        if (file_exists(ROUTERS . "/$this->router.php")) {
            $this->rmcv['router'] = require_once(ROUTERS . "/$this->router.php");
            App::lg("Pouzity router: '$this->router'");
            $this->rmcv['router']->go();
        } else {
            throw new Exception("Router '$this->router' (soubor '$this->router.php') nenalezen v adresari routeru '" . ROUTERS . "'.");
        }
    }

    public function getModel() {
        App::lg(MODELS);
        return;
    }

    public function control() {
        App::lg(CONTROLLERS);
        return;
    }

    public function view() {
        App::lg(VIEWS);
        return;
    }

    public function go() {
        App::lg('<b>Spusteni aplikace!</b>');
        return;
    }

}
