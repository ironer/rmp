<?php

class App
{
    public static function lg($text = '') { if (!PRODUCTION) echo '[' . App::runtime() . ' / ' . App::memory() . "] $text<br>"; }
    public static function num($val = 0, $decs = 2, $units = '') { return number_format($val, $decs, ',', ' ') . ($units ? " $units" : ""); }

    public static function maxMem($real = false) { return App::num(memory_get_peak_usage($real) / 1024, 1, 'kB'); }
    public static function memory() { return App::num(memory_get_usage() / 1024, 1, ' kB'); }
    public static function runtime() { return App::num((microtime(true) - NOW) * 1000, 1, 'ms'); }
    public static function dump($var) {
        require_once(CLASSES . '/Dumper.php'); echo '<hr>';
        Dumper::dump($var, array('location' => true));
        echo '<hr>';
    }

    public $router = 'default';
    public $model = 'default';
    public $controller = 'default';
    public $view = 'default';

    private $rmcv = array();


    public function route()
    {
        if (file_exists(ROUTERS . "/$this->router.php")) {
            $this->rmcv['router'] = require_once(ROUTERS . "/$this->router.php");
            App::lg("Pouzity router: '$this->router'");
            $this->rmcv['router']->go();
        } else {
            throw new Exception("Router '$this->router' (soubor '$this->router.php') nenalezen v adresari routeru '" . ROUTERS . "'.");
        }
    }


    public function getModel()
    {
        App::lg(MODELS);
        return;
    }


    public function control()
    {
        App::lg(CONTROLLERS);
        return;
    }


    public function view()
    {
        App::lg(VIEWS);
        return;
    }


    public function go()
    {
        App::lg('<b>Spusteni aplikace!</b>');
        return;
    }

}
