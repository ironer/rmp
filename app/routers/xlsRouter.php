<?php

$router = new Router($this->router, $this);

$export = new Excel('exportXLS', $this);

$export->config(array(
	'table' => array(
		array(1, 'zluva', 'vana'),
		array(2, 'Stefan', 'Fiedler'),
		array(3, 'neco', 'nic')
	)
));

$export->go();

return $router;
