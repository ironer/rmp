<?php

class Reader
{
        
	public $id;
	public $container;
    
    public $prepared=0;                       //počet emailů k odeslání
    public $sent=0;                         //počet odeslaných mailů
    public $errors=0;                         //počet chybně odeslaných adres
    public $localerrors;                        

    public $used_cache;
            
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
    
    private $msgid_len = 20;
    private $index_len = 10;
	

	public function __construct($_id, $_container)
	{
		if (get_class($_container) === 'App') {
			$this->id = $_id;
			$this->container = $_container;
			App::lg("Vytvoren mailer '$this->id'", $this);
		} else {
			throw new Exception("Konstruktor maileru ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
		}
        
//        $this->readData();
        
	}
    
    




    
    public function readData() {
        
        $this->storage = CACHE . '/mailer/' . $this->used_cache;

        if (!is_dir($this->storage)) {
            
            App::lg('Nenalezena cache '.CACHE . '/mailer/' .$this->used_cache);
            
        } else {
            
            if (is_file($this->storage.'/data.dat')) $this->data = unserialize(file_get_contents($this->storage.'/data.dat'));
            if (is_file($this->storage.'/indexes.dat')) $this->indexes = unserialize(file_get_contents($this->storage.'/indexes.dat'));
            if (is_file($this->storage.'/emails.dat')) $this->emails = unserialize(file_get_contents($this->storage.'/emails.dat'));
            if (is_file($this->storage.'/encoded.dat')) $this->encoded = unserialize(file_get_contents($this->storage.'/encoded.dat'));
            
            if (is_array($this->emails)) $this->prepared = count($this->emails); else $this->prepared = 0;
            
            if (is_file($this->storage.'/sendindex.dat')) $this->sent = filesize($this->storage.'/sendindex.dat')/($this->msgid_len+$this->index_len+2); else $this->sent = 0;
            
            if (is_file($this->storage.'/errors.dat')) $this->errors = count(explode("\n",file_get_contents($this->storage.'/errors.dat')))-1;

        }
        
    }



}