<?php

/**
 * EMail sending and handling class.
 *
 * @see EMailAttachment
 */
class EMail extends Instantiable {

	protected $from = '';
	protected $to = array();
	protected $cc = array();
	protected $bcc = array();
	protected $subject = '';
	protected $text = '';
	protected $html = '';
	protected $attachments = array();
	protected $charset = 'UTF-8';
	protected $base64 = false;
	protected $htmlheader = '';
	protected $htmlfooter = '';
	protected $htmltemplate = '';
	protected $texttemplate = '';
	protected $templatereplacements = array();

	protected static function encodeHeaderValue($string) {
		return '=?UTF-8?Q?'.imap_8bit($string).'?=';
	}

	protected static function encodeContact($email, $name='') {
		return $name ? (preg_match('/^[A-Za-z0-9 _-]+$/',$name) ? '"'.$name.'"' : self::encodeHeaderValue($name)).' <'.$email.'>' : $email;
	}

	/**
	 * Returns a obfuscated clickable email link.
	 * 
	 * @access public
	 * @static
	 * @param string $config_key Reads the email address from this config value
	 * @param string $text (optional) Defines a different link text (default: null)
	 * @return string
	 */
	public static function getLink($config_key, $text=null) {
		$email = Config::get('email', $config_key, true);
		if(!$email) return null;
		$html = '<a href="mailto:'.strtolower($email).'">'.($text ? $text : $email).'</a>';
		$code = bin2hex($html);
		$fake_address = 's'.mt_rand(100000,999999).'@example.'.(rand(0,1) ? 'com' : 'org');
		return "<span class=\"m-protected\" data-real=\"$code\"><!--googleoff: index--><a href=\"mailto:$fake_address\">$fake_address</a> (This e-mail address is invalid because it is protected against spam bots. Please enable JavaScript to see the real address.)<!--googleon: index--></span>";
	}

	protected function base64chunk($str) {
		return rtrim(chunk_split(base64_encode($str),76,"\n"));
	}

	/**
	 * Defines the character set.
	 * 
	 * @access public
	 * @param string $charet
	 * @return void
	 */
	public function setCharset($charet) {
		$this->charset = $charset;
	}

	/**
	 * Tells the generator to use Base64 encoding.
	 * 
	 * @access public
	 * @param bool $useBase64 (optional) If set to true, Base64 encoding will be used (default: true)
	 * @return void
	 */
	public function useBase64($useBase64=true) {
		$this->base64 = $useBase64;
	}

	/**
	 * Specifies the email sender address and name.
	 * 
	 * @access public
	 * @param string $from The sender's email address
	 * @param string $name (optional) The sender's name (default: '')
	 * @return void
	 */
	public function setFrom($from, $name='') {
		$this->from = self::encodeContact($from, $name);
	}

	/**
	 * Returns the sender.
	 * 
	 * @access public
	 * @return string
	 */
	public function getFrom() {
		return $this->from;
	}

	/**
	 * Adds a recipient.
	 * 
	 * @access public
	 * @param string $to The recipient's email address
	 * @param string $name (optional) The recipient's name (default: '')
	 * @return void
	 */
	public function addTo($to, $name='') {
		$this->to[] = self::encodeContact($to, $name);
	}

	/**
	 * Adds a recipient via CC.
	 * 
	 * @access public
	 * @param string $cc The recipient's email address
	 * @param string $name (optional) The recipient's name (default: '')
	 * @return void
	 */
	public function addCc($cc, $name='') {
		$this->cc[] = self::encodeContact($cc, $name);
	}

	/**
	 * Adds a recipient via BCC.
	 *
	 * BCC addresses are hidden to recipients.
	 * 
	 * @access public
	 * @param string $bcc The recipient's email address
	 * @param string $name (optional) The recipient's name (default: '')
	 * @return void
	 */
	public function addBcc($bcc) {
		$this->bcc[] = $bcc;
	}

	/**
	 * Returns a list of all recipients.
	 *
	 * @access public
	 * @param bool $mix_types (optional) If set to true, there will be no separation between To, CC and BCC recipients (default: false)
	 * @return void
	 * @see self::addTo()
	 * @see self::addCc()
	 * @see self::addBcc()
	 *
	 * @example
	 * <code>
	 * $email->getRecipients(); // Returns ['to' => ['test@example.com'], 'cc' => [], 'bcc' => []]
	 * </code>
	 */
	public function getRecipients($mix_types=false) {
		$recipients = array(
			'to' => $this->to,
			'cc' => $this->cc,
			'bcc' => $this->bcc
		);
		if($mix_types) return array_merge($recipients['to'],$recipients['cc'],$recipients['bcc']);
		return $recipients;
	}

	/**
	 * Defines the email subject text.
	 * 
	 * @access public
	 * @param string $subject
	 * @return void
	 */
	public function setSubject($subject) {
		$this->subject = self::encodeHeaderValue($subject);
	}

	/**
	 * Specifies the email text content (text-only body).
	 * 
	 * @access public
	 * @param string $text
	 * @return void
	 * @see self::setHtml()
	 */
	public function setText($text) {
		$this->text = $text;
	}

	/**
	 * Specifies the email HTML content (body).
	 *
	 * If no text content is set via `setText` it will be auto-generated from the HTML content.
	 * 
	 * @access public
	 * @param string $html
	 * @return void
	 * @see self::setText()
	 */
	public function setHtml($html) {
		$this->html = $html;
	}

	/**
	 * Attaches a file.
	 * 
	 * @access public
	 * @param string $filename
	 * @return void
	 */
	public function attachFile($filename) {
		return $this->attachments[] = new EMailAttachment($filename);
	}

	/**
	 * Adds an attachment via `EMailAttachment` object.
	 * 
	 * @access public
	 * @param EMailAttachment $attachment
	 * @return void
	 */
	public function addAttachment(EMailAttachment $attachment) {
		return $this->attachments[] = $attachment;
	}

	/**
	 * Generates and sends the email.
	 * 
	 * @access public
	 * @return bool true on success, false on error
	 */
	public function send() {
		$headers = array();
		if(!$this->from) {
			return Error::warning('"From" address not set.');
		}
		$headers[] = 'From: '.$this->from;
		if($this->cc) $headers[] = 'Cc: '.implode(', ',$this->cc);
		if($this->bcc) $headers[] = 'Bcc: '.implode(', ',$this->bcc);
		$headers[] = 'MIME-Version: 1.0';
		$boundary = 'Part-Alternative-'.md5(microtime().'a');
		$boundaryMixed = 'Part-Mixed-'.md5(microtime().'m');
		if($this->text && !$this->html && !$this->attachments) {
			$headers[] = "Content-Type: text/plain;\n\tcharset=".$this->charset;
			if($this->base64) {
				$headers[] = 'Content-Transfer-Encoding: base64';
				$body = $this->base64chunk($this->text);
			}else{
				$headers[] = 'Content-Transfer-Encoding: quoted-printable';
				$body = imap_8bit($this->text);
			}
		}else{
			if($this->attachments) {
				$headers[] = "Content-Type: multipart/mixed;\n\tboundary=\"".$boundaryMixed."\"";
			}else{
				$headers[] = "Content-Type: multipart/alternative;\n\tboundary=\"".$boundary."\"";
			}
			$parts = array();
			$parts[] = "Content-Type: text/plain;\n\tcharset=".$this->charset."\n".
				($this->base64
					? "Content-Transfer-Encoding: base64\n\n".
						$this->base64chunk($this->text
							? $this->text
							: $this->transformHtmlToText($this->html)
						)
					: "Content-Transfer-Encoding: quoted-printable\n\n".
						imap_8bit($this->text
							? $this->text
							: $this->transformHtmlToText($this->html)
						)
				);
			if($this->html) {
				if($this->base64) {
					$parts[] = "Content-Type: text/html;\n\tcharset=".$this->charset."\n".
						"Content-Transfer-Encoding: base64\n\n".
						$this->base64chunk($this->html);
				}else{
					$parts[] = "Content-Type: text/html;\n\tcharset=".$this->charset."\n".
						"Content-Transfer-Encoding: quoted-printable\n\n".
						imap_8bit($this->html);
				}
			}
			$body = "--".$boundary."\n".implode("\n\n--".$boundary."\n",$parts)."\n\n--".$boundary."--";
			if($this->attachments) {
				$att = array("Content-Type: multipart/alternative;\n\tboundary=\"".$boundary."\"\n\n".$body);
				for($i=0; $i<count($this->attachments); $i++) {
					$att[] = "Content-Type: ".$this->attachments[$i]->getMimeType()."\n".
						"Content-Disposition: attachment; filename=\"".$this->attachments[$i]->getFilename()."\"\n".
						"Content-Transfer-Encoding: base64\n\n".
						$this->base64chunk($this->attachments[$i]->getData());
				}
				$body = '--'.$boundaryMixed."\n".implode("\n\n--".$boundaryMixed."\n",$att)."\n\n--".$boundaryMixed.'--';
			}
		}
		return mail(implode(', ',$this->to), $this->subject, $body, implode("\n",$headers)) ? true : false;
	}

	protected function transformHtmlToText($html) {
		$text = $html;
		$text = preg_replace("@<script[^>]*?>.*?</script>@si","",$text);
		$text = preg_replace("@<style.*?</style>@siU","",$text);
		$text = preg_replace("@<br[^>]*?>@si","[\0LINEBREAK\0]",$text);
		$text = preg_replace("@<[\/\!]*?[^<>]*?>@si","",$text);
		$text = preg_replace("@<![\s\S]*?--[ \t\n\r]*>@","",$text);
		$text = str_replace("[\0LINEBREAK\0]","\n",$text);
		$text = htmlspecialchars_decode($text);
		$text = trim($text);
		$text = preg_replace("/[ \t]+/"," ",$text);
		$text = preg_replace("/(\r\n|\n|\r)/","\n",$text);
		$text = preg_replace("/\n{2,}/","\n\n",$text);
		return $text;
	}

	/**
	 * Defines the HTML template header filename.
	 *
	 * This header will be prepended to the HTML content set via `setHtml`.
	 * 
	 * @access public
	 * @param string $htmlheaderfile
	 * @return void
	 * @see self::setHtml()
	 */
	public function setHtmlHeader($htmlheaderfile) {
		$this->htmlheader = file_get_contents($htmlheaderfile);
	}

	/**
	 * Defines the HTML template footer filename.
	 *
	 * This footer will be appended to the HTML content set via `setHtml`.
	 * 
	 * @access public
	 * @param string $htmlfooterfile
	 * @return void
	 * @see self::setHtml()
	 */
	public function setHtmlFooter($htmlfooterfile) {
		$this->htmlfooter = file_get_contents($htmlfooterfile);
	}

	/**
	 * Defines the HTML template body file.
	 *
	 * The contents of this file will be read and set as HTML content.
	 * Custom fields can later be replaced using `setReplacement`.
	 * Those custom fields can be masked using curly brackets (e.g. `{firstname}`, `{lastname}`)
	 * 
	 * @access public
	 * @param string $htmltemplatefile
	 * @return void
	 * @see self::setTextTemplate()
	 * @see self::setHtml()
	 * @see self::setReplacement()
	 *
	 * @example
	 * <code>
	 * // ...
	 * $email->setHtmlTemplate('templates/email_html.tpl');
	 * $email->setReplacement('firstname', 'John');
	 * $email->setReplacement('lastname', 'Doe');
	 * $email->applyTemplates();
	 * // ...
	 * $email->send();
	 * </code>
	 */
	public function setHtmlTemplate($htmltemplatefile) {
		$this->htmltemplate = file_get_contents($htmltemplatefile);
	}

	/**
	 * Defines the text template body file.
	 *
	 * The contents of this file will be read and set as text content.
	 * Custom fields can later be replaced using `setReplacement`.
	 * Those custom fields can be masked using curly brackets (e.g. `{firstname}`, `{lastname}`)
	 * 
	 * @access public
	 * @param string $texttemplatefile
	 * @return void
	 * @see self::setHtmlTemplate()
	 * @see self::setText()
	 * @see self::setReplacement()
	 *
	 * @example
	 * <code>
	 * // ...
	 * $email->setTextTemplate('templates/email_text.tpl');
	 * $email->setReplacement('firstname', 'John');
	 * $email->setReplacement('lastname', 'Doe');
	 * $email->applyTemplates();
	 * // ...
	 * $email->send();
	 * </code>
	 */
	public function setTextTemplate($texttemplatefile) {
		$this->texttemplate = file_get_contents($texttemplatefile);
	}

	/**
	 * Sets a custom replacement field for templates.
	 * 
	 * @access public
	 * @param string $field
	 * @param string $value
	 * @return void
	 * @see self::setHtmlTemplate()
	 * @see self::setTextTemplate()
	 */
	public function setReplacement($field, $value) {
		$this->templatereplacements[strtoupper($field)] = $value;
	}

	/**
	 * Applies the previously set template files and replacements.
	 * 
	 * @access public
	 * @param array $fields (optional) Replacement field array like in `setReplacement` (default: null)
	 * @param bool $html_escape (optional) If set to true, the replacement fields will be HTML escaped (default: true)
	 * @return void
	 */
	public function applyTemplates(array $fields=null, $html_escape=true) {
		$html = $this->htmlheader.$this->htmltemplate.$this->htmlfooter;
		if($fields===null) $fields = array();
		$fields = array_merge($fields, $this->templatereplacements);
		foreach($fields as $field=>$value) {
			$field = str_replace(array('{{','}}'),array('{','}'),'{'.strtoupper($field).'}');
			$value = trim($value);
			if($html_escape) $value = htmlspecialchars($value);
			if($html) $html = str_replace($field,nl2br($value),$html);
		}
		if($html) $this->html = $html;
	}

}
