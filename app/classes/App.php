<?php

class App {
    public $router = 'default';
    public $model = 'default';
    public $controller = 'default';
    public $view = 'default';

    private $rmcv = array();

    public function route() {
        if (file_exists(ROUTERS . "/$this->router.php")) {
            $this->rmcv['router'] = require_once(ROUTERS . "/$this->router.php");
            echo "Pouzity router: $this->router" . '<br>';
            $this->rmcv['router']->go();
        } else {
            throw new Exception("Router '$this->router' (soubor '$this->router.php') nenalezen v adresari routeru '" . ROUTERS . "'.");
        }
    }

    public function getModel() {
        echo MODELS . '<br>';
        return;
    }

    public function control() {
        echo CONTROLLERS . '<br>';
        return;
    }

    public function view() {
        echo VIEWS . '<br>';
        return;
    }

    public function go() {
        echo '<b>Start aplikace!</b>';
        return;
    }

}
