<?php

require_once('lib/InternetX/AutoDnsClient.php');

class Domain {

	const INVALID = 'invalid';
	const FREE = 'free';
	const TAKEN = 'taken';

	protected $autodns = null;
	protected $domain = null;
	protected $valid = false;
	protected $ownerc = null;

	public function __construct($domain) {
		if(!is_string($domain) || !$domain) Error::fatal('Domain must be a string.');
		$this->domain = strtolower($domain);
		$this->autodns = new AutoDnsClient();
		if(App::isSandboxed()) {
			$this->autodns->setGateway(Config::get('domains_sandbox', 'autodns_gateway'));
			$this->autodns->setUser(Config::get('domains_sandbox', 'autodns_user'));
			$this->autodns->setPassword(Config::get('domains_sandbox', 'autodns_password'));
			$this->autodns->setContext(Config::get('domains_sandbox', 'autodns_context'));
		}else{
			$this->autodns->setGateway(Config::get('domains', 'autodns_gateway'));
			$this->autodns->setUser(Config::get('domains', 'autodns_user'));
			$this->autodns->setPassword(Config::get('domains', 'autodns_password'));
			$this->autodns->setContext(Config::get('domains', 'autodns_context'));
		}
		$this->autodns->setReplyTo(Config::get('domains', 'autodns_reply_to'));
		$this->autodns->setLanguage(App::getLang());
		$this->autodns->setWaitForDomainConnect(false);
		$this->autodns->setZoneIp(Config::get('domains', 'zone_ip'));
		$this->autodns->setZoneMx(Config::get('domains', 'zone_mx'));
	}

	public function overwriteReplyTo($replyto) {
		$this->autodns->setReplyTo($replyto);
	}

	public static function ajax() {
		if(!isset($_POST['action']) || !isset($_POST['domain'])) return false;
		switch($_POST['action']) {
			case 'check':
				$session = App::getSession();
				$cached = $session->get('domain_check');
				$result = $session->get('domain_result');
				if($cached && $cached===$_POST['domain'] && $result) {
					return $result;
				}
				$domain = new self($_POST['domain']);
				$result = '';
				if(!$domain->isValid()) $result = self::INVALID;
				elseif($domain->isFree()) $result = self::FREE;
				else $result = self::TAKEN;
				$session->set('domain_check', $domain);
				$session->set('domain_result', $result);
				return $result;
				break;
		}
	}

	public static function clearSession() {
		$session = App::getSession();
		$session->remove('domain_check');
		$session->remove('domain_result');
	}

	public function getCachedCheckResult() {
		if(!$this->domain) return null;
		$session = App::getSession();
		if($session->get('domain_check')!==$this->domain) return null;
		return $session->get('domain_result');
	}

	protected function getMyHandle() {
		return $this->autodns->createPerson(
			Config::get('legal', 'company_nicename'),
			Config::get('legal', 'person_firstname'),
			Config::get('legal', 'person_lastname'),
			Config::get('legal', 'company_street'),
			Config::get('legal', 'company_pcode'),
			Config::get('legal', 'company_city'),
			Config::get('legal', 'company_countrycode'),
			Config::get('legal', 'company_phone'),
			Config::get('email', 'support_address')
		);
	}

	public function isValid() {
		if($this->valid) return true;
		if(!preg_match('/^[^\.]{3,}\..+$/', $this->domain)) return false;
		list($sld, $tld) = explode('.', $this->domain, 2);
		if(!preg_match('/^[a-z0-9-]+$/', $sld) || substr($sld,0,1)==='-' || substr($sld,-1)==='-' || strstr($sld,'--')) return false;
		$tlds = Cache::code(function(){
			$tlds = strtolower(file_get_contents(Config::get('domains', 'iana_tlds_file')));
			$tlds = preg_replace('/#[^\r\n]+/', '', $tlds);
			$tlds = explode("\n",trim(str_replace("\r\n", "\n", $tlds)));
			return $tlds;
		}, Config::get('domains', 'iana_tlds_max_age'));
		if(!in_array($tld,$tlds)) return false;
		$this->valid = true;
		return true;
	}

	public function isFree() {
		if(!$this->isValid()) return null;
		return $this->autodns->isFree($this->domain);
	}

	public function setOwnerC($organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email) {
		$this->ownerc = $this->autodns->createPerson($organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email);
	}

	public function register() {
		if(!$this->ownerc) {
			Error::warning('OwnerC information missing.');
			return false;
		}
		if(!$this->isValid()) {
			Error::warning('Trying to register invalid domain.');
			return false;
		}
		$techc = $this->getMyHandle();
		return $this->autodns->registerDomain(
			$this->domain,
			Config::get(App::isSandboxed() ? 'domains_sandbox' : 'domains', 'nameservers'),
			$this->ownerc,
			$techc,
			$techc,
			$techc
		);
	}

	public function transfer($authcode=null) {
		if(!$this->ownerc) {
			Error::warning('OwnerC information missing.');
			return false;
		}
		if(!$this->isValid()) {
			Error::warning('Trying to transfer invalid domain.');
			return false;
		}
		if($authcode) $this->autodns->setAuthInfo($this->domain,$authcode);
		$techc = $this->getMyHandle();
		return $this->autodns->transferDomain(
			$this->domain,
			Config::get(App::isSandboxed() ? 'domains_sandbox' : 'domains', 'nameservers'),
			$this->ownerc,
			$techc,
			$techc,
			$techc
		);
	}

	public function getLastError() {
		return $this->autodns->getLastError();
	}

	public function __toString() {
		return $this->domain;
	}

}
