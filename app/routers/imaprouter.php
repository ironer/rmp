<?php

$router = new Router($this->router, $this);
$imap = new IMAPread('imap1', $this);

$imap->go(10);


return $router;