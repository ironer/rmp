<?php

class App
{

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
        App::lg('Spusteni aplikace!');
        return;
    }


    private static $lastRuntime = NOW;
    private static $lastMemory = 0;


    public static function num($val = 0, $decs = 2, $units = '', $delta = false) {
        return ($delta && ($val = round($val, $decs)) > 0 ? '+' : '') . number_format($val, $decs, ',', ' ') . ($units ? " $units" : '');
    }


    public static function runtime($minus = NOW) {
        return App::num(((App::$lastRuntime = microtime(true)) - $minus) * 1000, 0, 'ms', $minus !== NOW);
    }


    public static function memory($minus = 0) {
        return App::num(((App::$lastMemory = memory_get_usage()) - $minus) / 1024, 0, 'kB', $minus !== 0);
    }


    public static function maxMem($real = false) {
        return App::num(memory_get_peak_usage($real) / 1024, 1, 'kB');
    }


    public static function lg($text = '') {
        if (!PRODUCTION) {
            require_once(CLASSES . '/Dumper.php');
            list($file, $line, $code) = Dumper::findLocation();

            echo '<pre style="margin: 3px 0">[' . str_pad(App::runtime(App::$lastRuntime), 8, ' ', STR_PAD_LEFT) . ' / '
                . str_pad(App::memory(App::$lastMemory), 8, ' ', STR_PAD_LEFT) . ']' . " <b>$text"
                . '</b> {<small><a href="editor://open/?file=' . rawurlencode($file) . "&line=$line"
                . '"><i>' . substr($file, strlen(ROOT)) . "</i> <b>@$line</b></a> $code</small>}</pre>";
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

}
