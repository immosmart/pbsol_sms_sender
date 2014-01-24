<?php

/**
 * This is the model class for table "pbsol_sms_log".
 *
 * The followings are the available columns in table 'pbsol_sms_log':
 * @property integer $id
 * @property string $guid
 * @property string $from
 * @property string $to
 * @property string $text
 * @property string $status
 * @property boolean $is_wait
 * @property string $created
 * @property string $delivered
 */
class PbsolSmsLog extends CActiveRecord
{
    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return PbsolSmsLog the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'pbsol_sms_log';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('guid, from, to, status', 'length', 'max' => 255),
            array('text, is_wait, created, delivered', 'safe'),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, guid, from, to, text, status, is_wait, created, delivered', 'safe', 'on' => 'search'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array();
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'guid' => 'Guid',
            'from' => 'From',
            'to' => 'To',
            'text' => 'Text',
            'status' => 'Status',
            'is_wait' => 'Is Wait',
            'created' => 'Created',
            'delivered' => 'Delivered',
        );
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id);
        $criteria->compare('guid', $this->guid, true);
        $criteria->compare('t.from', $this->from, true);
        $criteria->compare('t.to', $this->to, true);
        $criteria->compare('text', $this->text, true);
        $criteria->compare('status', $this->status, true);
        $criteria->compare('is_wait', $this->is_wait);
        $criteria->compare('created', $this->created, true);
        $criteria->compare('delivered', $this->delivered, true);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }

    /**
     * Push sms
     *
     * @return boolean
     */
    public function push()
    {
        return Yii::app()->smsSender->pushByModel($this);
    }

    /**
     * Return text description of sms state
     *
     * @return mixed
     */
    public function getState()
    {
        return Yii::app()->smsSender->getState($this->guid);
    }
}