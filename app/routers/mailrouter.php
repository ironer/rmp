<?php

require_once(CLASSES . '/Router.php');
$router = new Router($this->router, $this);

require_once(CLASSES . '/Mailer.php');


$params = array(
    'from'=>array('info@essensmail.com','Administrátor'),
    'to'=>array(array('romang@float.cz','Gregor')),
    'bcc'=>array(array('gregor@spotreby.cz','Příjemce 1'),array('r.gregor@spotreby.cz','Příjemce 2'),array('roman.gregor@spotreby.cz','Příjemce 3')),
    'text'=>'Testovací email',
    'html'=>'Text testovacího mailu s <strong>tučným textem</strong> a <em>kurzívou</em><br /><br />A nějaký kecy, aby byl mail delší, protože jinak se mailserveru zdá, že je to SPAM, což ale vůbec není pravda, tak ať mě kretén nesere, sakra.<br /><br />s pozdravem<br /><br />Odesílatel :-)<br /><br />',
);
$params['text'] = strip_tags(str_replace('<br />',"\r\n",$params['html']));



$mailer = new Mailer('mailer1', $this);

$mailer->prepare($params);

//$mailer->go('admin@essensworld.com','Admin Štefan');

//$mailer->go('test@spotreby.cz','Wugas');

return $router;