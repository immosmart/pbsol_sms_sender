<?php

/**
 * Class PbsolSmsSender
 *
 * Wrapper for pbsol.ru sms-server
 */
class PbsolSmsSender extends CApplicationComponent
{
    //Config params
    public $alphaNumber;
    public $apiUrl = 'https://sms.pbsol.ru:1543/api/';
    public $certPath;
    public $defaultCountry = 'RU';
    public $delayMethod = 'cron';

    //Possible errors
    const PBSOL_ERROR_NO_API_URL = 1;
    const PBSOL_ERROR_NO_CERTIFICATE = 2;
    const PBSOL_ERROR_CERTIFICATE_NOT_FOUND = 3;
    const PBSOL_ERROR_LOG_RECORN_NOT_FOUND = 4;
    const PBSOL_ERROR_GET_RESULT = 5;
    const PBSOL_ERROR_EMPTY_PHONE = 6;
    const PBSOL_ERROR_EMPTY_MESSAGE = 7;
    const PBSOL_ERROR_EMPTY_ALPHA_NUMBER = 8;

    /**
     * Initializing component
     *
     * @throws PbsolSmsSenderException
     */
    public function init()
    {
        parent::init();

        //import Pbsol Exception class
        $dir = dirname(__FILE__);
        $alias = md5($dir);
        Yii::setPathOfAlias($alias, $dir);
        Yii::import($alias . '.PbsolSmsSenderException');

        if (!$this->apiUrl) {
            throw new PbsolSmsSenderException(Yii::t('PbsolSmsSender', 'Api url needed'), self::PBSOL_ERROR_NO_API_URL);
        }

        if (!$this->certPath) {
            throw new PbsolSmsSenderException(Yii::t(
                'PbsolSmsSender', 'Certificate needed'
            ), self::PBSOL_ERROR_NO_CERTIFICATE);
        }

        if (!file_exists($this->certPath)) {
            $this->certPath = Yii::getPathOfAlias('application') . '/' . trim($this->certPath, '/');

            if (!file_exists($this->certPath)) {
                throw new PbsolSmsSenderException(Yii::t(
                    'PbsolSmsSender', 'Certificate file "{file}" not found', array('{file}' => $this->certPath)
                ), self::PBSOL_ERROR_CERTIFICATE_NOT_FOUND);
            }
        }
    }

    /**
     * Send sms message with pbsol.ru API
     *
     * @param string $phone           Phone number
     * @param string $message         Text of message
     * @param bool   $now             Send now (without Cron or Gearman). Default - false
     * @param bool   $tryNormalNumber Try normalize phone number. Default - true
     * @param string $alphaNumber     Alpha number. If null - uses alphaNumber from config
     *
     * @return bool
     * @throws PbsolSmsSenderException
     */
    public function send($phone, $message, $now = false, $tryNormalNumber = true, $alphaNumber = null)
    {
        $phoneNumber = $phone;
        if ($tryNormalNumber) {
            $phoneNumber = $this->numberNormal($phone);
        }

        $alphaNumber = $alphaNumber ? : $this->alphaNumber;

        $error = 0;

        // Create PbsolSmsLog-record, check input data and save current status

        if (empty($phoneNumber)) {
            $error = self::PBSOL_ERROR_EMPTY_PHONE;
        }
        if (empty($message)) {
            $error = self::PBSOL_ERROR_EMPTY_MESSAGE;
        }
        if (empty($alphaNumber)) {
            $error = self::PBSOL_ERROR_EMPTY_ALPHA_NUMBER;
        }

        $smsGwLog = new PbsolSmsLog();
        $smsGwLog->from = $alphaNumber;
        $smsGwLog->to = $phoneNumber;
        $smsGwLog->text = $message;
        $smsGwLog->created = date('Y-m-d H:i:s');

        switch ($error) {
        case self::PBSOL_ERROR_EMPTY_PHONE:
            $smsGwLog->status = Yii::t(
                'PbsolSmsSender', 'Phone number is incorrect. Input number: {number}', array('{number}' => $phone)
            );
            break;
        case self::PBSOL_ERROR_EMPTY_MESSAGE:
            $smsGwLog->status = Yii::t('PbsolSmsSender', 'Message is empty');
            break;
        case self::PBSOL_ERROR_EMPTY_ALPHA_NUMBER:
            $smsGwLog->status = Yii::t('PbsolSmsSender', 'Alpha number is empty');
            break;
        }

        $smsGwLog->save();

        if ($error != 0) {
            throw new PbsolSmsSenderException($smsGwLog->status, $error);
        }

        // Sending message
        if ($now) {
            return $this->pushByModel($smsGwLog);
        }

        // Put message in the queue
        switch ($this->delayMethod) {
        case 'cron':
            return $this->toCron($smsGwLog);
            break;
        case 'gearman':
            return $this->toGearman($smsGwLog);
            break;
        default:
            return false;
            break;
        }
    }

    /**
     * Normalizing phone number for country.
     * If $country is null, uses defaultCountry from config.
     *
     * @param string      $number
     * @param null|string $country
     *
     * @return null|string
     */
    public function numberNormal($number, $country = null)
    {
        if (!$country) {
            $country = $this->defaultCountry;
        }

        $number = preg_replace('/[^0-9]/x', '', trim($number));

        if ($country == 'KZ' || $country == 'RU') {
            // если номер пришел без кода страны
            if (strlen($number) == 10) {
                $number = '7' . $number;
            }
            // если первый символ - не код страны 7, а, например, 8
            if (strpos($number, '7') !== 0) {
                $number = '7' . substr($number, 1);
            }
            // проверяем получившийся номер по регулярке
            if (preg_match('/^[0-9]{11}$/', $number)) {
                return $number;
            }
            return null;
        }

        return $number;
    }

    /**
     * Send sms by ID of PbsolSmsLog-record
     *
     * @param $id integer ID of PbsolSmsLog-record
     *
     * @return bool
     * @throws PbsolSmsSenderException
     */
    public function pushById($id)
    {
        $model = PbsolSmsLog::model()->findByPk($id);
        if (!$model) {
            throw new PbsolSmsSenderException(Yii::t(
                'PbsolSmsSender', 'PbsolSmsLog with id={id} not found', array('{id}' => $id)
            ), self::PBSOL_ERROR_LOG_RECORN_NOT_FOUND);
        }

        return $this->pushByModel($model);
    }

    /**
     * Send sms by PbsolSmsLog-record
     *
     * @param PbsolSmsLog $smsGwLog
     *
     * @return bool
     */
    private function pushByModel(PbsolSmsLog $smsGwLog)
    {
        $smsGwLog->status = Yii::t(
            'PbsolSmsSender', '{date} - Preparing send message', array('{date}' => date('Y-m-d H:i:s'))
        );
        $smsGwLog->save();

        // It will be send to pbsol.ru API
        $data = array(
            'AlphaNumber' => $smsGwLog->from,
            'Msisdn' => $smsGwLog->to,
            'Text' => $smsGwLog->text
        );

        // Send request and read response
        $result = $this->sendRequest('SendSms', $data);

        // True response
        if ($result['ResultCode'] == 0) {
            $smsGwLog->guid = $result['Result'];
            $smsGwLog->status = Yii::t('PbsolSmsSender', '{date} - OK send', array('{date}' => date('Y-m-d H:i:s')));
            $smsGwLog->save();
            return true;
        }

        // False response
        $smsGwLog->status = Yii::t(
            'PbsolSmsSender', '{date} - Error send. Code: {code}. {description}', array(
                '{date}' => date('Y-m-d H:i:s'),
                '{code}' => $result['ResultCode'],
                '{description}' => $result['ResultDescription']
            )
        );
        $smsGwLog->save();
        return false;
    }

    /**
     * Send request to Pbsol server. Try read response and decode to array.
     *
     * @param string $method
     * @param array  $params
     *
     * @return array|null
     * @throws PbsolSmsSenderException
     */
    private function sendRequest($method, $params = array())
    {
        $request = json_encode($params);
        $opts = array(
            'http' => array(
                'method' => "POST",
                'content' => $request,
            )
        );
        $context = stream_context_create($opts);
        stream_context_set_option($context, 'ssl', 'local_cert', $this->certPath);
        $result = @file_get_contents(trim($this->apiUrl, '/') . '/' . $method, 0, $context);
        if (!$result) {
            throw new PbsolSmsSenderException(Yii::t(
                'PbsolSmsSender', 'Error with get result with method {method}', array('{method}', $method)
            ), self::PBSOL_ERROR_GET_RESULT);
        }
        return json_decode($result, true);
    }

    /**
     * Mark sms as needed processing by Cron-Command
     *
     * @param PbsolSmsLog $smsGwLog
     *
     * @return bool
     */
    private function toCron(PbsolSmsLog $smsGwLog)
    {
        $smsGwLog->is_wait = true;
        $smsGwLog->status = Yii::t(
            'PbsolSmsSender', '{date} - Waiting Cron job', array('{date}' => date('Y-m-d H:i:s'))
        );
        $smsGwLog->save();
        return true;
    }

    /**
     * Push to Gearman queue
     *
     * @param PbsolSmsLog $smsGwLog
     *
     * @return bool
     */
    private function toGearman(PbsolSmsLog $smsGwLog)
    {
        $smsGwLog->status = Yii::t(
            'PbsolSmsSender', '{date} - Waiting Gearman job', array('{date}' => date('Y-m-d H:i:s'))
        );
        $smsGwLog->save();
        $args = json_encode(array('id' => $smsGwLog->id));
        GearmanModel::createClient("PbsolSmsPushById", $args);
        return true;
    }

} 