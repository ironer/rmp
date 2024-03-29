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
			ATTACHMENTS = 'attachments',    //přílohy
			CHARSET     = 'charset',
            DATADIR     = 'mailer',         //složka uložiště

			EOL = "\r\n";

	public $id;
	public $container;
    
    public $prepared=0;                       //počet emailů k odeslání
    public $sent=0;                         //počet odeslaných mailů
    public $errors=0;                         //počet chybně odeslaných adres
    public $localerrors;                        
        
    private $runSMTP = true;
    
	private $indexes = array(
		'from' => 0,
		'reply' => 0,
		'to' => 0,
		'bcc' => 0,
		'subject' => 0,
		'text' => 0,
		'html' => 0,
		'attachments' => array(),
		'headers' => 0
	);

	private $data = array(
		'from' => array(),
		'reply' => array(),
		'to' => array(),
		'bcc' => array(),
		'subject' => array(),
		'text' => array(),
		'html' => array(),
		'attachments' => array(),
		'headers' => array()
	);
    
	private $emails = array();

	private $encoded = array(
		'fromEmail' => '',
		'fromFull' => '',
		'toEmail' => '',
		'toFull' => '',
		'reply' => '',
		'bcc' => '',
        'bccEmails' => array(),
		'subject' => '',
        'headers' => array(),
		'text' => '',
		'html' => '',
		'attachments' => array(),
	);
    
    private $fullemail = array();
    
    private $boundary = array();

    private $storage;

    private $f_errors;
    private $f_emails;
    private $f_index;
    
    private $msgid_len = 20;
    private $index_len = 10;
	
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
        
        $this->readData();
        
	}
    
    
    public function addEmail($params) {
        
        $email = array();
        
        foreach ($params as $key=>$param) {
            
            if ($key == self::BCC) {
                
                if (is_array($param)) {
                
                    $email['bcc'] = array();
                    
                    foreach ($param as $item) {
                        
                        $index = $this->indexes['bcc']++;
                        $this->data['bcc'][$index] = $item;
                        $email['bcc'][] = $index;
                        
                    }
                    
                 } else  $email['bcc'] = null;
            }
            
            elseif ($key == self::ATTACHMENTS) {
                
                $email[$key] = array();
                
                foreach ($param as $akey=>$item) {
                    
                   if (isset($this->indexes[self::ATTACHMENTS]['key_'.$akey])) $index = ++$this->indexes[self::ATTACHMENTS]['key_'.$akey];
						 else $this->indexes[self::ATTACHMENTS]['key_'.$akey] = $index = 0;
                         
                    $this->data[self::ATTACHMENTS]['key_'.$akey][$index] = $item;
                    $email[self::ATTACHMENTS]['key_'.$akey] = $index;
                    
                }

            }
            
            else {
                
                $index = $this->indexes[$key]++;
                $this->data[$key][$index] = $params[$key];
                $email[$key] = $index;
                    
            }
            
        }
        
        $this->emails[] = $email;
        $this->prepared++;
                    
    }
    

	public function go($count=0) {
        
        App::lg('Start go',$this);
//        App::dump($this->emails);
//        App::dump($this->data);
        if (is_file($this->storage.'/sendindex.dat')) {
            $fileindex = explode("\n",file_get_contents($this->storage.'/sendindex.dat'));
            $this->sent = count($fileindex)-1;
        } else {
            $this->sent = 0;
        }
        if (is_file($this->storage.'/encoded.dat')) $this->encoded = unserialize(file_get_contents($this->storage.'/encoded.dat'));

        $counter = 0;
        $timer = microtime(true);
        $localerrors = 0;
        
        echo 'sent '.$this->sent;
        
        array_splice($this->emails,0,$this->sent);
        
        echo ' tosend '.count($this->emails);
        
        foreach ($this->emails as $email) {
            
//            App::dump($email);
                        
            for ($i=0;$i<3;$i++) $this->boundary[$i] = md5(microtime(true).uniqid());

            $this->fullemail['smtp_replies']=array();
            
            foreach ($email as $key=>$param) {
                
                if (($this->fullemail[$key] = $email[$key]) === null) unset($this->fullemail[$key]);
                
                switch ($key) {
                    
                    case 'from';
                    case 'to':
                        
                        $this->encoded[$key.'Email'] = $this->data[$key][$email[$key]][0];
                        $this->encoded[$key.'Full']  = $this->encodeUser($this->data[$key][$email[$key]]);
                        if ($key == 'from') $this->encoded['headers']['from'] = 'From: ' . $this->encoded[$key.'Full'];
                        break;
                    
                    case 'bcc':
                        
                        $this->encoded['bcc'] = '';
                        $this->encoded['bccEmails'] = array();
                        
                        if (is_array($email['bcc'])) {
                        
                            
                            foreach ($email['bcc'] as $bccindex) {
                                
                                $this->encoded['bcc'] .= (($this->encoded['bcc']!='') ? ',' : '') . $this->encodeUser($this->data['bcc'][$bccindex]);
                                $this->encoded['bccEmails'][] = $this->data['bcc'][$bccindex][0];
                                
                            }
                            
                        }
                        
                        break;

                    case 'subject':
                    
                        $this->encoded[$key] = $this->AltBase64($this->data[$key][$email[$key]]);
                        break;

                    case 'text';
                    case 'html':
                        
                        if ($key == 'text') $type = 'text/plain'; else $type = 'text/html';
                        $this->encoded[$key] = 'Content-Type: ' . $type . ';' . "\n\t" . 'charset="UTF-8"' . "\n" . 'Content-Transfer-Encoding: quoted-printable' . "\n\n" . quoted_printable_encode($this->data[$key][$email[$key]]);
                        break;
                        
                    case 'attachments':
                        
                        foreach ($email['attachments'] as $akey=>$fileindex) {
                            
                            $file = $this->data['attachments'][$akey][$fileindex];
                            if ($slashpos = strrpos($file,'/')!==false) $filename = substr($file,$slashpos+1); else $filename = $file;
                            
                            
                            $fi = finfo_open(FILEINFO_MIME);
                            $mime_type = finfo_file($fi, $file);
                            $mime_type = substr($mime_type,0,strrpos($mime_type,';'));
                            
                            $this->encoded['attachments'][$akey] = 'Content-Type: ' . $mime_type . ';' . "\n\t" . 'name="' . $filename . '"' . "\n" . 'Content-Transfer-Encoding: base64' . "\n" . 'Content-Disposition: attachment;' . "\n\t" . 'filename="' . $filename . '"' . "\n\n" . chunk_split(base64_encode(file_get_contents($file)));
                                 
                        }
                                            
                        break;

                    case 'headers':
                        
                        foreach ($this->data['headers'][$email[$key]] as $akey=>$header) {
                            
                            $this->encoded['headers'][$akey] = $header;
                                 
                        }
                                            
                        break;

                 }
                 
            }
            
            $body = $this->createMIME();

    App::lg('Encode end',$this);

            $smtp = $this->SMTPmail($body);
            
            App::dump($smtp);
            

    App::lg('SMTP send',$this);

            $this->f_emails = fopen($this->storage.'/send.dat','a');
            $this->f_index  = fopen($this->storage.'/sendindex.dat','a');
            $stat = fstat($this->f_emails);
            $fpos = $stat['size'];
            fputs($this->f_index,str_pad($this->fullemail['msgid'],$this->msgid_len,' ',STR_PAD_RIGHT).str_pad($fpos,$this->index_len,' ',STR_PAD_RIGHT).self::EOL);
            fputs($this->f_emails,serialize($this->fullemail).self::EOL);
            
            $this->sent++;
            $counter++;
            if ($counter == $count) {file_put_contents($this->storage.'/encoded.dat',serialize($this->encoded)); fclose($this->f_emails); fclose($this->f_index); return;}

        }
        	   
	}
    
    
    private function createMIME() {
        
        $body = 'This is a MIME encoded message.' . self::EOL . self::EOL;
        
        $boundary = md5(microtime(true).uniqid());
            
		$this->encoded['headers']['mime'] = 'MIME-Version: 1.0';
		$this->encoded['headers']['contenttype'] = 'Content-Type: multipart/alternative;'."\n\t".'boundary="'.$this->boundary[0].'"';
			
        $body .= "\n\n" . '--' . $this->boundary[0] . "\n";
        $body .= 'Content-Type: multipart/alternative;'."\n\t".'boundary="'.$this->boundary[1]."\"\n\n\n";
        
        $body .= "\n\n" . '--' . $this->boundary[1] . "\n" . $this->encoded['text'];
		$body .= "\n\n" . '--' . $this->boundary[1] . "\n" . $this->encoded['html'];

		$body .= "\n\n" . '--' . $this->boundary[1] . '--' . "\n";

        //připojit přílohy
        
        foreach($this->encoded['attachments'] as $attachment) {
            
            $body .= "\n\n" . '--' . $this->boundary[0] . "\n" . $attachment;

        }
        
        $body .= "\n\n" . '--' . $this->boundary[0] . '--' . "\n";            

        return $body;
        
    }
    

	private function SMTPmail ($body) {
        
        $this->localerrors = 0;
                        
		if (($SMTPIN = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) && socket_connect($SMTPIN, self::$SmtpServer, self::$SmtpPort)) {
            
			$mail = "To: ".$this->encoded['toFull'] . self::EOL
                . "Subject:".$this->encoded['subject'] . self::EOL
                . implode(self::EOL,$this->encoded['headers']) . self::EOL . self::EOL
                . $body . self::EOL . '.';
			$recipients = 'RCPT TO: <' . $this->encoded['toEmail'] . '>' . self::EOL;;
			foreach ($this->encoded['bccEmails'] as $recipient) $recipients .= 'RCPT TO: <' . $recipient . '>' . self::EOL;

			$this->sockTalk($SMTPIN, '', $talk);
			$this->sockTalk($SMTPIN, 'EHLO ' . $_SERVER['HTTP_HOST'], $talk);
			$replies = explode(self::EOL,$this->sockTalk($SMTPIN, 'AUTH LOGIN' . self::EOL . base64_encode(self::$SmtpUser) . self::EOL . base64_encode(self::$SmtpPass) . self::EOL
					. 'MAIL FROM: <' . $this->encoded['fromEmail'] . '>' . self::EOL
					. $recipients
					. 'DATA' , $talk));
                    
            //zpracování odpovědí SMTP serveru na odesílatele a příjemce
            if ($replies[3][0]!='2') {$this->logError($this->encoded['toEmail'] . ' Err. ' . $replies[3]);}
            $this->fullemail['smtp_replies'][0] = substr($replies[3],0,3);
            for ($i=4;$i<=4+count($this->encoded['bccEmails']);$i++) {

                if ($replies[$i][0]!='2') {$this->logError($this->encoded['bccEmails'][$i-5] . ' Err. ' . $replies[$i]); $this->errors++; $this->localerrors++;}
                $this->fullemail['smtp_replies'][$i-3] = substr($replies[$i],0,3);
                
            }
            
            $reply = $this->sockTalk($SMTPIN, $mail, $talk);
            
            //zpracování odpovědi SMTP serveru na odeslané tělo emailu
            $this->fullemail['msgid'] = trim(strrchr($reply,' '));
            if ($reply[0]!='2') {$this->logError('SMTP' . ' Err. ' . $reply); $this->errors += count($this->encoded['bccEmails'])+1-$this->localerrors;}
            $this->fullemail['smtp_replies'][] = substr($reply,0,3);
            
			$this->sockTalk($SMTPIN, 'QUIT', $talk);
			socket_close($SMTPIN);

		}
		return $talk;


	}

	private function sockTalk($socket, $command, &$talk) {
        
        if (!$this->runSMTP) {

    		if (!empty($command)) {
    		  
    			if (substr($command, 0, 10) === 'AUTH LOGIN') {
    				$command = preg_replace('#^(AUTH LOGIN[\r\n]+)[^\r\n]+([\r\n]+)[^\r\n]+#i', '$1***$2***', $command);
    			}
    			$talk[] = $command;
            
            }
            
            return $talk[] = 'SMTP disabled';
        
        } else {        
    		if (!empty($command)) {
    			socket_write($socket, $command . "\r\n");
    			if (substr($command, 0, 10) === 'AUTH LOGIN') {
    				$command = preg_replace('#^(AUTH LOGIN[\r\n]+)[^\r\n]+([\r\n]+)[^\r\n]+#i', '$1***$2***', $command);
    			}
    			$talk[] = $command;
    		}
///for windows server ojebávka!!
if ($_SERVER['SERVER_NAME'] == 'localhost') {
            $buff = '';
            $ln = socket_read($socket,1024,PHP_BINARY_READ); $buff .= $ln;
            if (substr($ln,0,3)=='334' && !strpos($ln,'success'))
                do {$ln = socket_read($socket,1024,PHP_BINARY_READ); $buff .= $ln;} while(substr($ln,0,3)!='354');
            return $talk[] = $buff;      
}else{
//standard version for Linux server
    		return $talk[] = socket_read($socket,1024,PHP_BINARY_READ); 
}
      }

	}
    
    public function reset() {
        
        $this->data = array();
        $this->indexes = array(
    		'from' => 0,
    		'reply' => 0,
    		'to' => 0,
    		'bcc' => 0,
    		'subject' => 0,
    		'text' => 0,
    		'html' => 0,
    		'attachments' => array()
    	);
        
        $this->emails = array();
        
    }
    

	private function AltBase64($text) {

		$quoted = quoted_printable_encode($text);

		if ($text != $quoted) return '=?UTF-8?B?' . base64_encode($text) . '?=';
		else return $text;

	}

    
    private function encodeUser($user) {

		if (!empty($user[1]))
			return '"'.$this->AltBase64($user[1]).'" <'.$user[0].'>';
		else
			return '<'.$user[0].'>';
        
    }
    
    
    private function readData() {
        
        $this->storage = CACHE . '/mailer/' . $this->id;

        if (!is_dir($this->storage)) {
            
            mkdir($this->storage);
            
        } else {
            
            if (is_file($this->storage.'/data.dat')) $this->data = unserialize(file_get_contents($this->storage.'/data.dat'));
            if (is_file($this->storage.'/indexes.dat')) $this->indexes = unserialize(file_get_contents($this->storage.'/indexes.dat'));
            if (is_file($this->storage.'/emails.dat')) $this->emails = unserialize(file_get_contents($this->storage.'/emails.dat'));
            if (is_file($this->storage.'/encoded.dat')) $this->encoded = unserialize(file_get_contents($this->storage.'/encoded.dat'));
            
            if (is_array($this->emails)) $this->prepared = count($this->emails); else $this->prepared = 0;
            
            if (is_file($this->storage.'/sendindex.dat')) $this->sent = filesize($this->storage.'/sendindex.dat')/($this->msgid_len+$this->index_len+2); else $this->sent = 0;
            
        }
        
    }


    public function saveData() {
            
            file_put_contents($this->storage.'/data.dat',serialize($this->data));
            file_put_contents($this->storage.'/indexes.dat',serialize($this->indexes));
            file_put_contents($this->storage.'/emails.dat',serialize($this->emails));
        
    }
    
    private function logError($txt) {
        
//        App::dump($txt); //dočasně chyby dumpovat, pak napsat zápis do errors.txt
        file_put_contents($this->storage.'/errors.dat',trim($txt).self::EOL,FILE_APPEND);
        
    }

    
//	private function normaliza($string){
//		$table = array('À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'A','Þ'=>'B','Ç'=>'C','Ć'=>'C','Č'=>'C','Ð'=>'Dj',
//			'Ď'=>'D','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','Ě'=>'E','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ľ'=>'L',
//			'Ĺ'=>'L','Ñ'=>'N','Ň'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ő'=>'O','Ø'=>'O','Ř'=>'R',
//			'Ŕ'=>'R','Š'=>'S','ß'=>'Ss','Ť'=>'T','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ů'=>'U','Ű'=>'U','Ý'=>'Y',
//			'Ÿ'=>'Y','Ž'=>'Z','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'a','þ'=>'b','ç'=>'c','ć'=>'c',
//			'č'=>'c','ð'=>'dj','ď'=>'d','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ě'=>'e','ì'=>'i','í'=>'i','î'=>'i',
//			'ï'=>'i','ľ'=>'l','ĺ'=>'l','ñ'=>'n','ň'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ő'=>'o',
//			'ø'=>'o','ř'=>'r','ŕ'=>'r','š'=>'s','ß'=>'ss','ť'=>'t','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ů'=>'u',
//			'ű'=>'u','ý'=>'y','ÿ'=>'y','ž'=>'z');
//		return preg_replace('/[^(\x20-\x7F)]*/','?',strtr($string, $table));
//	}

}