<?php

require_once('lib/InternetX/AutoDnsClient.php');

class Install extends AbstractDbRecord {

	public static function ajax() {
		if(!isset($_POST['action'])) return;
		switch($_POST['action']) {
			case 'get_status_active':
				if(!isset($_POST['uid'])) return;
				$user = new User($_POST['uid']);
				if(!$user->exists()) return;
				return $user->getField('active');
				break;
		}
	}

	protected function getTable() {
		return 'install';
	}

	protected function getPrimaryKey() {
		return 'uid';
	}

	public static function createNew(User $user) {

		$uid = $user->getId();
		if(!$uid) {
			Error::fatal('Install failed: User not found.');
		}
		$unix_username = Config::get('install', 'unix_user_prefix').$uid.Config::get('install', 'unix_user_suffix');

		$domain = new Domain($user->getField('domain'));
		$domain_mode = $user->getField('domain_mode');
		$domain_authcode = $user->getField('domain_authcode');

		// Register domain
		if($domain_mode==='none') {
			$user->setField('status_domain',2);
			$user->save();
			App::executeHooks('domain-external', array(
				'user_obj' => $user,
				'domain' => (string) $domain
			));
		}else{
			$domain->setOwnerC(
				$user->getField('company'),
				$user->getField('firstname'),
				$user->getField('lastname'),
				$user->getField('street'),
				$user->getField('postalcode'),
				$user->getField('city'),
				$user->getField('country'),
				$user->getField('phone'),
				$user->getField('businessemail')
			);
			if($domain_mode==='transfer') {
				if(!$domain->transfer($domain_authcode)) {
					Error::warning('Domain transfer error. '.$domain->getLastError());
				}
			}else{
				if(!$domain->register()) {
					Error::warning('Domain registration error. '.$domain->getLastError());
				}
			}
		}

		// Let cron (root) trigger the installation process
		$install = self::newInstance();
		$install->setField('uid', $uid);
		$install->setField('user', $unix_username);
		$install->setField('domain', $domain);
		$install->setField('sandbox', App::isSandboxed() ? '1' : '0');
		$install->save(true);

	}

	public static function runCron() {

		// Note: This method is called as superuser (root) via CLI

		// Get DB object and query all install entries
		$db = MysqlDb::getInstance();
		$install = $db->query("SELECT * FROM `install`");

		// Quit if there is nothing to do
		if(!$install) return null;

		$rootdb_pass = @shell_exec('cat /root/.my.cnf');
		if(!preg_match('/password=([^\s]+)/', $rootdb_pass, $matches)) {
			Error::fatal('No root DB password found (.my.cnf)');
		}
		$rootdb_pass = $matches[1];
		$rootdb = new MysqlDb();
		$rootdb->connect('localhost','root',$rootdb_pass);
		unset($matches);
		unset($rootdb_pass);

		$rootdb->ignoreLongQueries();

		$quota = Config::get('install', 'quota_gb');

		// Loop through every install entry to complete the installation
		foreach($install as $info) {

			App::setSandboxed($info['sandbox']==='1');

			$uid = $info['uid'];
			$user = new User($uid);
			if(!$user->exists()) {
				Error::warning('User not found, skipping: '.$uid);
				continue;
			}

			// Prevent double execution by instantly deleting DB entry
			$db->query($db->prepare("DELETE FROM `install` WHERE `uid`=@VAL LIMIT 1", $uid));

			// Set variables
			$domain = $info['domain'];
			list($sld, $tld) = explode('.', $domain, 2);
			$unix_username = $info['user'];
			$demo_dir = rtrim(Config::get('install', 'demo_dir'), '/');
			$wpcfg_file = Config::get('install', 'users_base_prefix').$unix_username.Config::get('install', 'users_base_suffix').$domain.'/wp-config.php';
			$user_dir = "/usr/www/$unix_username/public/$domain";

			// This is for parsed credentials
			$credentials = array(
				'domain' => $domain,
				'username' => $unix_username
			);

			// Create new unix user
			$log = shell_exec("/root/webuser $unix_username");
			if(!preg_match('/Password for [^ ]+ set to: ([^\s]+)/',$log,$matches)) Error::fatal('User creation failed: '.$unix_username);
			$credentials['password'] = $matches[1];

			// Create new virtual host
			shell_exec("/root/virtualhost $unix_username $domain");

			// Create new database
			$log = shell_exec("/root/webdb $unix_username wp");
			if(!preg_match('/Database: ([^\s]+)/',$log,$matches)) Error::fatal('Database creation failed: '.$unix_username);
			$credentials['db_name'] = $matches[1];
			if(!preg_match('/Username: ([^\s]+)/',$log,$matches)) Error::fatal('Database creation failed: '.$unix_username);
			$credentials['db_user'] = $matches[1];
			if(!preg_match('/Password: ([^\s]+)/',$log,$matches)) Error::fatal('Database creation failed: '.$unix_username);
			$credentials['db_pass'] = $matches[1];

			// Set quota
			shell_exec("/root/setquota $unix_username ".round($quota*1024));

			// Copy WordPress from demo installation
			shell_exec("rsync -a --exclude='wp-config.php' $demo_dir/ $user_dir/");
			shell_exec("chown -R www-data:$unix_username $user_dir/");
			shell_exec("chmod -R 777 $user_dir/");

			// Copy database tables including content
			$demo_db = Config::get('install', 'demo_db');
			$user_db = $credentials['db_name'];
			shell_exec("mysqldump $demo_db | mysql $user_db");

			// Generate WP Admin
			$credentials['wp_url'] = 'http://www.'.$domain;
			$credentials['wp_user'] = $sld.'_admin';
			$credentials['wp_pass'] = Random::generate(8);
			$wp_pass_hash = md5($credentials['wp_pass']);
			$rootdb->query($rootdb->prepare("TRUNCATE TABLE @VAR.wp_users", $credentials['db_name']));
			$rootdb->query($rootdb->prepare(
				"INSERT INTO @VAR.wp_users SET ID=1, user_login=@VAL, ".
				"user_pass=@VAL, user_nicename=@VAL, ".
				"user_email=@VAL, user_registered=NOW(), ".
				"display_name=@VAL",
				$credentials['db_name'],
				$credentials['wp_user'],
				$wp_pass_hash,
				$user->getField('firstname'),
				$user->getField('email'),
				$user->getField('firstname').' '.$user->getField('lastname')
			));

			// Create mail accounts
			$domain_id = $rootdb->getId($rootdb->prepare("INSERT INTO sys_mailserver.virtual_domains SET `name`=@VAL, `webuser`=@VAL", $domain, $unix_username));
			$credentials['email_email'] = $user->getField('businessemail');
			$credentials['email_pass'] = Random::generate(10);
			$credentials['bounce_email'] = Config::get('install', 'bounce_email_user').'@'.$domain;
			$credentials['bounce_pass'] = Random::generate(10);
			$rootdb->query($rootdb->prepare("INSERT INTO sys_mailserver.virtual_users SET `domain_id`=@VAL, `password`=@VAL, `email`=@VAL", $domain_id, md5($credentials['email_pass']), $credentials['email_email']));
			$rootdb->query($rootdb->prepare("INSERT INTO sys_mailserver.virtual_users SET `domain_id`=@VAL, `password`=@VAL, `email`=@VAL, `autodeleteall`=1", $domain_id, md5($credentials['bounce_pass']), $credentials['bounce_email']));

			// Modify database content
			// Delete all user metadata except of admin
			$rootdb->query($rootdb->prepare("DELETE FROM @VAR.wp_usermeta WHERE user_id!=1 OR meta_key IN ('session_tokens', 'wp_dashboard_quick_press_last_post_id')", $credentials['db_name']));
			// Change admin names
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_usermeta SET meta_value=@VAL WHERE meta_key IN ('nickname', 'first_name')", $credentials['db_name'], $user->getField('firstname')));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_usermeta SET meta_value=@VAL WHERE meta_key='last_name' LIMIT 1", $credentials['db_name'], $user->getField('lastname')));
			// Delete all posts except of published and drafts
			$rootdb->query($rootdb->prepare("DELETE FROM @VAR.wp_posts WHERE post_status NOT IN ('publish','draft')", $credentials['db_name']));
			$rootdb->query($rootdb->prepare("SELECT * FROM @VAR.wp_commentmeta WHERE comment_id NOT IN (SELECT comment_id FROM @VAR.wp_comments)", $credentials['db_name'], $credentials['db_name']));
			$delete_postmeta = array_column($rootdb->query($rootdb->prepare("SELECT meta_id FROM @VAR.wp_postmeta pm LEFT JOIN @VAR.wp_posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL", $credentials['db_name'], $credentials['db_name'])), 'meta_id');
			$rootdb->query($rootdb->prepare("DELETE FROM @VAR.wp_postmeta WHERE meta_id IN (".implode(',',$delete_postmeta).")", $credentials['db_name']));
			// Change URLs
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=@VAL WHERE option_name IN ('siteurl', 'home', 'wpmlactivateredirecturl')", $credentials['db_name'], $credentials['wp_url']));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=REPLACE(option_value,'www.100bws.com/demo/de',@VAL) WHERE option_value LIKE '%www.100bws.com/demo/de%' AND option_value NOT LIKE 'a:%'", $credentials['db_name'], 'www.'.$domain));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_posts SET guid=REPLACE(guid,'www.100bws.com/demo/de',@VAL)", $credentials['db_name'], 'www.'.$domain));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_postmeta SET meta_value=REPLACE(meta_value,'www.100bws.com/demo/de',@VAL) WHERE meta_value LIKE '%www.100bws.com/demo/de%' AND meta_value NOT LIKE 'a:%'", $credentials['db_name'], 'www.'.$domain));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_wpmlthemes SET `content`=REPLACE(`content`,'www.100bws.com/demo/de',@VAL), `created`=NOW(), `modified`=NOW()", $credentials['db_name'], 'www.'.$domain));
			// Change email addresses
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_posts SET post_content=REPLACE(post_content,'support@100bws.com',@VAL) WHERE post_content LIKE '%support@100bws.com%'", $credentials['db_name'], $credentials['email_email']));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=REPLACE(option_value,'support@100bws.com',@VAL) WHERE option_value LIKE '%support@100bws.com%' AND option_value NOT LIKE 'a:%'", $credentials['db_name'], $credentials['email_email']));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=@VAL WHERE option_name='wpmlsmtppass' LIMIT 1", $credentials['db_name'], $credentials['email_pass']));
			// Change bounce address
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=@VAL WHERE option_name IN ('wpmlbounceemail', 'wpmlbouncepop_user')", $credentials['db_name'], $credentials['bounce_email']));
			// Change bounce password
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=@VAL WHERE option_name='wpmlbouncepop_pass' LIMIT 1", $credentials['db_name'], $credentials['bounce_pass']));
			// Generate new newsletter API key
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=@VAL WHERE option_name='wpmlapi_key' LIMIT 1", $credentials['db_name'], strtoupper(md5(uniqid(Random::generate(16),true)))));
			// Change dates to now
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_posts SET post_date=NOW(), post_date_gmt=UTC_TIMESTAMP(), post_modified=NOW(), post_modified_gmt=UTC_TIMESTAMP()", $credentials['db_name']));
			// Change site name
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value=@VAL WHERE option_name IN ('blogname', 'wpmlsmtpfromname', 'wpmloffsitetitle', 'woocommerce_email_from_name')", $credentials['db_name'], $user->getField('sitename')));
			$rootdb->query($rootdb->prepare("UPDATE @VAR.wp_options SET option_value='' WHERE option_name='blogdescription' LIMIT 1", $credentials['db_name']));
			// Remove subscribers
			$rootdb->query($rootdb->prepare("TRUNCATE TABLE @VAR.wp_wpmlunsubscribes", $credentials['db_name']));
			$rootdb->query($rootdb->prepare("TRUNCATE TABLE @VAR.wp_wpmlsubscriberslists", $credentials['db_name']));
			$rootdb->query($rootdb->prepare("TRUNCATE TABLE @VAR.wp_wpmlsubscribers", $credentials['db_name']));
			$rootdb->query($rootdb->prepare("TRUNCATE TABLE @VAR.wp_wpmlsubscribersoptions", $credentials['db_name']));

			// Write wp-config user file
			$secret_keys = @file_get_contents(Config::get('install', 'wp_api_salt_url'));
			if(!$secret_keys) {
				Error::warning('Could not retrieve salt unique keys.');
			}
			$wpcfg = "<?php\n".
				"define('DB_NAME', '{$credentials['db_name']}');\n".
				"define('DB_USER', '{$credentials['db_user']}');\n".
				"define('DB_PASSWORD', '{$credentials['db_pass']}');\n".
				"define('DB_HOST', 'localhost');\n".
				"define('DB_CHARSET', 'utf8mb4');\n".
				"define('DB_COLLATE', '');\n".
				"$secret_keys\n".
				'$table_prefix'." = 'wp_';\n".
				"define('WP_DEBUG', false);\n".
				"if(!defined('ABSPATH')) define('ABSPATH',dirname(__FILE__).'/');\n".
				"require_once(ABSPATH . 'wp-settings.php');\n"
			;
			file_put_contents($wpcfg_file, $wpcfg);

			// Write .htaccess file
			$htaccess =
				"\n# BEGIN WordPress\n".
				"<IfModule mod_rewrite.c>\n".
				"  RewriteEngine On\n".
				"  RewriteBase /\n".
				"  RewriteCond %{REQUEST_FILENAME} !-f\n".
				"  RewriteCond %{REQUEST_FILENAME} !-d\n".
				"  RewriteRule . /index.php [L]\n".
				"</IfModule>\n".
				"# END WordPress\n";
			file_put_contents("$user_dir/.htaccess", $htaccess);

			// Change user, group and file modes
			shell_exec("chown $unix_username:www-data $wpcfg_file");
			shell_exec("chmod 777 $wpcfg_file");

			// Create portal credentials and entries
			$credentials['portal_user'] = $unix_username;
			$credentials['portal_pass'] = Random::generate(8);
			$rootdb->query($rootdb->prepare("INSERT INTO int_portal.clients SET client=@VAL, active=1, clearing=0, server='02.websrv.eu', email=@VAL", $unix_username, $user->getField('email')));
			$rootdb->query($rootdb->prepare("INSERT INTO int_portal.domains SET client=@VAL, domain=@VAL, tld=@VAL, sld=@VAL, active=1", $unix_username, $domain, $user->getField('tld'), $user->getField('sld')));
			$rootdb->query($rootdb->prepare("INSERT INTO int_portal.planassignment SET client=@VAL, plan_start=NOW(), planid=14", $unix_username));
			$rootdb->query($rootdb->prepare("INSERT INTO int_portal.users SET user=@VAL, client=@VAL, active=1, passhash=@VAL, role='bws'", $credentials['portal_user'], $unix_username, sha1($credentials['portal_pass'])));

			// Set user's install status to 1
			$user->setField('status_install', 1);
			$user->save();

			// Execute install-complete hook (functions.php)
			App::executeHooks('install-complete', array(
				'user_obj' => $user,
				'credentials' => $credentials
			));

		}

	}

	public static function processDomainResponse($mail) {

		// Parse domain response
		if(!preg_match('@<system>(.*?)</system>@s', $mail, $matches)) return Error::warning('No system information found.');
		$system = trim($matches[1]);
		if(!preg_match('@<reply>(.*?)</reply>@s', $mail, $matches)) return Error::warning('No reply found.');
		$reply = trim($matches[1]);
		if(!$system || !$reply) return Error::warning('No processable response given.');
		$system = preg_split('/[\r\n]+/',$system);
		$reply = preg_split('/[\r\n]+/',$reply);
		$result = array();
		foreach($system as $prop) {
			if(!strstr($prop,':')) continue;
			$prop = explode(':',$prop,2);
			$key = 'system_'.strtolower(trim($prop[0]));
			$result[$key] = trim($prop[1]);
		}
		foreach($reply as $prop) {
			if(!strstr($prop,':')) continue;
			$prop = explode(':',$prop,2);
			$key = strtolower(trim($prop[0]));
			$result[$key] = trim($prop[1]);
		}

		// Get domain
		if(!isset($result['sld']) || !isset($result['tld'])) return Error::warning('Domain not found.');
		$domain = strtolower($result['sld']).'.'.strtolower($result['tld']);

		// Get user
		$user = User::getUserByDomain($domain);
		if(!$user) return Error::warning('User not found for domain: '.$domain);

		// Get success status
		$success = isset($result['status']) && strtolower($result['status'])==='success';

		if($user->getField('status_domain')) return Error::warning('Received domain response for already processed domain. '.print_r($result,true));

		// Set user domain status
		if($success) {
			$user->setField('status_domain', 1);
			$user->save();
		}else{
			App::adminNotification("Domain Registration Notification\n\n".print_r($result,true));
		}

		// Execute domain-response hook (functions.php)
		App::executeHooks('domain-response', array(
			'success' => $success,
			'user_obj' => $user,
			'domain' => $domain,
			'result' => $result
		));

		return true;

	}

	public static function erase(User $user) {
		// TODO
	}

}
