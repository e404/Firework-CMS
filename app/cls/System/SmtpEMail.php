<?php

/**
 * Same es EMail class, but uses real SMTP mailing instead of `sendmail`.
 * 
 * `SmtpEMail` can be used as a full replacement to `EMail`.
 * Use this class only with trusted and controlled (owned) SMTP servers, as it only uses AUTH PLAIN and persistent connections, which can cause problems with SMTP servers from public ISPs, such as getting rate limited or blocked.
 *
 * @see EMail
 * @see EMailAttachment
 */
class SmtpEMail extends EMail {

	protected $smtp_hostname = 'localhost';
	protected $smtp_host_ip = '127.0.0.1';
	protected $smtp_port = 25;
	protected $smtp_username = '';
	protected $smtp_password = '';
	
	const SMTP_TIMEOUT = 3; // seconds
	protected static $smtp_connection = null;

	public function __construct() {
		$this->smtp_hostname = Config::get('smtp', 'host') ?: $this->smtp_hostname;
		$this->smtp_host_ip = gethostbyname($this->smtp_hostname);
		$this->smtp_port = Config::get('smtp', 'port') ?: $this->smtp_port;
		$this->smtp_username = Config::get('smtp', 'username');
		$this->smtp_password = Config::get('smtp', 'password');
	}

	public function __destruct() {
		if(self::$smtp_connection) {
			stream_set_blocking(self::$smtp_connection, false); // Set stream to non-blocking mode. Speeds up deconstruction as fwrite does not wait for any server feedback.
			fwrite(self::$smtp_connection, "QUIT\r\n");
		}
	}

	protected function performSend($body, array $headers) {
		if($this->performSmtpSend($body, $headers)) return true;
		// Fallback to default sendmail
		return parent::performSend($body, $headers);
	}

	protected function performSmtpSend($body, array $headers) {
		if(!$this->openSmtpConnection()) return false;
		$this->sendSmtpLine('MAIL FROM:<'.$this->from_address.'>');
		$rcpts = preg_replace('/^[^<]+<([^>]+)>.*$/', '$1', $this->getRecipients(true));
		foreach($rcpts as $rcpt) {
			$this->sendSmtpLine('RCPT TO:<'.$rcpt.'>');
		}
		if(!$this->sendSmtpLine('DATA')) return false;
		$headers_insert = preg_filter('/^/', 'To: ', $this->to, 1);
		$headers_insert[] = 'Subject: '.$this->subject;
		array_splice($headers, 1, 0, $headers_insert);
		foreach($headers as $header) {
			$this->sendRawSmtpLine($header);
		}
		$this->sendRawSmtpLine(''); // Empty line signals beginning of body
		$body_split = preg_split('/[\r\n]+/', trim($body));
		foreach($body_split as $line) {
			$this->sendRawSmtpLine($line);
		}
		if(!$this->sendSmtpLine('.')) return false;
		return true;
	}
	protected function openSmtpConnection() {
		if(self::$smtp_connection) return self::$smtp_connection;
		self::$smtp_connection = @fsockopen($this->smtp_host_ip, $this->smtp_port, $errno, $errstr, self::SMTP_TIMEOUT);
		stream_set_timeout(self::$smtp_connection, self::SMTP_TIMEOUT);
		if($errno) {
			Error::warning('Failed establishing SMTP connection ('.$this->smtp_hostname.':'.$this->smtp_port.'): '.$errstr);
			return false;
		}
		if(!$this->readSmtpResponse()) return false;
		if(!$this->sendSmtpLine('EHLO '.$this->smtp_hostname)) return false;
		if($this->smtp_username && $this->smtp_password) {
			if(!$this->sendSmtpLine('AUTH PLAIN')) return false;
			if(!$this->sendSmtpLine($this->smtp_username)) return false;
			if(!$this->sendSmtpLine($this->smtp_password)) return false;
		}
		return true;
	}


	protected function readSmtpResponse($parse=false, $throw_warnings=true) {
		$response = '';
		while($chunk = fgets(self::$smtp_connection, 4096)) {
			$response.= $chunk;
			if(substr($chunk,3,1)==='-') continue;
			break;
		}
		if(!$parse && substr($response,0,1)>3) {
			return true;
		}
		$props = [];
		if(!preg_match_all('/([0-9]{3})[ -]([^\r\n]+)[\r\n]+/s', $response, $lines)) return [];
		if(substr($lines[1][0],0,1)>3) {
			Error::warning('SMTP Error: '.implode(' ', $lines[2]));
			return false;
		}
		return ['code' => (int) $lines[1][0], 'props' => $lines[2]];
	}

	protected function sendRawSmtpLine($cmd) {
		return fwrite(self::$smtp_connection, "$cmd\r\n");
	}

	protected function sendSmtpLine($cmd, $parse=false, $throw_warnings=true) {
		$success = $this->sendRawSmtpLine($cmd);
		if(!$success) return false;
		return $this->readSmtpResponse($parse, $throw_warnings);
	}

}
