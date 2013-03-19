<?php

if (!isset($_GET['roman']) || $_GET['roman']!=1) die('Fatal Big Error! OK! Done! Bсе работает!');

$router = new Router($this->router, $this);

    $db=mysql_connect("essens1505.dbaserver.net", "essens1505","XTtanjBd");
    mysql_select_db('essens1505');
//    $db=mysql_connect('localhost','essensworld','essensworld');
//    mysql_select_db('essensworld');

$mailer = new Mailer('mailer2', $this);

//$mailer->reset();

if ($mailer->prepared > 0) {
    $params = array();
} else {
    $params = array(
        'from'=>array('info@essensmail.com','Info ESSENS'),
        'subject'=>'Vaše přístupové údaje pro převod telefonního čísla',
    //    'attachments'=>array('empty'=>'img/no.gif','cenik'=>'cenik_essens_cz.pdf'),
        'headers'=>array('Priority: urgent','X-Priority: 1 (Highest)','Reply-To: vodafone@essens.cz'),
    );
}

$res = mysql_query("SELECT CON_NR, CON_FNAME, CON_LNAME, CON_EMAIL, TNR_NUMBER, TNR_PORT_OUT_PIN FROM TELCO_NR T LEFT JOIN CONTRACTOR C ON CON_NR=TNR_CON_NR WHERE TNR_PORT_OUT_PIN>0 ORDER BY TNR_CON_NR LIMIT 25");

$contractors=array();

while ($rcpt = @mysql_fetch_assoc($res)) {

    $params['to'] = array($rcpt['CON_EMAIL'],$rcpt['CON_FNAME'].' '.$rcpt['CON_LNAME']);
//    $params['to'] = array('ironer80@gmail.com','Štefan Fiedler');

    $params['html'] = "&nbsp;&nbsp;&nbsp;Dobrý den,<br />pro převod Vašeho telefonního čísla: <strong>$rcpt[TNR_NUMBER]</strong><br />Vám zasíláme unikátní kód: <strong>$rcpt[TNR_PORT_OUT_PIN]</strong><br /><br />Upozorňujeme, že uvedený unikátní kód je platný pouze<br />jedenkrát, a to jen k uvedenému telefonnímu číslu.<br /><br />Po zadání tohoto kódu na webových stránkách:<br /><br /><a href=\"http://www.vodafone.cz/essens\">http://www.vodafone.cz/essens</a><br /><br />budete vyzváni k výběru Vámi zvoleného tarifu<br />a následně k vyplnění osobních údajů.<br /><br />Potvrzením budou údaje odeslány společnosti Vodafone,<br />která do několika dní odešle na Vámi zadanou adresu<br />písemnou smlouvu se sdělením, jak dále postupovat.<br /><br />Po vyřízení všech náležitostí, obdržíte od spol. Vodafone<br />novou kartu SIM s Vaším telefonním číslem. O termínu<br />převodu Vás také bude společnost Vodafone informovat.<br /><br />Váš tým ESSENS Czech<br /><br />";
    
    $params['text'] = strip_tags(str_replace('<br />',"\r\n",str_replace('&nbsp;',' ',$params['html'])));


//    echo $rcpt['CON_EMAIL'].' '.$rcpt['CON_FNAME'].' '.$rcpt['CON_LNAME'].'<br /><br />'.$params['html'].'<br /><br />'.nl2br($params['text']).'<br /><hr /><br />';

    $mailer->addEmail($params);
    $numbers[] = $rcpt['TNR_NUMBER'];
    unset($params['from']); unset($params['subject']); unset($params['headers']); 

}

$mailer->saveData();

$mailer->go(25);

$numbers = implode(',',$numbers);

mysql_query("UPDATE TELCO_NR SET TNR_PORT_OUT_PIN=-TNR_PORT_OUT_PIN WHERE TNR_NUMBER IN ($numbers)");
echo mysql_error();

?><html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Rozesílač</title>
<meta http-equiv="Content-Style-Type" content="text/css" />
<meta http-equiv="Content-language" content="cs" />
<meta http-equiv="refresh" content="10" />
</head>
<body onload="countdown();">
<?php
    echo 'Odesláno: '.$mailer->sent.' z ' . $mailer->prepared.'. Chybné: '.$mailer->errors;
?><br /><br />
Za <span id="timer">10</span> sekund budeme pokračovat. Pokud ne, dejte prosím F5.

<script type="text/javascript">
    
    function decrement() {
        
        document.getElementById('timer').innerHTML = parseInt(document.getElementById('timer').innerHTML)-1;
        
    }
    
    function countdown() {
        
        setInterval("decrement();",1000);
        
    }

</script>
</body>
</html>
<?php return $router; ?>
