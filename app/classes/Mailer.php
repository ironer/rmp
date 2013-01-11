<?php

class Mailer
{

	const FROM_NAME   = 'from_name',      //jméno odesílatele
			FROM_EMAIL  = 'from_email',     //email odesílatele
			TO_NAME     = 'to_name',        //jméno příjemce
			TO_EMAIL    = 'to_email',       //email příjemce
			REPLY_TO    = 'reply_to',       //email příjemce
			SUBJECT     = 'subject',        //předmět emailu
			BODY_TEXT   = 'body_text',      //textová verze emailu
			BODY_HTML   = 'body_html',      //HTML verze emailu
			CHARSET     = 'charset',        //kódování e-mailu

			LAST = ''; //určeno k odstranění po dokončení seznamu

	public $id;
	public $container;

	private $sender;
	private $sendermail;
	private $recipient;
	private $recipientmail;
	private $subject;
	private $body;
	private $headers = array();
	private $additional;
    private $boundary;

    private $SmtpServer="essensmail-cz-ham.zarea.net";
    private $SmtpPort="25"; //default
    private $SmtpUser="info@essensmail.com";
    private $SmtpPass="essensinf0";
    
	public function __construct($_id, $_container)
	{
		if (get_class($_container) === 'App') {
			$this->id = $_id;
			$this->container = $_container;
			App::lg("Vytvoren mailer '$this->id'", $this);
		} else {
			throw new Exception("Konstruktor maileru ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
		}
	}


	public function prepare($params) {
		if (!empty($params[self::FROM_NAME]))
			$this->sender = '"'.$this->AltBase64($params[self::FROM_NAME]).'" <'.$params[self::FROM_EMAIL].'>';
		else
			$this->sender = '<'.$params[self::FROM_EMAIL].'>';
		if (!empty($params[self::TO_NAME]))
			$this->recipient = '"'.$this->AltBase64($params[self::TO_NAME]).'" <'.$params[self::TO_EMAIL].'>';
		else
			$this->recipient = '<'.$params[self::TO_EMAIL].'>';
        $this->sendermail = $params[self::FROM_EMAIL];
        $this->recipientmail = $params[self::TO_EMAIL];
		$this->subject = $this->AltBase64($params[self::SUBJECT]);
		$this->headers[] = 'From: ' . $this->sender;
		if (!empty($params[self::REPLY_TO]))
			$this->headers[] = 'Reply-To: ' . $params[self::REPLY_TO];
		else
			$this->headers[] = 'Reply-To: ' . $params[self::FROM_EMAIL];
		$this->additional = '-f'.$params[self::FROM_EMAIL];
        
        if (!empty($params[self::BODY_HTML])) {

            $this->boundary = md5(microtime(true));
            $this->headers[] = 'MIME-Version: 1.0';
            $this->headers[] = 'Content-Type: multipart/alternative;'."\n\t".'boundary="'.$this->boundary.'"';
            $body = "This is a MIME encoded message.\n\n";
            $this->addPart($params[self::BODY_TEXT],'text/plain');
            $this->addPart($params[self::BODY_HTML],'text/html');
            
            $this->body .= "\n\n" . '--' . $this->boundary . '--' . "\n"; 

        } else {
            
            $this->headers[] = 'Content-type: text/plain; charset=UTF-8';
            $this->body = $params[self::BODY_TEXT];

        }
            
		return $this;
	}


	public function go($recipient_email='',$recipient_name='')
	{

		App::lg("Send mail...", $this);

		if ($recipient_email != '') {

			if (!empty($recipient_name)) $this->recipient = '"'.$this->AltBase64($recipient_name).'" <'.$recipient_email.'>'; else $this->recipient = '<'.$recipient_email.'>';
            $this->recipientmail = $recipient_email;

		}

		$headers = implode("\n", $this->headers);

        $result = $this->SMTPmail($this->sender, $this->recipient, $this->subject, $headers, $this->body);
        
        App::dump($result);
        
		//zpracovat POP3




		return $this->id;
	}

    
    private function addPart($part,$type) {

        $this->body .= "\n\n" . '--' . $this->boundary . "\n";
        
        if ($type == 'text/plain' || $type == 'text/html') {
        
            $quoted = quoted_printable_encode($part);
            if ($part != $quoted) {$part = $quoted; $coded = true;} else $coded = false;
            
            $this->body .= 'Content-Type: ' . $type . ';' . "\n\t" . 'charset="UTF-8"' . (($coded) ? "\n" . 'Content-Transfer-Encoding: quoted-printable' : '') . "\n\n" . $part;
            
        } else {

            $part = base64_encode($part);
            $this->body .= 'Content-Type: ' . $type . "\n" . 'Content-Transfer-Encoding: base64' . "\n\n" . $part;
            
        }
            
        
    }
    
    
    private function SMTPmail ($from,$to,$subject,$headers,$body) {

        if ($SMTPIN = fsockopen ($this->SmtpServer, $this->SmtpPort)) {
            
            $mail = "To: ".$to."\r\nSubject:".$subject."\r\n".$headers."\r\n\r\n".$body."\r\n.\r\n";
            
            $this->sockTalk($SMTPIN, '', $talk);
            $this->sockTalk($SMTPIN, 'EHLO ' . $_SERVER['HTTP_HOST'], $talk);
            $this->sockTalk($SMTPIN, 'AUTH LOGIN', $talk);
            $this->sockTalk($SMTPIN, base64_encode($this->SmtpUser), $talk);
            $this->sockTalk($SMTPIN, base64_encode($this->SmtpPass), $talk);
            $this->sockTalk($SMTPIN, 'MAIL FROM: <' . $this->sendermail . '>  SIZE=' . strlen($mail) . ' BODY=8BITMIME', $talk);
            $this->sockTalk($SMTPIN, 'RCPT TO: <' . $this->recipientmail . '>', $talk);
            $this->sockTalk($SMTPIN, 'DATA', $talk);
            $this->sockTalk($SMTPIN, $mail, $talk);
            $this->sockTalk($SMTPIN, 'QUIT', $talk);
            fclose($SMTPIN); 
            
        } 
        return $talk;
        

} 
    
    private function sockTalk($cocket, $command,&$talk) {
        if (!empty($command)) {
            @fwrite ($cocket, $command . "\r\n");
            $talk[] = $command;
        }
        $i=0; while(($line=fgets($cocket, 256))) {$talk[] = rtrim($line); if (strlen($line)<3 || substr($line,3,1) == ' ') break; if ($i>15) {App::lg('Chyba čtení odpovědi',$this); break 1;}; $i++;}

    }
    
    private function AltBase64($text) {
        
        $quoted = quoted_printable_encode($text);
        
        if ($text != $quoted) return '=?UTF-8?B?' . base64_encode($text) . '?=';
        else return $text;
        
    }

    
    private function normaliza($string){
        $table = array('À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'A','Þ'=>'B','Ç'=>'C','Ć'=>'C','Č'=>'C','Ð'=>'Dj',
        			   'Ď'=>'D','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','Ě'=>'E','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ľ'=>'L',
        			   'Ĺ'=>'L','Ñ'=>'N','Ň'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ő'=>'O','Ø'=>'O','Ř'=>'R',
        			   'Ŕ'=>'R','Š'=>'S','ß'=>'Ss','Ť'=>'T','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ů'=>'U','Ű'=>'U','Ý'=>'Y',
        			   'Ÿ'=>'Y','Ž'=>'Z','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'a','þ'=>'b','ç'=>'c','ć'=>'c',
        			   'č'=>'c','ð'=>'dj','ď'=>'d','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ě'=>'e','ì'=>'i','í'=>'i','î'=>'i',
        			   'ï'=>'i','ľ'=>'l','ĺ'=>'l','ñ'=>'n','ň'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ő'=>'o',
        			   'ø'=>'o','ř'=>'r','ŕ'=>'r','š'=>'s','ß'=>'ss','ť'=>'t','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ů'=>'u',
        			   'ű'=>'u','ý'=>'y','ÿ'=>'y','ž'=>'z');
        return preg_replace('/[^(\x20-\x7F)]*/','?',strtr($string, $table));
    }

}