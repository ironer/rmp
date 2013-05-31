<?php

$router = new Router($this->router, $this);

$export = new Excel('exportXLS', $this);

$export->config(array(
	'encoding' => 'bin',
	'table' => array(
		array(1, 'žluva', 'černá vrána'),
		array(3, 'Štefan', 'Fiedler'),
		array(7, 'něco', 'nic')
	),
	'columns' => array(
		array('format' => 'int', 'func' => 'avg'),
		array('func' => 'min'),
		array('func' => 'max')
	)
));

$export->go();

return $router;
