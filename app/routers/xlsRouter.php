<?php

$router = new Router($this->router, $this);

$export = new Table('testTable');

$export->config(array(
	'type' => 'xml',
//	'stream' => TRUE,
	'date' => 'y-n-j G:i',
	'source' => array(
		array(123123.132, 123123.132, 'ΚΑΛΛΙΟΠΗ ΧΟΝΔΡΟΣΠ', '006', 1370086580),
		array(0.371, 0.371, '<td>zluvik</td>', '+420777367753', 1367667380),
		array(0.7323, 0.7323, 'něco', '10.11', 1372937780)
	),
	'columns' => array(
		array('format' => 'percent', 'func' => 'avg', 'align' => 'right'),
		array('format' => 'float', 'func' => 'avg', 'align' => 'right'),
		array('func' => 'min', 'width' => 0),
		array('func' => 'min', 'align' => 'center'),
		array('format' => 'ut', 'func' => 'avg', 'align' => 'right')
	)
));

if ($table = $export->go()) echo $table;

return $router;
