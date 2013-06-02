<?php

$router = new Router($this->router, $this);

$export = new HtmlTable('exportXLS');

$export->config(array(
	'type' => 'xls',
	'source' => array(
		array(1, 'žluva', '006', 1370086580),
		array(3, 'Štefan', '02.2', 1367667380),
		array(7, 'něco', '10.11', 1372937780)
	),
	'columns' => array(
		array('format' => 'int', 'func' => 'avg'),
		array('func' => 'min'),
		array('func' => 'max'),
		array('format' => 'ut', 'func' => 'avg')
	)
));

$export->go();

return $router;
