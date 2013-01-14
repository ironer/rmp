<?php

class Mailer
{

    const   FROM        = 'from',           //jméno odesílatele
            TO          = 'to',             //jméno příjemce
			BCC         = 'bcc',            //příjemci skrytých kopií (pole polí s jménem a emailems)
			REPLY_TO    = 'reply',          //email příjemce
			SUBJECT     = 'subject',        //předmět emailu
			TEXT        = 'text',   	    //textová verze emailu
			HTML        = 'html',   	    //HTML verze emailu
			CHARSET     = 'charset',        //kódování e-mailu

			EOL = "\r\n";

	public $id;
	public $container;

	private $indexes = array(
		'from' => 0,
		'reply' => 0,
		'to' => 0,
		'bcc' => 0,
		'subject' => 0,
		'text' => 0,
		'html' => 0,
		'attachments' => 0
	);

	private $data = array(
		'from' => array(),
		'reply' => array(),
		'to' => array(),
		'bcc' => array(),
		'subject' => array(),
		'text' => array(),
		'html' => array(),
		'attachments' => array()
	);

	private $emails = array(array('from'=>0, 'to'=>0, 'bcc'=>array(0,1,2,3)));

	private $encoded = array(
		'fromName' => '',
		'fromEmail' => '',
		'toName' => '',
		'toEmail' => '',
		'reply' => '',
		'bcc' => '',
		'subject' => '',
		'text' => '',
		'html' => '',
		'attachments' => array()
	);
	
	private static $SmtpServer = "essensmail-cz-ham.zarea.net";
	private static $SmtpPort = "25"; //default
	private static $SmtpUser = "info@essensmail.com";
	private static $SmtpPass = "essensinf0";

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
        
        $email = array();
        
        foreach ($params as $key=>$param) {
            
            switch ($key) {
                
                case self::TO:
                case self::BCC:
                
                    foreach ($param as $user) {
                        
                        $index = $this->indexes[$key]++;
                        $this->data[$key][$index] = $user;
                        $email[$key][] = $index;
                        
                    }
                break;
                
                default:
                    
                    $index = $this->indexes[$key]++;
                    $this->data[$key][$index] = $params[$key];
                    $email[$key][] = $index;
                    
                break;
                    
            }
            
        }
        
        App::dump($email);
        App::dump($this->data);
            
    }
    

	public function old_prepare($params) {

		//zpracování odesílatele a příjemců

		if (!empty($params[self::FROM_NAME]))
			$this->sender = '"'.$this->AltBase64($params[self::FROM_NAME]).'" <'.$params[self::FROM_EMAIL].'>';
		else
			$this->sender = '<'.$params[self::FROM_EMAIL].'>';
		if (!empty($params[self::TO_NAME]))
			$this->recipient = '"'.$this->AltBase64($params[self::TO_NAME]).'" <'.$params[self::TO_EMAIL].'>';
		else
			$this->recipient = '<'.$params[self::TO_EMAIL].'>';
		$this->sendermail = $params[self::FROM_EMAIL];
		$this->recipientmails[] = $params[self::TO_EMAIL];
		if (is_array($params[self::BCC])) {

			foreach ($params[self::BCC] as $bcc) {

				if ($this->bcc != '') $this->bcc .= ',';

				if (!empty($bcc[1]))
					$this->bcc .= '"'.$this->AltBase64($bcc[1]).'" <'.$bcc[0].'>';
				else
					$this->bcc .= '<'.$bcc[0].'>';

				$this->recipientmails[] = $bcc[0];

			}

			$this->headers[] = 'Bcc: ' . $this->bcc;

		}


		//příprava obsahu mailu

		$this->subject = $this->AltBase64($params[self::SUBJECT]);
		$this->headers[] = 'From: ' . $this->sender;
		if (!empty($params[self::REPLY_TO]))
			$this->headers[] = 'Reply-To: ' . $params[self::REPLY_TO];
		else
			$this->headers[] = 'Reply-To: ' . $params[self::FROM_EMAIL];
		$this->additional = '-f'.$params[self::FROM_EMAIL];

		if (!empty($params[self::BODY_HTML])) { //vytvořit MIME mail

			$this->boundary = md5(microtime(true));
			$this->headers[] = 'MIME-Version: 1.0';
			$this->headers[] = 'Content-Type: multipart/alternative;'."\n\t".'boundary="'.$this->boundary.'"';
			$body = "This is a MIME encoded message.\n\n";
			$this->addPart($params[self::BODY_TEXT],'text/plain');
			$this->addPart($params[self::BODY_HTML],'text/html');

			$this->body .= "\n\n" . '--' . $this->boundary . '--' . "\n";

		} else {    //není HTML verze, ani příloha, pošleme obyčejný textový mail

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
			$this->recipientmail = array($recipient_email);

			unset($this->bcc); //zrušit příjemce skrytých kopií, aby se jim mail neposílal znovu

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

		if (($SMTPIN = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) && socket_connect($SMTPIN, self::$SmtpServer, self::$SmtpPort)) {

			$mail = "To: ".$to."\r\nSubject:".$subject."\r\n".$headers."\r\n\r\n".$body."\r\n.";
			$recipients = '';
			foreach ($this->recipientmails as $recipient) $recipients .= 'RCPT TO: <' . $recipient . '>' . self::EOL;

			$this->sockTalk($SMTPIN, '', $talk);
			$this->sockTalk($SMTPIN, 'EHLO ' . $_SERVER['HTTP_HOST'], $talk);
			$this->sockTalk($SMTPIN, 'AUTH LOGIN' . self::EOL . base64_encode(self::$SmtpUser) . self::EOL . base64_encode(self::$SmtpPass) . self::EOL
					. 'MAIL FROM: <' . $this->sendermail . '>' . self::EOL
					. $recipients
					. 'DATA' , $talk);
			$this->sockTalk($SMTPIN, $mail, $talk);
			$this->sockTalk($SMTPIN, 'QUIT', $talk);
			socket_close($SMTPIN);

		}
		return $talk;


	}

	private function sockTalk($socket, $command, &$talk) {
		if (!empty($command)) {
			socket_write($socket, $command . "\r\n");
			if (substr($command, 0, 10) === 'AUTH LOGIN') {
				$command = preg_replace('#^(AUTH LOGIN[\r\n]+)[^\r\n]+([\r\n]+)[^\r\n]+#i', '$1***$2***', $command);
			}
			$talk[] = $command;
		}

		$talk[] = socket_read($socket,512);

	}

	private function AltBase64($text) {

		$quoted = quoted_printable_encode($text);

		if ($text != $quoted) return '=?UTF-8?B?' . base64_encode($text) . '?=';
		else return $text;

	}

    
    private function encodeUser($user) {

		if (!empty($user[1]))
			$this->sender = '"'.$this->AltBase64($user[1]).'" <'.$user[0].'>';
		else
			$this->sender = '<'.$user[0].'>';
        
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