<?php

require_once(CLASSES . '/Router.php');
$router = new Router($this->router, $this);

require_once(CLASSES . '/Mailer.php');


$mailer = new Mailer('mailer1', $this);

$params = array(
    'from'=>array('info@essensmail.com','Administrátor'),
    'to'=>array('romang@float.cz','Gregor'),
    'bcc'=>array(array('gregor@spotreby.cz','Příjemce 1'),array('r.gregor@spotreby.cyp','Příjemce 2'),array('roman.gregor@spotreby.cz','Příjemce 3')),
    'subject'=>'Testovací email',
    'html'=>'Text testovacího mailu s <strong>tučným textem</strong> a <em>kurzívou</em><br /><br />A nějaký kecy, aby byl mail delší, protože jinak se mailserveru zdá, že je to SPAM, což ale vůbec není pravda, tak ať mě kretén nesere, sakra.<br /><br />s pozdravem<br /><br />Odesílatel :-)<br /><br />',
    'attachments'=>array('empty'=>'img/no.gif','cenik'=>'cenik_essens_cz.pdf'),
);
$params['text'] = strip_tags(str_replace('<br />',"\r\n",$params['html']));

$mailer->prepare($params);

$mailer->prepare(array(
    'to'=>array('rgregor@float.cz','Gregor'),
    'bcc'=>null,
    'subject'=>'Druhý testovací email',
    'attachments'=>array('empty'=>'img/no.png'),    
));

$mailer->go();

return $router;