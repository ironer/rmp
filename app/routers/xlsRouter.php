<?php

$router = new Router($this->router, $this);

$export = new HtmlTable('exportXLS');

$export->config(array(
	'type' => 'xls',
	'source' => array(
		array(1, 'žluva', '006', 1370086580),
		array(3, '<td>zluvik</td>', '\'007', 1367667380),
		array(7, 'něco', '10.11', 1372937780)
	),
	'columns' => array(
		array('format' => 'int', 'func' => 'avg'),
		array('func' => 'min'),
		array('func' => 'min'),
		array('format' => 'ut', 'func' => 'avg')
	)
));

if ($table = $export->go()) echo $table;

return $router;
