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
	private $recipient;
	private $subject;
	private $body;
	private $headers = array();
	private $additional;


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
			$this->sender = '"'.$params[self::FROM_NAME].'" <'.$params[self::FROM_EMAIL].'>';
		else
			$this->sender = '"'.$params[self::FROM_EMAIL].'>';
		if (!empty($params[self::TO_NAME]))
			$this->recipient = '"'.$params[self::TO_NAME].'" <'.$params[self::TO_EMAIL].'>';
		else
			$this->recipient = '"'.$params[self::TO_EMAIL].'>';
		$this->subject = '=?UTF-8?B?' . base64_encode($params[self::SUBJECT]) . '?=';
		$this->body = $params[self::BODY_TEXT];
		$this->headers[] = 'From: ' . $this->sender;
		$this->headers[] = 'Content-type: text/plain; charset=utf-8';
		if (!empty($params[self::REPLY_TO]))
			$this->headers[] = 'Reply-To: ' . $params[self::REPLY_TO];
		else
			$this->headers[] = 'Reply-To: ' . $params[self::FROM_EMAIL];
		$this->additional = '-f'.$params[self::FROM_EMAIL];

		return $this;
	}


	public function go($recipient_email='',$recipient_name='')
	{
		App::lg("Send mail...", $this);

		if ($recipient_email != '') {

			if (!empty($recipient_name)) $this->recipient = '"'.$recipient_name.'" <'.$recipient_email.'>'; else $this->recipient = '"'.$recipient_email.'>';

		}

		$headers = implode("\n", $this->headers);

//        @mail($this->recipient, $this->subject, $this->body, $headers, $this->additional);
		App::lg("@mail($this->recipient, $this->subject, $this->body, $headers, $this->additional)", $this);

		//mail($this->recipient, $this->subject, $this->body, $headers, $this->additional);

		//zpracovat POP3




		return $this->id;
	}


}