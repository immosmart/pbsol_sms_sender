Sms Sender (pbsol.ru)
================

Wrapper for sms sending (http://pbsol.ru/)

## Install
1) Make sure that `PbsolSmsSender.php`, `PbsolSmsSenderException.php`, `models`, `command` present in `protected/extensions/PbsolSmsSender` dir.

2) You must create `solid-cert.crt`: `cat cert.crt cert.key > solid-cert.crt`. `cert.key` - generated when submitting the certificate request , `cert.crt` - client key (from pbsol.ru)

3) Edit your components config `config/main.php`.

Add new component:
	
	'components' => array(
	//any components
		'smsSender' => array(
			'class' => 'ext.PbsolSmsSender.PbsolSmsSender',     // path for extension class
			'alphaNumber' => 'MyName',                          // Sender Name
			'apiUrl' => 'https://sms.pbsol.ru:1543/api/',       // pbsol.ru API-url for requests. Default "https://sms.pbsol.ru:1543/api/"
			'certPath' => 'config/keys/solid-cert.crt',         // Certificate file. From root-dir, or from "protected" dir
			'delayMethod' => 'cron',                            // Method of the queue. Default "cron"
			'delayCallback' => array('Class', 'Method'),        // If 'delayMethod' is 'callback'. Default "null"
			'normalNumberCallback' => array('Class', 'Method'), // Callback for normalize phone number. Default "null"
		),
	)

4) Edit your import config `config/main.php`.

Add lines:

	'import' => array(
	//any import
		'application.extensions.pbsolSmsSender.models._base.*',
		'application.extensions.pbsolSmsSender.models.*',
	)

5) Edit your commandMap config `config/console.php`.

Add new command:

	'commandMap' => array(
	//any commands
		'pbsolsms' => array(
			'class' => 'application.extensions.commands.pbsolSmsCommand',
		),
	)

6) Create new migration `yiic migrate create` and put into created migration-class methods from `data/migration.txt`. Then apply new migration `yiic migrate`