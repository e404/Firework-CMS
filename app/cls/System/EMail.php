<?php

class EMail {

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

	public function setCharset($charet) {
		$this->charset = $charset;
	}

	public function useBase64($useBase64=true) {
		$this->base64 = $useBase64;
	}

	public function setFrom($from, $name='') {
		$this->from = $name ? '"'.$name.'" <'.$from.'>' : $from;
	}

	public function getFrom() {
		return $this->from;
	}

	public function addTo($to, $name='') {
		$this->to[] = $name ? '"'.$name.'" <'.$to.'>' : $to;
	}

	public function addCc($cc, $name='') {
		$this->cc[] = $name ? '"'.$name.'" <'.$cc.'>' : $cc;
	}

	public function addBcc($bcc) {
		$this->bcc[] = $bcc;
	}

	public function getRecipients($mix_types=false) {
		$recipients = array(
			'to' => $this->to,
			'cc' => $this->cc,
			'bcc' => $this->bcc
		);
		if($mix_types) return array_merge($recipients['to'],$recipients['cc'],$recipients['bcc']);
		return $recipients;
	}

	public function setSubject($subject) {
		$this->subject = $subject;
	}

	public function setText($text) {
		$this->text = $text;
	}

	public function setHtml($html) {
		$this->html = $html;
	}

	public function attachFile($filename) {
		return $this->attachments[] = new EMailAttachment($filename);
	}

	public function addAttachment(EMailAttachment $attachment) {
		return $this->attachments[] = $attachment;
	}

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
		$mail = array(
			'to' => implode(', ',$this->to),
			'subject' => '=?UTF-8?Q?'.imap_8bit($this->subject).'?=',
			'body' => $body,
			'headers' => implode("\n",$headers)
		);
		return mail($mail['to'],$mail['subject'],$mail['body'],$mail['headers']) ? true : false;
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

	public function setHtmlHeader($htmlheaderfile) {
		$this->htmlheader = file_get_contents($htmlheaderfile);
	}

	public function setHtmlFooter($htmlfooterfile) {
		$this->htmlfooter = file_get_contents($htmlfooterfile);
	}

	public function setHtmlTemplate($htmltemplatefile) {
		$this->htmltemplate = file_get_contents($htmltemplatefile);
	}

	public function setTextTemplate($texttemplatefile) {
		$this->texttemplate = file_get_contents($texttemplatefile);
	}

	public function setReplacement($field, $value) {
		$this->templatereplacements[strtoupper($field)] = $value;
	}

	public function applyTemplates($fields=null,$html_escape=true) {
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
