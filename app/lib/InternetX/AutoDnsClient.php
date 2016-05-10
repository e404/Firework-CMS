<?php

class AutoDnsClient {

	protected $gatewayUrl = 'https://gateway.autodns.com/';

	private $authUser = '';
	private $authPass = '';
	private $authContext = 1;
	private $replyTo = '';

	protected $authinfo = array();
	protected $zoneIp = null;
	protected $zoneMx = null;
	protected $language = 'en';
	protected $lastError = null;
	protected $waitForDomainConnect = false;

	protected function talk($xmlData) {
		$curl = curl_init($this->gatewayUrl);
		curl_setopt($curl,CURLOPT_POSTFIELDS,$xmlData);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($curl,CURLOPT_TIMEOUT,20);
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,15);
		$data = @curl_exec($curl);
		return $data ? $data : false;
	}

	protected function assembleXml($taskXml) {
		$xml = '<?xml version="1.0" encoding="utf-8"?>'."\n".
			'<request>'.
				'<auth>'.
					'<user>'.$this->authUser.'</user>'.
					'<password>'.$this->authPass.'</password>'.
					'<context>'.$this->authContext.'</context>'.
				'</auth>'.
				'<language>'.$this->language."</language>\n".
				$taskXml.
			'</request>';
		return $xml;
	}

	protected function performTask($taskXml) {
		if(is_array($taskXml)) {
			$taskXml = $this->convertTaskArrayToXml($taskXml);
		}
		$xml = $this->assembleXml($taskXml);
		$response = $this->talk($xml);
		return $response;
	}

	protected function convertTaskArrayToXml($array) {
		$taskXmlObj = new SimpleXMLElement('<task/>');
		self::walkArrayXmlRecursive($array,$taskXmlObj);
		return preg_replace("/^[^\n]+\n/",'',$taskXmlObj->asXML());
	}

	final private static function walkArrayXmlRecursive($array,&$xml) {
		foreach($array as $key => $value) {
			if(is_array($value)) {
				if(!is_numeric($key)) {
					$subnode = $xml->addChild((string)$key);
					self::walkArrayXmlRecursive($value,$subnode);
				}else{
					self::walkArrayXmlRecursive($value,$xml);
				}
			}else{
				$xml->addChild((string)$key,(string)$value);
			}
		}
	}

	protected function getUniqueId() {
		return sha1(uniqid(serialize($_SERVER),true));
	}

	protected function getNameserverArray($nsa) {
		$a = array();
		foreach($nsa as $ns) {
			$a[] = array(
				'nserver' => array(
					'name' => $ns,
					'ip' => gethostbyname($ns)
				)
			);
		}
		return $a;
	}

	const HANDLE_TYPE_PERSON = 'PERSON';
	const HANDLE_TYPE_ORG = 'ORG';
	const HANDLE_TYPE_ROLE = 'ROLE';
	const TASK_DOMAIN_CREATE = '0101';
	const TASK_DOMAIN_UPDATE = '0102';
	const TASK_DOMAIN_DELETE = '0103101';
	const TASK_DOMAIN_TRANSFER = '0104';
	const TASK_ZONE_CREATE = '0201';

	public function setGateway($gateway) { $this->gatewayUrl = $gateway; }
	public function setUser($user) { $this->authUser=$user; }
	public function setPassword($pass) { $this->authPass=$pass; }
	public function setContext($context) { $this->authContext=$context; }
	public function setReplyTo($replyTo) { $this->replyTo=$replyTo; }

	protected function createHandle($type,$organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email,$protected=true) {
		return array(
			'type' => $type,
			'fname' => $fname,
			'lname' => $lname,
			'organization' => $organization,
			'address' => $address,
			'pcode' => $pcode,
			'city' => $city,
			'country' => strtoupper(substr($country,0,2)),
			'phone' => preg_replace('/^([0-9]{2})([0-9])([0-9]+)$/','+$1-$2-$3',preg_replace('/[^0-9]/','',$phone)),
			'email' => $email,
			'protection' => $protected ? 'b' : 'a'
		);
	}

	public function createNullHandle() {
		return $this->createPerson(null,null,null,null,null,null,null,null,null);
	}

	public function createPerson($organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email) {
		return $this->createHandle(self::HANDLE_TYPE_PERSON,$organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email);
	}

	public function createOrganization($organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email) {
		return $this->createHandle(self::HANDLE_TYPE_ORG,$organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email);
	}

	public function createRole($organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email) {
		return $this->createHandle(self::HANDLE_TYPE_ROLE,$organization,$fname,$lname,$address,$pcode,$city,$country,$phone,$email);
	}

	protected function domainOperation($task,$domain,$nameservers=null,$ownerc=null,$adminc=null,$techc=null,$zonec=null) {
		$this->lastError = null;
		$exectime = time();
		if($task===self::TASK_DOMAIN_DELETE) {
			$task = $this->performTask(array(
				'code' => self::TASK_DOMAIN_DELETE,
				'cancelation' => array(
					array(
						'domain' => $domain,
						'type' => 'delete',
						'execdate' => 'now'
					)
				),
				'reply_to' => $this->replyTo
			));
		}else{
			if(!$nameservers || !$ownerc) return false;
			$task = $this->performTask(array(
				'code' => $task,
				'domain' => array(
					array(
						'name' => $domain,
						'ownerc' => $ownerc,
						'adminc' => $adminc ? $adminc : $ownerc,
						'techc' => $techc ? $techc : $ownerc,
						'zonec' => $zonec ? $zonec : $ownerc
					),
					$this->getNameserverArray($nameservers),
					'authinfo' => isset($this->authinfo[$domain]) ? $this->authinfo[$domain] : '',
					'zone' => $this->zoneIp ? array(
						'ip' => $this->zoneIp,
						'mx' => $this->zoneMx ? $this->zoneMx : '',
						'ns_action' => 'complete',
						'www_include' => true
					) : ''
				),
				'reply_to' => $this->replyTo
			));
		}
		if(strstr($task,'<code>S')) return true;
		if(strstr($task,'<code>EF01021</code>') || strstr($task,'<code>EF01013</code>')) {
			$this->lastError = $task;
			return 0;
		}
		if(strstr($task,'<code>E')) {
			$this->lastError = $task;
			return false;
		}
		if(!$this->waitForDomainConnect || $task===self::TASK_DOMAIN_TRANSFER) return true;
		$i = 0;
		do {
			$i++;
			if($i>6) return null;
			sleep(10);
		}while(!$this->isSuccessfullyProcessed($domain,$exectime));
		return true;
	}

	public function setAuthInfo($domain,$authcode) {
		$this->authinfo[$domain] = htmlspecialchars($authcode);
	}

	public function transferDomain($domain,$nameservers,$ownerc,$adminc=null,$techc=null,$zonec=null) {
		return $this->domainOperation(self::TASK_DOMAIN_TRANSFER,$domain,$nameservers,$ownerc,$adminc,$techc,$zonec);
	}

	public function registerDomain($domain,$nameservers,$ownerc,$adminc=null,$techc=null,$zonec=null) {
		return $this->domainOperation(self::TASK_DOMAIN_CREATE,$domain,$nameservers,$ownerc,$adminc,$techc,$zonec);
	}

	public function updateDomain($domain,$nameservers,$ownerc,$adminc=null,$techc=null,$zonec=null) {
		return $this->domainOperation(self::TASK_DOMAIN_UPDATE,$domain,$nameservers,$ownerc,$adminc,$techc,$zonec);
	}

	public function deleteDomain($domain) {
		return $this->domainOperation(self::TASK_DOMAIN_DELETE,$domain);
	}

	public function isFree($domain) {
		$code = $this->registerDomain($domain,array('localhost'),$this->createNullHandle());
		return $code===0 ? false : true;
	}

	public function isSuccessfullyProcessed($domain,$exectimeOffset=null) {
		$task = $this->performTask(array(
			'code' => '0713',
			'view' => array(
				'limit' => 1
			),
			'where' => array(
				array(
					'and' => array(
						'key' => 'object',
						'operator' => 'eq',
						'value' => $domain
					)
				),
				$exectime ? array(
					'and' => array(
						'key' => 'created',
						'operator' => 'ge',
						'value' => date('Y-m-d H:i:s',$exectimeOffset)
					)
				) : array()
			),
			'order' => array(
				'key' => 'created',
				'mode' => 'desc'
			)
		));
		return (bool) strstr(strtolower($task),'<status>success</status>');
	}

	public function setLanguage($language) {
		$this->language = $language;
	}

	public function setWaitForDomainConnect($wait) {
		$this->waitForDomainConnect = (bool) $wait;
	}

	public function getLastError() {
		return $this->lastError;
	}

	public function setZoneIp($ip) {
		$this->zoneIp = $ip;
	}

	public function setZoneMx($mx) {
		$this->zoneMx = $mx;
	}

}

?>