<?php

require_once(CLASSES . '/Router.php');
$router = new Router($this->router, $this);

require_once(CLASSES . '/Mailer.php');


$params = array(
    'from_name'=>'Administrátor',
    'from_email'=>'admin@server.hu',
    'to_name'=>'Gregor',
    'to_email'=>'gregor@float.cz',
    'subject'=>'Testovací email',
    'body_text'=>'Text testovacího emailu',
);




$mailer = new Mailer('mailer1', $this);

$mailer->prepare($params)->go();

$mailer->go('info@ithub.cz','IThub.cz');

return $router;