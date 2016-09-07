Firework CMS
============

Copyright (c) 2016 Roadfamily LLC

**[(MIT license)](LICENSE.md)**

Firework CMS is a highly sophisticated, multi-purpose CMS and a complete website framework written in pure object oriented PHP.
Every single aspect of this application is adjustable.

This peace of software is meant to help professional webdesigners and webmasters to generate new websites within a couple of hours instead of weeks.
We are using Firework CMS for our own projects. And we know it is pretty awesome code.
Firework CMS is *not* meant for people who have little to none experience in website coding.

Features
--------
- Running on PHP 5.4 or greater
- No online administration platform, which means perfect security: Pages and everything else can be modified via FTP / SFTP
- Supports skins (themes)
- Supports plugins and widgets

Docs
----
Firework CMS is completely documented.
You can read the entire [Class Reference online](http://www.fireworkcms.com/docs/).

Changelog
=========

Version 1.3.0
-------------
- Current DEV version

Version 1.2.0
-------------
- EMail class improvements
- Added app.utils app wide JavaScript tools
- Every AbstractDbCollection is now directly iterable using foreach()
- Database fields can now be directly read and written via $row->fieldname
- HTML escaped representations of database fields can be read using $row->fieldname_html
- App wide app.changed() function
- A website overlay can be raised using app.overlay()
- Added JavaScript translation access via app.lang.translate() using a callback function
- Added 'webcron' hook for executing tasks without OS cron
- MysqlDb improvements like multiQuery() and callProcedure()
- Made open source (MIT License)
- Changed +403.php search to pages directory
- Improved translation in `Language::currency()`
- Several bug fixes and other improvements

Version 1.1.0
-------------
- `App::getPage()` is deprecated
- Introducing `App::getUrlPart()` as *getPage()* replacement
- All system classes are now based on `ISystem` or `NISystem`
- System classes now support `injectMethod()` and/or `injectStaticMethod()`
- Debug info box UI enhancements
- Bug fixes

Version 1.0.0
-------------
- Initial public version
