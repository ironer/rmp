<?php

class App
{
    public $id;
    private $router = 'default';
    private $model = 'default';
    private $controller = 'default';
    private $view = 'default';

    private $rmcv = array(
        'routers' => array(),
        'models' => array(),
        'services' => array(),
        'controllers' => array(),
        'views' => array()
    );


    public function __construct($_id = 'default') {
        $this->id = $_id;
        App::lg("Vytvorena aplikace '$_id'");
    }


    public function route($_usedRouter = '')
    {
        $_usedRouter = empty($_usedRouter) ? $this->router : ($this->router = $_usedRouter);
        if (is_file(ROUTERS . "/$_usedRouter.php")) {
            App::lg("  $this->id: Volani routeru '$_usedRouter'");
            App::prependArray($this->rmcv['routers'], $_usedRouter, require_once(ROUTERS . "/$this->router.php"));
            if (get_class($this->rmcv['routers'][$_usedRouter]) === 'Router') {
                if ($_usedRouter === $this->router) {
                    $this->rmcv['routers'][$_usedRouter]->go();
                }
            } else {
                throw new Exception("Aplikace ocekava odkaz na router. Skript '$_usedRouter.php' nevraci objekt tridy 'Router'.");
            }
        } else {
            throw new Exception("Router '$_usedRouter' (soubor '$_usedRouter.php') nenalezen v adresari routeru '" . ROUTERS . "'.");
        }

        return $this;
    }


    public function getModel()
    {
        App::lg("  $this->id: Model...");

        return $this;
    }


    public function control()
    {
        App::lg("  $this->id: Controller...");

        return $this;
    }


    public function view()
    {
        App::lg("  $this->id: View...");

        return $this;
    }


    public function go()
    {
        App::lg("Spusteni aplikace '$this->id'!");

        return $this->id;
    }


    private static $lastRuntime = NOW;
    private static $lastMemory = 0;


    public static function num($val = 0, $decs = 2, $units = '', $delta = FALSE) {
        return ($delta && ($val = round($val, $decs)) > 0 ? '+' : '') . number_format($val, $decs, ',', ' ') . ($units ? " $units" : '');
    }


    public static function runtime($minus = NOW) {
        return App::num(((App::$lastRuntime = microtime(TRUE)) - $minus) * 1000, 0, 'ms', $minus !== NOW);
    }


    public static function memory($minus = 0) {
        return App::num(((App::$lastMemory = memory_get_usage()) - $minus) / 1024, 0, 'kB', $minus !== 0);
    }


    public static function maxMem($real = FALSE) {
        return App::num(memory_get_peak_usage($real) / 1024, 0, 'kB');
    }


    public static function lg($text = '', $reset = FALSE) {
        if (DEBUG) {
            require_once(CLASSES . '/Dumper.php');
            list($file, $line, $code) = Dumper::findLocation(TRUE);

            if ($reset) {
                App::$lastRuntime = NOW;
                App::$lastMemory = 0;
            }

            echo '<pre style="margin: 3px 0">[' . str_pad(App::runtime(App::$lastRuntime), 8, ' ', STR_PAD_LEFT) . ' / '
                . str_pad(App::memory(App::$lastMemory), 8, ' ', STR_PAD_LEFT) . ']' . " <b>$text"
                . '</b> [<small><a href="editor://open/?file=' . rawurlencode($file) . "&line=$line"
                . '"><i>' . htmlspecialchars(substr($file, strlen(ROOT))) . "</i> <b>@$line</b></a>"
                . ($code ? " $code" : '') . '</small>]</pre>';
        }
    }


    public static function dump() {
        require_once(CLASSES . '/Dumper.php');
        $vars = func_get_args();
        echo '<hr>';
        foreach ($vars as $var) {
            Dumper::dump($var, array('location' => TRUE));
            echo '<hr>';
        }
    }


    private static function prependArray(&$array, $key, $var) {
        $array = array_reverse($array);
        $array[$key] = $var;
        $array = array_reverse($array);
    }

}
