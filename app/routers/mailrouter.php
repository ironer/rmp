<?php

require_once(CLASSES . '/Router.php');
$router = new Router($this->router, $this);

require_once(CLASSES . '/Mailer.php');


$params = array(
    'from_name'=>'Administrátor',
    'from_email'=>'info@essensmail.com',
    'to_name'=>'Gregor',
    'to_email'=>'romang@float.cz',
    'subject'=>'Testovací email',
    'body_html'=>'Text testovacího mailu s <strong>tučným textem</strong> a <em>kurzívou</em><br /><br />A nějaký kecy, aby byl mail delší, protože jinak se mailserveru zdá, že je to SPAM, což ale vůbec není pravda, tak ať mě kretén nesere, sakra.<br /><br />s pozdravem<br /><br />Odesílatel :-)<br /><br />',
);
$params['body_text'] = strip_tags(str_replace('<br />',"\r\n",$params['body_html']));



$mailer = new Mailer('mailer1', $this);

$mailer->prepare($params)->go();

//$mailer->go('admin@essensworld.com','Admin Štefan');

//$mailer->go('kangasivan@seznam.cz','Wugas');

return $router;