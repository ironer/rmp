<?php

require_once(CLASSES . '/Router.php');
$router = new Router($this->router, $this);

require_once(CLASSES . '/imapread.php');


$imap = new IMAPread('imap1', $this);

$imap->go(10);


return $router;