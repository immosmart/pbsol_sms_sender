Sms Sender (pbsol.ru)
================

Wrapper for sms sending (http://pbsol.ru/)

## Install
1) Move `PbsolSmsSender.php` and `PbsolSmsSenderException` to `protected/extensions/PbsolSmsSender`

2) Edit your components config `config/main.php`.

Add new component:
	
	'components' => array(
	//any components
		'smsSender' => array(
			'class' => 'ext.PbsolSmsSender.PbsolSmsSender', // path for extension class
			'defaultCountry' => 'RU',                       // needed for default normalizing phone numbers
			'apiUrl' => 'https://sms.pbsol.ru:1543/api/',   // pbsol.ru API-url for requests
			'certPath' => 'config/cert.crt'                 // Certificate file. From root-dir, or from "protected" dir
		),
	)

3) 12313