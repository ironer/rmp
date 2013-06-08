<?php

$router = new Router($this->router, $this);

$export = new Table('testTable');

$export->config(array(
	'type' => 'xml',
	'date' => 'y-n-j G:i',
	'source' => array(
		array(1, 'ΚΑΛΛΙΟΠΗ ΧΟΝΔΡΟΣΠ', '006', 1370086580),
		array(3, '<td>zluvik</td>', '+420777367753', 1367667380),
		array(7, 'něco', '10.11', 1372937780)
	),
	'columns' => array(
		array('format' => 'int', 'func' => 'avg', 'align' => 'right'),
		array('func' => 'min'),
		array('func' => 'min', 'align' => 'center'),
		array('format' => 'ut', 'func' => 'avg', 'align' => 'right')
	)
));

if ($table = $export->go()) echo $table;

return $router;
