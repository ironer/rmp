<?php

class IMAPread
{
        
	public $id;
	public $container;
    
    private $storage;

	private static $IMAPuser   = 'info@essensmail.com' ;
	private static $IMAPpass   = 'essensinf0' ;
	private static $IMAPserver = 'pop3.essensmail.com' ;
	
	public function __construct($_id, $_container)
	{
		if (get_class($_container) === 'App') {
			$this->id = $_id;
			$this->container = $_container;
			App::lg("Vytvoren imapreader '$this->id'", $this);
		} else {
			throw new Exception("Konstruktor maileru ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
		}
        
	}
        

	public function go($count=0) {
	   
        $mailbox = imap_open(  "{".self::$IMAPserver.":110/pop3/notls}INBOX", self::$IMAPuser, self::$IMAPpass  );
        
        $emails = imap_search($mailbox, "NEW" , SE_UID);
        
        if (!is_array($emails)) {App::lg('Žádné maily na serveru...'); return;}
        
        $emails = array_slice($emails,0,10);
        
        foreach ($emails as $index) {
            
            $structure = imap_fetchstructure($mailbox, $index, FT_UID);
            
            $body = imap_fetchbody($mailbox, $index, $this->searchReport($structure->parts)+1, FT_UID);
            
            echo nl2br($body).'<br /><br />';

            preg_match('|X-Postfix-Queue-ID:\s+([0-9A-F]+)|i',$body,$msg_id);
            
            echo "$msg_id[1]<br /><br />";
            
            $body = str_replace("\r\n","\n",$body)."\n";
        
            preg_match_all('|Final-Recipient:[^;]+;\s*([a-z_\-0-9@\.]+)[^O]*Original-Recipient:[^;]+;\s*([a-z_\-0-9@\.]+).*?Diagnostic-Code:\s*(.*?)\n\n|ims',$body,$matches);
            
            array_shift($matches);
            
            App::dump($matches);
            
            //imap_delete($mailbox,$index,FT_UID);
            //imap_expunge($mailbox);
        
        
            echo '-----------------------------'."<br /><br />";
        
        }        	   
	}
    
    
    function searchReport($structure) {
        
        foreach ($structure as $index=>$part) if (strtolower($part->subtype) == 'delivery-status') return $index;
    
    }
    

    private function logError($txt) {
        
//        App::dump($txt); //dočasně chyby dumpovat, pak napsat zápis do errors.txt
        file_put_contents($this->storage.'/errors.dat',trim($txt).self::EOL,FILE_APPEND);
        
    }


}