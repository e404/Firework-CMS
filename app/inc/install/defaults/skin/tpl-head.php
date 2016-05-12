<!DOCTYPE HTML>
<html lang="<?= App::getLang() ?>">
	<head>
		<base href="<?= App::getLink(Config::get('env', 'baseuri')) ?>">
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>[[[TITLE]]]</title>
		<link rel="canonical" href="[[[CANONICAL]]]">
		<meta name="viewport" content="width=device-width, user-scalable=no">
		<link rel="stylesheet" type="text/css" href="<? App::linkVersionedFile(App::getSkinPath().'styles.css') ?>">
		<script type="text/javascript" src="<?= App::linkFile(App::getAppDir().'lib/jquery-2.1.4.min.js') ?>"></script>
		<script type="text/javascript" src="<? App::linkVersionedFile(App::getAppDir().'inc/public/app.js') ?>"></script>
		<? if(Config::get('debug')): ?>
			<script type="text/javascript" src="<? App::linkVersionedFile(App::getAppDir().'lib/js.cookie.min.js') ?>"></script>
			<script type="text/javascript" src="<? App::linkVersionedFile(App::getAppDir().'inc/public/debug.js') ?>"></script>
		<? endif; ?>
		[[[HOOK:head]]]
	</head>
	<body class="[[[BODYCLASS]]]">
		<div id="wrapper">
			<div id="container">
				<div id="header">
					<div id="logo" onclick="location.href='/'"></div>
					<div class="menu-wrapper">
						<div class="menu">
							<ul>
								[[[HOOK:menu]]]
							</ul>
						</div>
					</div>
					[[[HOOK:header]]]
				</div>
				<div id="content">
