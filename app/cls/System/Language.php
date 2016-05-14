<?php

class Language {

	protected $autoappend = false;
	protected $autoappend_added = array();
	protected $base = '';
	protected $lang = '';
	protected $strings = array();
	protected $loaded = false;
	protected $supported = array();
	protected $filehandle = null;
	protected $number_dec_point = '.';
	protected $number_thsd_sep = '';
	protected $currency_prefix = '';
	protected $currency_suffix = '';
	protected $currency_decimals = 2;

	public function setAutoappend($autoappend=true) {
		$this->autoappend = (bool) $autoappend;
	}

	public function setBase($lang) {
		$this->base = $lang;
	}

	public function setLanguage($lang) {
		if(!$this->isSupported($lang)) return false;
		$this->lang = $lang;
		$dec_point = Config::get('lang', 'number_dec_point');
		if(is_array($dec_point)) {
			$dec_point = isset($dec_point[$this->lang]) ? $dec_point[$this->lang] : '.';
		}elseif(!$dec_point) {
			$dec_point = '.';
		}
		$this->number_dec_point = $dec_point;
		$separator = Config::get('lang', 'number_thsd_sep');
		if(is_array($separator)) {
			$separator = isset($separator[$this->lang]) ? $separator[$this->lang] : '';
		}elseif(!$separator){
			$separator = '';
		}
		$this->number_thsd_sep = $separator;
		$prefix = Config::get('lang', 'currency_prefix');
		if(is_array($prefix) && isset($prefix[$this->lang])) {
			$prefix = $prefix[$this->lang];
		}
		$this->currency_prefix = $prefix;
		$suffix = Config::get('lang', 'currency_suffix');
		if(is_array($suffix) && isset($suffix[$this->lang])) {
			$suffix = $suffix[$this->lang];
		}
		$this->currency_suffix = $suffix;
		$decimals = Config::get('lang','currency_decimals');
		if(is_array($decimals) && isset($decimals[$this->lang])) {
			$decimals = $decimals[$this->lang];
		}
		$this->currency_decimals = $decimals;
		return true;
	}

	public function getLangString() {
		return $this->lang;
	}

	public function isSupported($lang) {
		$lang_dir = rtrim(Config::get('dirs', 'lang'),'/').'/';
		return $lang===$this->base || file_exists($lang_dir.$lang.'.csv');
	}

	public function getSupportedLanguages() {
		if($this->supported) return $this->supported;
		if(!$this->base) {
			Error::warning('Language system not yet loaded.');
			return array();
		}
		$lang_dir = rtrim(Config::get('dirs', 'lang'),'/').'/';
		$supported = array($this->base => true);
		foreach(glob($lang_dir.'*.csv') as $path) {
			if(preg_match('@^'.preg_quote($lang_dir,'@').'([a-z]{2})\.csv$@',$path,$matches)) $supported[$matches[1]] = true;
		}
		$this->supported = array_keys($supported);
		return $this->supported;
	}

	protected function load() {
		if($this->loaded) return;
		$lang_dir = rtrim(Config::get('dirs', 'lang'),'/').'/';
		$filename = $lang_dir.$this->lang.'.csv';
		$cachefile = "lang.{$this->lang}.private.phpdata";
		if(Cache::exists($cachefile) && !Cache::isOutdated($cachefile,$filename)) {
			$this->strings = Cache::readFile($cachefile,true);
			$this->loaded = true;
			return;
		}
		if(!file_exists($filename)) Error::fatal("Language file not found: $filename");
		if($this->autoappend) {
			$this->filehandle = fopen($filename,'a+');
			fseek($this->filehandle,-1,SEEK_END);
			if(fread($this->filehandle,1)!=="\n") fwrite($this->filehandle,"\n",1);
			rewind($this->filehandle);
		}else{
			$this->filehandle = fopen($filename,'r');
		}
		while(!feof($this->filehandle)) {
			$csv = fgetcsv($this->filehandle,null,"\t",'"');
			if(!$csv[0]) continue;
			$this->strings[$csv[0]] = $csv[1];
		}
		$this->loaded = true;
		Cache::writeFile($cachefile,$this->strings,true);
	}

	public function translateString($str) {
		if($this->lang===$this->base) return $str;
		$this->load();
		if(isset($this->strings[$str])) return $this->strings[$str];
		if($this->autoappend) {
			$lang_dir = rtrim(Config::get('dirs', 'lang'),'/').'/';
			if(!$this->filehandle) $this->filehandle = fopen($lang_dir.$this->lang.'.csv',$this->autoappend ? 'a+' : 'r');
			if(!isset($this->autoappend_added[$str])) {
				fputcsv($this->filehandle,array($str,$str),"\t",'"');
				$this->autoappend_added[$str] = true;
			}
		}
		return $str;
	}

	public function translateHtml($html,$prefix='{{',$suffix='}}') {
		$parts = preg_split('/('.preg_quote($prefix,'/').'(.*?)'.preg_quote($suffix,'/').')/',$html,null,PREG_SPLIT_DELIM_CAPTURE);
		$html = '';
		for($i=0; $i<count($parts); $i++) {
			if(substr($parts[$i],0,strlen($prefix))===$prefix && substr($parts[$i],-strlen($suffix))===$suffix) {
				$i++;
				$html.= $this->translateString($parts[$i]);
			}else{
				$html.= $parts[$i];
			}
		}
		return $html;
	}

	public function number($number, $decimals=0, $separator=true) {
		if(!is_numeric($number) || !$this->lang) return $number;
		return number_format($number, $decimals, $this->number_dec_point, $separator ? $this->number_thsd_sep : '');
	}

	public function currency($number, $simple=false) {
		if(!is_numeric($number) || !$this->lang) return $number;
		$number = trim($this->currency_prefix.' '.$this->number($number, $this->currency_decimals, true).' '.$this->currency_suffix);
		if($simple) $number = preg_replace('@'.preg_quote($this->number_dec_point).'0+$@','',$number);
		return $number;
	}

	public function __toString() {
		return $this->getLangString();
	}

	public static function getNativeName($lang) {
		$lang = (string) $lang;
		$names = array(
		'ab' => 'аҧсуа бызшәа, аҧсшәа',
		'aa' => 'Afaraf',
		'af' => 'Afrikaans',
		'ak' => 'Akan',
		'sq' => 'Shqip',
		'am' => 'አማርኛ',
		'ar' => 'العربية',
		'an' => 'aragonés',
		'hy' => 'Հայերեն',
		'as' => 'অসমীয়া',
		'av' => 'авар мацӀ, магӀарул мацӀ',
		'ae' => 'avesta',
		'ay' => 'aymar aru',
		'az' => 'azərbaycan dili',
		'bm' => 'bamanankan',
		'ba' => 'башҡорт теле',
		'eu' => 'euskara, euskera',
		'be' => 'беларуская мова',
		'bn' => 'বাংলা',
		'bh' => 'भोजपुरी',
		'bi' => 'Bislama',
		'bs' => 'bosanski jezik',
		'br' => 'brezhoneg',
		'bg' => 'български език',
		'my' => 'ဗမာစာ',
		'ca' => 'català',
		'ch' => 'Chamoru',
		'ce' => 'нохчийн мотт',
		'ny' => 'chiCheŵa, chinyanja',
		'zh' => '中文 (Zhōngwén), 汉语, 漢語',
		'cv' => 'чӑваш чӗлхи',
		'kw' => 'Kernewek',
		'co' => 'corsu, lingua corsa',
		'cr' => 'ᓀᐦᐃᔭᐍᐏᐣ',
		'hr' => 'hrvatski jezik',
		'cs' => 'čeština, český jazyk',
		'da' => 'dansk',
		'dv' => 'ދިވެހި',
		'nl' => 'Nederlands, Vlaams',
		'dz' => 'རྫོང་ཁ',
		'en' => 'English',
		'eo' => 'Esperanto',
		'et' => 'eesti, eesti keel',
		'ee' => 'Eʋegbe',
		'fo' => 'føroyskt',
		'fj' => 'vosa Vakaviti',
		'fi' => 'suomi, suomen kieli',
		'fr' => 'français, langue française',
		'ff' => 'Fulfulde, Pulaar, Pular',
		'gl' => 'galego',
		'ka' => 'ქართული',
		'de' => 'Deutsch',
		'el' => 'ελληνικά',
		'gn' => 'Avañe\'ẽ',
		'gu' => 'ગુજરાતી',
		'ht' => 'Kreyòl ayisyen',
		'ha' => '(Hausa) هَوُسَ',
		'he' => 'עברית',
		'hz' => 'Otjiherero',
		'hi' => 'हिन्दी, हिंदी',
		'ho' => 'Hiri Motu',
		'hu' => 'magyar',
		'ia' => 'Interlingua',
		'id' => 'Bahasa Indonesia',
		'ie' => 'Interlingue',
		'ga' => 'Gaeilge',
		'ig' => 'Asụsụ Igbo',
		'ik' => 'Iñupiaq, Iñupiatun',
		'io' => 'Ido',
		'is' => 'Íslenska',
		'it' => 'italiano',
		'iu' => 'ᐃᓄᒃᑎᑐᑦ',
		'ja' => '日本語 (にほんご)',
		'jv' => 'basa Jawa',
		'kl' => 'kalaallisut, kalaallit oqaasii',
		'kn' => 'ಕನ್ನಡ',
		'kr' => 'Kanuri',
		'ks' => 'कश्मीरी, كشميري‎',
		'kk' => 'қазақ тілі',
		'km' => 'ខ្មែរ, ខេមរភាសា, ភាសាខ្មែរ',
		'ki' => 'Gĩkũyũ',
		'rw' => 'Ikinyarwanda',
		'ky' => 'Кыргызча, Кыргыз тили',
		'kv' => 'коми кыв',
		'kg' => 'Kikongo',
		'ko' => '한국어, 조선어',
		'ku' => 'Kurdî, كوردی‎',
		'kj' => 'Kuanyama',
		'la' => 'latine, lingua latina',
		'lb' => 'Lëtzebuergesch',
		'lg' => 'Luganda',
		'li' => 'Limburgs',
		'ln' => 'Lingála',
		'lo' => 'ພາສາລາວ',
		'lt' => 'lietuvių kalba',
		'lu' => 'Tshiluba',
		'lv' => 'latviešu valoda',
		'gv' => 'Gaelg, Gailck',
		'mk' => 'македонски јазик',
		'mg' => 'fiteny malagasy',
		'ms' => 'bahasa Melayu, بهاس ملايو‎',
		'ml' => 'മലയാളം',
		'mt' => 'Malti',
		'mi' => 'te reo Māori',
		'mr' => 'मराठी',
		'mh' => 'Kajin M̧ajeļ',
		'mn' => 'Монгол хэл',
		'na' => 'Dorerin Naoero',
		'nv' => 'Diné bizaad',
		'nd' => 'isiNdebele',
		'ne' => 'नेपाली',
		'ng' => 'Owambo',
		'nb' => 'Norsk bokmål',
		'nn' => 'Norsk nynorsk',
		'no' => 'Norsk',
		'ii' => 'ꆈꌠ꒿ Nuosuhxop',
		'nr' => 'isiNdebele',
		'oc' => 'occitan, lenga d\'òc',
		'oj' => 'ᐊᓂᔑᓈᐯᒧᐎᓐ',
		'cu' => 'ѩзыкъ словѣньскъ',
		'om' => 'Afaan Oromoo',
		'or' => 'ଓଡ଼ିଆ',
		'os' => 'ирон æвзаг',
		'pa' => 'ਪੰਜਾਬੀ, پنجابی‎',
		'pi' => 'पाऴि',
		'fa' => 'فارسی',
		'pl' => 'język polski, polszczyzna',
		'ps' => 'پښتو',
		'pt' => 'português',
		'qu' => 'Runa Simi, Kichwa',
		'rm' => 'rumantsch grischun',
		'rn' => 'Ikirundi',
		'ro' => 'limba română',
		'ru' => 'Русский',
		'sa' => 'संस्कृतम्',
		'sc' => 'sardu',
		'sd' => 'सिन्धी, سنڌي، سندھی‎',
		'se' => 'Davvisámegiella',
		'sm' => 'gagana fa\'a Samoa',
		'sg' => 'yângâ tî sängö',
		'sr' => 'српски језик',
		'gd' => 'Gàidhlig',
		'sn' => 'chiShona',
		'si' => 'සිංහල',
		'sk' => 'slovenčina, slovenský jazyk',
		'sl' => 'slovenski jezik, slovenščina',
		'so' => 'Soomaaliga, af Soomaali',
		'st' => 'Sesotho',
		'es' => 'español',
		'su' => 'Basa Sunda',
		'sw' => 'Kiswahili',
		'ss' => 'SiSwati',
		'sv' => 'svenska',
		'ta' => 'தமிழ்',
		'te' => 'తెలుగు',
		'tg' => 'тоҷикӣ, toçikī, تاجیکی‎',
		'th' => 'ไทย',
		'ti' => 'ትግርኛ',
		'bo' => 'བོད་ཡིག',
		'tk' => 'Türkmen, Түркмен',
		'tl' => 'Wikang Tagalog, ᜏᜒᜃᜅ᜔ ᜆᜄᜎᜓᜄ᜔',
		'tn' => 'Setswana',
		'to' => 'faka Tonga',
		'tr' => 'Türkçe',
		'ts' => 'Xitsonga',
		'tt' => 'татар теле, tatar tele',
		'tw' => 'Twi',
		'ty' => 'Reo Tahiti',
		'ug' => 'ئۇيغۇرچە‎, Uyghurche',
		'uk' => 'Українська',
		'ur' => 'اردو',
		'uz' => 'Oʻzbek, Ўзбек, أۇزبېك‎',
		've' => 'Tshivenḓa',
		'vi' => 'Tiếng Việt',
		'vo' => 'Volapük',
		'wa' => 'walon',
		'cy' => 'Cymraeg',
		'wo' => 'Wollof',
		'fy' => 'Frysk',
		'xh' => 'isiXhosa',
		'yi' => 'ייִדיש',
		'yo' => 'Yorùbá',
		'za' => 'Saɯ cueŋƅ, Saw cuengh'
		);
		if(isset($names[$lang])) return $names[$lang];
		Error::warning('Language not recognized: '.$lang);
		return null;
	}

}
