<?php

if(rtrim(substr($_SERVER['REQUEST_URI'],0,2),'?')!=='/') {
	header('Location: /');
	die();
}

if(!isset($_GET['install'])) {
	header('Location: ?install=1');
	die();
}

?>
<!DOCTYPE HTML>
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
body, input, select { cursor: default; background: #333; color: #fff; font-family: 'Courier New', Courier, monospace; font-size: 16px; margin: 2em; line-height: 1.5em; }
ul, li { margin: 0; padding: 0; display: block; list-style: none; }
li { margin-bottom: 1em; }
a { cursor: pointer; display: inline-block; background: #ddd; color: #555; padding: 1em 2em 0.7em; text-decoration: none; border: 0; }
a:after {
	content: ' ▶︎';
}
a:hover { border-style: inset; box-shadow: none; background: #fd5; color: #000; }
input, select { color: #fd5; background: transparent; margin: 0; border: 0; border-left: 0.2em solid #555; padding-left: 0.3em; width: 100%; line-height: 2em; transition: all 0.5s; }
input { cursor: text; }
select { -webkit-appearance: none; cursor: pointer; border-radius: 0; color: #888; }
input:valid, select:valid { border-left-color: #fd5; color: #fd5; }
input:hover, input:focus, select:hover, select:focus { border-left-color: #555 !important; box-shadow: #555 0 1px 0 !important; }
label { display: block; cursor: pointer; color: #aaa; }
:focus { outline: none; }
::selection { background: #fd0; color: #fff; }
.ok { color: #0f0; }
.fail { color: #f00; }
</style>
</head><body>
<h1>Ding Framework Installation</h1>
<?php

define('APP_DIR', App::getAppDir());
define('HOME_DIR', realpath(APP_DIR.'..').'/');
define('REQUIRE_PHP_VERSION', '5.4.0');

switch((int) $_GET['install']) {
	case 1:
		$check = array();
		$check['server'] = strstr($_SERVER["SERVER_SOFTWARE"], 'Apache');
		$check['php_version'] = version_compare(PHP_VERSION, REQUIRE_PHP_VERSION)>=0;
		$check['write_access'] = is_writable(HOME_DIR);
?>
		<h2>Checking Requirements</h2>
		<ul>
			<li>
				<strong>HTTP Server</strong> = Apache<br>
				<?php echo $check['php_version'] ? '<span class="ok">OK</span> (is '.$_SERVER["SERVER_SOFTWARE"].')' : '<span class="fail">FAILED</span> (using '.$_SERVER["SERVER_SOFTWARE"].')' ?>
			</li>
			<li>
				<strong>PHP Version</strong> ≥ <?= REQUIRE_PHP_VERSION ?><br>
				<?php echo $check['php_version'] ? '<span class="ok">OK</span> (using '.PHP_VERSION.')' : '<span class="fail">FAILED</span> (using '.PHP_VERSION.')' ?>
			</li>
			<li>
				<strong>PHP short open tag</strong><br>
				<?php echo ini_get('short_open_tag') ? '<span class="ok">OK</span> (true)' : '<span class="fail">FAILED</span> (set short_open_tag = true)' ?>
			</li>
			short_open_tag
			<li>
				<strong>Write Access</strong> <?php echo HOME_DIR ?><br>
				<?php echo $check['write_access'] ? '<span class="ok">OK</span> (writable)' : '<span class="fail">FAILED</span> (make it writable and try again)' ?>
			</li>
		</ul>
<?php
		if(array_search(false, $check)===false):
?>
			<p>
				<a href="?install=2">Continue</a>
			</p>
<?php
		endif;
		break;
	case 2:
		if(!preg_match_all('/%([A-Z0-9_]+)%/', file_get_contents(APP_DIR.'inc/install/config-template.ini'), $fields)) die('<span class="fail">Error while reading config template.</span>');
		require_once(APP_DIR.'inc/install/timezones.php');
?>
		<h2>Information Input</h2>
		<form method="post" action="?install=3">
		<ul>
<?php
			$fields = array_unique($fields[1]);
			foreach($fields as $field):
				$label = str_replace('_', ' ', $field);
				$value = null;
				switch($field) {
					case 'HOST':
						$value = $_SERVER['HTTP_HOST'];
						break;
					case 'TIMEZONE':
						$value = $timezones;
						break;
					case 'CURRENCY':
						$label = 'CURRENCY SYMBOL';
						break;
					case 'SALT':
						$label = 'SESSION SALT';
						$value = Random::generate(64);
						break;
					case 'TITLE':
						$label = 'TITLE FOR HOME (COMPLETE, NO SUFFIX ADDED)';
						break;
				}
?>
				<li>
					<label for="<?php echo $field ?>"><?php echo $label ?></label>
<?php
					if(is_array($value)):
?>
						<select id="<?php echo $field ?>" name="<?php echo $field ?>" required>
							<option value="">— Select —</option>
<?php
							foreach($value as $option_key=>$option_value):
?>
								<option value="<?php echo htmlspecialchars($option_key) ?>"><?php echo htmlspecialchars($option_value) ?></option>
<?php
							endforeach;
?>
						</select>
<?php
					else:
?>
						<input id="<?php echo $field ?>" type="text" name="<?php echo $field ?>" value="<?php echo htmlspecialchars($value) ?>" autocomplete="off" spellcheck="false" required>
<?php
					endif;
?>
				</li>
<?php
			endforeach;
?>
		</ul>
		<p>
			<a href="javascript:document.forms[0].submit()">Run Installation</a>
		</p>
		</form>
<?php
		break;
	case 3:
		if($_SERVER['REQUEST_METHOD']!=='POST') die('<span class="fail">Wrong request method.</span>');
		function maybe_mkdir($dir) {
			echo '<strong>Creating directory '.$dir.'</strong><br>';
			if(file_exists($dir) && is_dir($dir)) {
				echo '<span class="ok">Skipped</span> (directory already exists)';
				return true;
			}elseif(@mkdir($dir)) {
				echo '<span class="ok">OK</span> (directory created)';
				return true;
			}else{
				die('<span class="fail">FAILED</span>');
			}
		}
		function maybe_copy($src, $dst) {
			echo '<strong>Copying file '.$dst.'</strong><br>';
			if(file_exists($dst)) {
				echo '<span class="ok">Skipped</span> (file existing)';
				return true;
			}elseif(@copy($src, $dst)) {
				echo '<span class="ok">OK</span> (file copied)';
				return true;
			}else{
				die('<span class="fail">FAILED</span>');
			}
		}
		function maybe_write($filename, $content) {
			echo '<strong>Writing file '.$filename.'</strong><br>';
			if(file_exists($filename)) {
				echo '<span class="ok">Skipped</span> (file existing)';
				return true;
			}elseif(@file_put_contents($filename, $content)) {
				echo '<span class="ok">OK</span> (file created)';
				return true;
			}else{
				die('<span class="fail">FAILED</span>');
			}
		}
		function maybe_deny_access($dir) {
			echo '<strong>Denying public access for '.$dir.'</strong><br>';
			$file = rtrim($dir, '/').'/.htaccess';
			if(file_exists($file)) {
				echo '<span class="ok">Skipped</span> (.htaccess file existing)';
				return true;
			}elseif(@file_put_contents($file, "Order deny,allow\nDeny from all")) {
				echo '<span class="ok">OK</span> (.htaccess file created)';
				return true;
			}else{
				die('<span class="fail">FAILED</span>');
			}
		}
?>
		<h2>Installation</h2>
		<ul>

			<li>
				<strong>MySQL connection</strong><br>
<?php
				$mysqli = new mysqli($_POST['MYSQL_HOST'], $_POST['MYSQL_USERNAME'], $_POST['MYSQL_PASSWORD'], $_POST['MYSQL_DBNAME']);
				if($mysqli->connect_error) {
					echo '<span class="fail">FAILED</span>';
					echo "<script>alert('MySQL connection failed. Try again.'); history.back();</script>\n";
					die();
				}
?>
				<span class="ok">OK</span> (connection established)
			</li>
			<li><?php maybe_write(HOME_DIR.'.htaccess', str_replace('%APP_DIR%', APP_DIR, file_get_contents(APP_DIR.'inc/install/home-htaccess.txt'))) ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'cache') ?></li>
			<li><?php maybe_deny_access(HOME_DIR.'cache') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site/cls') ?></li>
			<li><?php maybe_deny_access(HOME_DIR.'site/cls') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site/lang') ?></li>
			<li><?php maybe_deny_access(HOME_DIR.'site/lang') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site/pages') ?></li>
			<li><?php maybe_deny_access(HOME_DIR.'site/pages') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site/skins') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site/skins/default') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site/u') ?></li>
			<li><?php maybe_mkdir(HOME_DIR.'site/widgets') ?></li>
			<li><?php maybe_deny_access(HOME_DIR.'site/widgets') ?></li>
			<li><?php maybe_copy(APP_DIR.'inc/install/defaults/+404.php', HOME_DIR.'site/pages/+404.php') ?></li>
			<li><?php maybe_copy(APP_DIR.'inc/install/defaults/+404+image.php', HOME_DIR.'site/pages/+404+image.php') ?></li>
			<li><?php maybe_copy(APP_DIR.'inc/install/defaults/+start.php', HOME_DIR.'site/pages/+start.php') ?></li>
			<li><?php maybe_copy(APP_DIR.'inc/install/defaults/skin/tpl-head.php', HOME_DIR.'site/skins/default/tpl-head.php') ?></li>
			<li><?php maybe_copy(APP_DIR.'inc/install/defaults/skin/tpl-foot.php', HOME_DIR.'site/skins/default/tpl-foot.php') ?></li>
			<li><?php maybe_copy(APP_DIR.'inc/install/defaults/skin/styles.php', HOME_DIR.'site/skins/default/styles.php') ?></li>
			<li><?php maybe_copy(APP_DIR.'inc/install/defaults/skin/functions.php', HOME_DIR.'site/skins/default/functions.php') ?></li>
			<li>
				<strong>Writing config.ini</strong><br>
<?php
				if(file_exists(HOME_DIR.'config.ini')):
?>
					<span class="ok">Skipped</span> (already existing)
<?php
				else:
					$config_ini = file_get_contents(APP_DIR.'inc/install/config-template.ini');
					foreach($_POST as $key=>$value) {
						$config_ini = str_replace('%'.$key.'%', $value, $config_ini);
					}
					echo @file_put_contents(HOME_DIR.'config.ini', $config_ini) ? '<span class="ok">OK</span> (file written)' : die('<span class="fail">FAILED</span>');
				endif;
?>
			</li>
			<li>
				<strong>Creating MySQL structure</strong><br>
<?php
				foreach(explode(';', file_get_contents(APP_DIR.'inc/install/tables.sql')) as $sql_cmd) {
					$sql_cmd = trim($sql_cmd);
					if(!$sql_cmd) continue;
					if($mysqli->query($sql_cmd)!==true) {
						die('<span class="fail">FAILED</span> '.$mysqli->error);
					}
				}
				if($mysqli->connect_error) {
					echo '<span class="fail">FAILED</span>';
					echo "<script>alert('MySQL connection failed. Try again.'); history.back();</script>\n";
					die();
				}
?>
				<span class="ok">OK</span> (tables created)
			</li>
		</ul>
		<p>
			<a href="/">Go To Home</a>
		</p>
<?php
		break;
	default:
		echo '<span class="fail">Unknown Installation Status.</span>';
}

?>
</body></html>