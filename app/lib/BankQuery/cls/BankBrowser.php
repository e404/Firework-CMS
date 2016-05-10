<?php

require_once(__DIR__.'/BankDom.php');

class BankBrowser {

	protected $ch = null;
	protected $html = '';
	protected $header = [];
	protected $dom = null;
	protected $info = [];
	protected $url = '';
	public $debug = false;

	public function __construct() {
		$this->ch = curl_init();
		curl_setopt_array($this->ch, [
			CURLOPT_COOKIESESSION => true,
			CURLOPT_CERTINFO => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_COOKIEFILE => '',
			CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible)',
			CURLINFO_HEADER_OUT => true,
			CURLOPT_HEADERFUNCTION => array($this, 'readHeader')
		]);
	}

	public function readHeader($ch, $header) {
		if($h = trim($header)) {
			if(strstr($h, ':')) {
				$h = explode(':', $h, 2);
				$this->header[$h[0]] = trim($h[1]);
			}
		}
		return strlen($header);
	}

	public function navigate($url, $redirects=10) {
		if($redirects<0) {
			trigger_error('Browser redirection limit reached.', E_USER_WARNING);
			return;
		}
		$this->header = [];
		curl_setopt($this->ch, CURLOPT_URL, $url);
		if($this->url) curl_setopt($this->ch, CURLOPT_REFERER, $this->url);
		$this->html = curl_exec($this->ch);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, []);
		curl_setopt($this->ch, CURLOPT_POST, false);
		$this->info = curl_getinfo($this->ch);
		if($this->debug) {
			echo "\n".$this->info['url']."\n\n";
			echo "\n[REQUEST]\n\n";
			echo $this->info['request_header'];
			echo "\n[RESPONSE]\n\n";
			echo $this->info['http_code']." (HTTP STATUS CODE)\n";
			foreach($this->header as $key=>$val) {
				echo "$key: $val\n";
			}
			echo "\n\n(".ceil($this->info['total_time']*1000)." ms total time)\n";
			echo "\n\n--------------------------------------------------------------------\n\n";
		}
		if($this->info['redirect_url']) {
			return $this->navigate($this->info['redirect_url'], $redirects-1);
		}
		$this->url = $this->info['url'];
		$this->dom = null;
	}

	public function post($url, $postfields) {
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
		$this->navigate($url);
	}

	public function enter($name, $value) {
		$field = $this->getDom()->getElementByName($name);
		if(!$field) return false;
		$field['value'] = $value;
		return $field;
	}

	public static function buildUrl($parsed_url) {
		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port/".ltrim($path,'/')."$query$fragment";
	}

	public function click($text) {
		if(!$this->getDom()) return;
		$field = $this->getDom()->getElementByClickableText($text);
		if(!$field) return;
		switch($field->getName()) {
			case 'input':
				$form = $field->getClosestElementByTagName('form');
				if(!$form) return;
				$fields = $form->getFormFieldValues();
				switch(strtolower($form->getAttribute('method'))) {
					case 'post':
						$this->post($form->getAttribute('action'), $fields);
						break;
					case 'get':
					default:
						$url = parse_url($form->getAttribute('action'));
						if(isset($url['query'])) {
							$url['query'] = http_build_query(array_merge(parse_str($url['query']), $fields));
						}
						$url = self::buildUrl($url);
						$this->navigate($url);
						break;
				}
				break;
			case 'a':
				$this->navigate($field->getAttribute('href'));
				break;
		}
	}

	public function getSourceCode() {
		return $this->html;
	}

	public function getUrl() {
		return $this->url;
	}

	public function getInfo() {
		return $this->info;
	}

	public function getDom() {
		if($this->dom) return $this->dom;
		if(!$this->html) return null;
		return $this->dom = BankDom::create($this->html, $this->url);
	}

	public function getHeader() {
		return $this->header;
	}

}
