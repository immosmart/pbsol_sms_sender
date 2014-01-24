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
			'delayMethod' => 'cron',                            // Method of the queue (cron, callback). Default "cron"
			'delayCallback' => array('Class', 'Method'),        // If 'delayMethod' is 'callback'. Default "null"
			'normalNumberCallback' => array('Class', 'Method'), // Callback for normalize phone number. Default "null"
		),
	)

4) Edit your import config `config/main.php`.

Add lines:

	'import' => array(
	//any import
		'application.extensions.PbsolSmsSender.models.*',
	)

5) Edit your commandMap config `config/console.php`.

Add new command:

	'commandMap' => array(
	//any commands
		'pbsolsms' => array(
			'class' => 'application.extensions.PbsolSmsSender.commands.PbsolSmsCommand',
		),
	)

6) Create new migration `yiic migrate create` and put into created migration-class methods from `data/migration.txt`. Then apply new migration `yiic migrate`

## Using

`Yii::app()->smsSender` - global object of PbsolSmsSender extension

## Methods

### send

    /**
     * Send sms message with pbsol.ru API
     *
     * @param string $phone           Phone number
     * @param string $message         Text of message
     * @param bool   $now             Send now (without Cron or Callback). Default - false
     * @param bool   $tryNormalNumber Try normalize phone number. Default - true
     * @param string $alphaNumber     Alpha number. If null - uses alphaNumber from config
     *
     * @return bool
     * @throws PbsolSmsSenderException
     */
    public function send($phone, $message, $now = false, $tryNormalNumber = true, $alphaNumber = null)

`Yii::app()->smsSender->send('79012345678', 'Test message', true, true, 'MyName');`

### numberNormal

    /**
     * Normalizing phone number
     *
     * @param $number
     *
     * @return string|null
     * @throws PbsolSmsSenderException
     */
    public function numberNormal($number)

`$number = Yii::app()->smsSender->numberNormal('+7 (901) 234-56-78'); // 79012345678`

### pushById

    /**
     * Send sms by ID of PbsolSmsLog-record
     *
     * @param $id integer ID of PbsolSmsLog-record
     *
     * @return bool
     * @throws PbsolSmsSenderException
     */
    public function pushById($id)

It is used for delayed sending or resending

`Yii::app()->smsSender->pushById(57);`

### pushByModel

    /**
     * Send sms by PbsolSmsLog-record
     *
     * @param PbsolSmsLog $smsGwLog
     *
     * @return bool
     */
    public function pushByModel(PbsolSmsLog $smsGwLog)

It is used for delayed sending or resending

    $model = PbsolSmsLog::model->findByPk(57);
    Yii::app()->smsSender->pushByModel($model);

### getState

    /**
     * Return text description of sms state
     *
     * @param $guid string Message ID on Pbsol server
     * @return null|string
     */
    public function getState($guid)

## Normalize phone number

You can define the method (callback) that will normalize the number.

The method must return normal phone number OR null (in case of error).

Define the method in component settings:

a) as array('Class', 'method')

`'normalNumberCallback' => array('MyClass', 'myMethod')`

b) as closure

    'normalNumberCallback' => function($number) {
        // some code
        return $number;
    }

### Example (for RU-numbers)

    class MyClass
    {
        /**
         * Normalizing phone number for RU-numbers
         *
         * @param string $number
         *
         * @return string|null
         */
        public static function myMethod($number)
        {
            $number = preg_replace('/[^0-9]/x', '', trim($number));

            if (strlen($number) == 10) {
                $number = '7' . $number;
            }

            if (strpos($number, '7') !== 0) {
                $number = '7' . substr($number, 1);
            }

            if (preg_match('/^[0-9]{11}$/', $number)) {
                return $number;
            }

            return null;
        }
    }

## Commands

### send

    /**
     * Send sms message with pbsol.ru API
     *
     * @param string $number          Phone number
     * @param string $message         Text of message
     * @param bool   $now             Send now (without Cron or Callback). Default - false
     * @param bool   $tryNormalNumber Try normalize phone number. Default - true
     * @param string $alphaNumber     Alpha number. If null - uses alphaNumber from config
     */
    public function actionSend($number, $message, $now = false, $tryNormalNumber = true, $alphaNumber = null)

`php yiic pbsolsms send --number=79012345678 --message=Test --now=0`

### cronJob

    /**
     * This method can be added to Cron.
     * Sends all messages waiting to be send.
     */
    public function actionCronJob()

`php yiic pbsolsms cronjob`

## Delay

### Cron

You must setup the Crontab to execute command `php yiic pbsolsms cronjob`

### Callback

Define the method in component settings:

a) as array('Class', 'method')

`'delayCallback' => array('MyClass', 'myMethod')`

b) as closure

    'delayCallback' => function($model) {
        // some code
        return true;
    }

#### Example

    class MyClass
    {

        public static function myMethod(PbsolSmsLog $model)
        {
            $model->status = Yii::t(
                'PbsolSmsSender', '{date} - Waiting Gearman job', array('{date}' => date('Y-m-d H:i:s'))
            );
            $model->save();
            $args = json_encode(array('id' => $model->id));
            GearmanModel::createClient('PbsolSmsPushById', $args);
            return true;
        }
    }

## PbsolSmsLog

This is ActiveRecord model of table `pbsol_sms_log`

### Push

    /**
     * Push sms
     *
     * @return boolean
     */
    public function push()

It is used for delayed sending or resending

    $model = PbsolSmsLog::model()->findByPk(57);
    $model->push();

this is the same of

    $model = PbsolSmsLog::model()->findByPk(57);
    Yii::app()->smsSender->pushByModel($model);

and

    Yii::app()->smsSender->pushById(57);

### Get state

    /**
     * Return text description of sms state
     *
     * @return string|null
     */
    public function getState()
