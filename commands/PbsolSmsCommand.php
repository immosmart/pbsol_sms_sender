<?php

/**
 * Class PbsolSmsCommand
 *
 * Command class for PbsolSmsSender extension.
 * Make sure that 'pbsolsms' present in 'console.php' config file.
 */

class PbsolSmsCommand extends CConsoleCommand
{

    /**
     * Send sms message with pbsol.ru API
     *
     * @param string $number          Phone number
     * @param string $message         Text of message
     * @param bool   $now             Send now (without Cron or Gearman). Default - false
     * @param bool   $tryNormalNumber Try normalize phone number. Default - true
     * @param string $alphaNumber     Alpha number. If null - uses alphaNumber from config
     */
    public function actionSend($number, $message, $now = false, $tryNormalNumber = true, $alphaNumber = null)
    {
        Yii::app()->smsSender->send($number, $message, (bool)$now, (bool)$tryNormalNumber, $alphaNumber);
    }

    /**
     * This method can be added to Cron.
     * Sends all messages waiting to be send.
     */
    public function actionCronJob()
    {
        /** @var CDbConnection $db */
        $db = Yii::app()->db;

        $stmt = $db->createCommand()->select('id')->from('pbsol_sms_log')->where('is_wait = :wait', array(':wait' => true))->query();

        while (($row = $stmt->read()) !== false) {
            $db->createCommand()->update('pbsol_sms_log', array('is_wait' => ':wait'), 'id = :id', array(':id' => $row['id'], ':wait' => false));
            Yii::app()->smsSender->pushById($row['id']);
        }
    }

}