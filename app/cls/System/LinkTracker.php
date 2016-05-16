<?php

class LinkTracker extends Db {

	protected $lifetime_days = 14;
	protected $url = null;
	protected $desc = null;
	protected $context = null;
	protected $value = null;
	protected $pretty_filename = null;

	public function setLifetime($days) {
		$this->lifetime_days = $days;
	}

	public function setUrl($url) {
		$this->url = $url;
	}

	public function setDesc($desc) {
		$this->desc = $desc;
	}

	public function setContextValue($context, $value) {
		$this->context = $context;
		$this->value = is_string($value) ? $value : json_encode($value);
	}

	public function setPrettyFilename($pretty_filename) {
		$this->pretty_filename = $filename;
	}

	public function getLink() {
		if(!$this->url) return Error::fatal('URL not set.');
		if(!$this->desc) return Error::fatal('Description not set.');
		do {
			$id = Random::generate(8);
		} while(self::$db->single("SELECT id FROM links WHERE id='$id' LIMIT 1"));
		$expires = $this->lifetime_days ? date('Y-m-d H:i:s', time()+$this->lifetime_days*3600*24) : null;
		$query = self::$db->prepare("INSERT INTO `links` SET `id`=@VAL, `url`=@VAL, `desc`=@VAL, `context`=@VAL, `value`=@VAL, `expires`=@VAL", $id, $this->url, $this->desc, $this->context, $this->value, $expires);
		if(self::$db->query($query)) {
			return App::getLink("link/$id".($this->pretty_filename ? '/'.$this->pretty_filename : ''));
		}
		return false;
	}

	public static function action(string $id) {
		$link = self::$db->getRow(self::$db->prepare("SELECT `url`, `context`, `value` FROM `links` WHERE `id`=@VAL LIMIT 1", $id));
		if(!$link['url']) App::redirect(404);
		App::getSession()->set('link_id', $id);
		if($link['context']) {
			App::getSession()->set('link_context', $link['context']);
			if($link['value']) App::getSession()->set('link_value', $link['value']);
		}
		self::$db->query(self::$db->prepare("UPDATE `links` SET `t_action`=NOW(), `actions_count`=`actions_count`+1 WHERE `id`=@VAL LIMIT 1", $id));
		App::redirect($link['url'],true);
	}

	public static function getContextValue() {
		return array(
			'context' => App::getSession()->get('link_context'),
			'value' => App::getSession()->get('link_value')
		);
	}

	public static function purge($link_id=null) { // if $link_id is null, it will be taken from sessionstore
		if(!$link_id) {
			$link_id = App::getSession()->get('link_id');
			App::getSession()->remove('link_id');
			App::getSession()->remove('link_context');
			App::getSession()->remove('link_value');
		}
		if(!$link_id || !self::$db->single(self::$db->prepare("SELECT id FROM `links` WHERE id=@VAL LIMIT 1", $link_id))) return false;
		return self::$db->query(self::$db->prepare("DELETE FROM `links` WHERE id=@VAL LIMIT 1", $link_id));
	}

}
