<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "history_player".
 *
 * @property integer $history_id
 * @property integer $user_id
 * @property integer $is_host
 */
class HistoryPlayer extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'history_player';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['history_id', 'user_id', 'is_host'], 'required'],
            [['history_id', 'user_id', 'is_host'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'history_id' => 'History ID',
            'user_id' => 'User ID',
            'is_host' => 'Is Host',
        ];
    }
}
