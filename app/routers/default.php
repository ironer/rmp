<?php

require_once(CLASSES . '/Router.php');
$router = new Router($this->router, $this);

$useRouter = $router->go();

if ($useRouter !== 'default') {
    $this->route($useRouter);
}

return $router;