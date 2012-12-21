<?php

class App
{
    public static function num($val = 0, $decs = 2, $units = '') { return number_format($val, $decs, ',', ' ') . ($units ? " $units" : ""); }
    public static function runtime() { return App::num((microtime(true) - NOW) * 1000, 1, 'ms'); }
    public static function memory() { return App::num(memory_get_usage() / 1024, 1, ' kB'); }
    public static function maxMem($real = false) { return App::num(memory_get_peak_usage($real) / 1024, 1, 'kB'); }
    public static function lg($text = '') {
        if (!PRODUCTION) {
            require_once(CLASSES . '/Dumper.php');
            list($file, $line, $code) = Dumper::findLocation();

            echo '[' . App::runtime() . ' / ' . App::memory() . '] ' . " <b>$text"
                . '</b>&nbsp;&nbsp;&nbsp;{ <small><a href="editor://open/?file=' . rawurlencode($file) . "&line=$line"
                . '">' . substr($file, strlen(ROOT)) . ":$line</a> $code</small> }<br>";
        }
    }
    public static function dump() {
        require_once(CLASSES . '/Dumper.php');
        $vars = func_get_args();
        echo '<hr>';
        foreach ($vars as $var) {
            Dumper::dump($var, array('location' => true));
            echo '<hr>';
        }
    }

    public $router = 'default';
    public $model = 'default';
    public $controller = 'default';
    public $view = 'default';

    private $rmcv = array(
        routers => array(),
        models => array(),
        controllers => array(),
        views => array()
    );

    public function route()
    {
        if (is_file(ROUTERS . "/$this->router.php")) {
            $this->rmcv['routers'][$this->router] = require_once(ROUTERS . "/$this->router.php");
            App::lg("Spusten router: '$this->router'");
            $this->rmcv['routers'][$this->router]->go();
        } else {
            throw new Exception("Router '$this->router' (soubor '$this->router.php') nenalezen v adresari routeru '" . ROUTERS . "'.");
        }
    }


    public function getModel()
    {
        App::lg('Model...');
        return;
    }


    public function control()
    {
        App::lg('Controller...');
        return;
    }


    public function view()
    {
        App::lg('View...');
        return;
    }


    public function go()
    {
        App::lg('<b>Spusteni aplikace!</b>');
        return;
    }

}
