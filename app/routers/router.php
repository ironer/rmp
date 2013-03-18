<?php

require_once(CLASSES . '/Router.php');
$router = new Router($this->router, $this);

$test = new Test($router);
$test2 = new Test($test);

$router->routes = $test2;

return $router;

class Test
{
	private $ukazatel;
	public $zmena;

	public function __construct($ukazatel) {
		$this->ukazatel = $ukazatel;

	}
}
