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
    public $delayMethod = 'cron';
    public $delayCallback;
    public $normalNumberCallback;

    //Possible errors
    const PBSOL_ERROR_NO_API_URL = 1;
    const PBSOL_ERROR_NO_CERTIFICATE = 2;
    const PBSOL_ERROR_CERTIFICATE_NOT_FOUND = 3;
    const PBSOL_ERROR_LOG_RECORN_NOT_FOUND = 4;
    const PBSOL_ERROR_GET_RESULT = 5;
    const PBSOL_ERROR_EMPTY_PHONE = 6;
    const PBSOL_ERROR_EMPTY_MESSAGE = 7;
    const PBSOL_ERROR_EMPTY_ALPHA_NUMBER = 8;
    const PBSOL_ERROR_DELAY_CALLBACK_NOT_FOUND = 9;
    const PBSOL_ERROR_NORMAL_NUMBER_CALLBACK_NOT_FOUND = 10;

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

        // Check certificate
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

        // Check delay methods
        if ($this->delayMethod == 'callback') {
            switch (gettype($this->delayCallback)) {
            case 'array':
                if (
                    isset($this->delayCallback[0])
                    && isset($this->delayCallback[1])
                    && method_exists($this->delayCallback[0], $this->delayCallback[1])
                ) {
                    $this->delayMethod = 'callbackArray';
                } else {
                    throw new PbsolSmsSenderException(Yii::t(
                        'PbsolSmsSender', 'Delay callback not found'
                    ), self::PBSOL_ERROR_DELAY_CALLBACK_NOT_FOUND);
                }
                break;
            case 'object':
                $this->delayMethod = 'callbackObject';
                break;
            default:
                throw new PbsolSmsSenderException(Yii::t(
                    'PbsolSmsSender', 'Delay callback not found'
                ), self::PBSOL_ERROR_DELAY_CALLBACK_NOT_FOUND);
                break;
            }
        }
    }

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
        case 'callbackArray':
            return call_user_func($this->delayCallback, $smsGwLog);
            break;
        case 'callbackObject':
            $method = $this->delayCallback;
            return $method($smsGwLog);
            break;
        default:
            return false;
            break;
        }
    }

    /**
     * Normalizing phone number
     *
     * @param $number
     *
     * @return mixed
     * @throws PbsolSmsSenderException
     */
    public function numberNormal($number)
    {
        $number = preg_replace('/[^0-9]/x', '', trim($number));

        switch (gettype($this->normalNumberCallback)) {
        case 'array':
            if (
                isset($this->normalNumberCallback[0])
                && isset($this->normalNumberCallback[1])
                && method_exists($this->normalNumberCallback[0], $this->normalNumberCallback[1])
            ) {
                $number = call_user_func($this->normalNumberCallback, $number);
            } else {
                throw new PbsolSmsSenderException(Yii::t(
                    'PbsolSmsSender', 'Normalize number callback not found'
                ), self::PBSOL_ERROR_NORMAL_NUMBER_CALLBACK_NOT_FOUND);
            }
            break;
        case 'object':
            $method = $this->normalNumberCallback;
            $number = $method($number);
            break;
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
     * Return text description of sms state
     *
     * @param $guid string Message ID on Pbsol server
     * @return null|string
     */
    public function getState($guid)
    {
        $result = $this->sendRequest('GetMessageState', array('MsgID' => $guid));

        if (is_array($result) && isset($result['ResultDescription'])) {
            return $result['ResultDescription'];
        }
        return null;
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
                'header' => 'Content-type: application/json',
            )
        );
        $context = stream_context_create($opts);
        stream_context_set_option($context, 'ssl', 'local_cert', $this->certPath);
        $result = @file_get_contents(trim($this->apiUrl, '/') . '/' . $method, 0, $context);
        if (!$result) {
            throw new PbsolSmsSenderException(Yii::t(
                'PbsolSmsSender', 'Error with get result with method {method}', array('{method}' => $method)
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

} 