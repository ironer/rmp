<?php

$router = new Router($this->router, $this);
$reader = new Reader('reader1', $this);

$reader->used_cache = 'mailer1';

$reader->readData();



?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Čteč</title>
<meta http-equiv="Content-Style-Type" content="text/css" />
<meta http-equiv="Content-language" content="cs" />
</head>
<body>

<?php
    echo 'Odesláno: '.$reader->sent.' z ' . $reader->prepared.'. Chybné: '.$reader->errors;
?><br /><br />




</body>
</html>
<?php return $router; ?>