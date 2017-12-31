<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
/**
 * This is the model class for table "room_player".
 *
 * @property integer $room_id
 * @property integer $user_id
 * @property integer $is_host
 * @property integer $is_ready
 * @property string $updated_at
 */
class RoomPlayer extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'room_player';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => new Expression('NOW()'),  //时间戳（数字型）转为 日期字符串
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['room_id', 'user_id', 'is_host', 'is_ready'], 'required'],
            [['room_id', 'user_id', 'is_host', 'is_ready'], 'integer'],
            [['updated_at'], 'safe'],
            [['room_id', 'user_id'], 'unique', 'targetAttribute' => ['room_id', 'user_id'], 'message' => 'The combination of Room ID and User ID has already been taken.'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'room_id' => 'Room ID',
            'user_id' => 'User ID',
            'is_host' => '是否是主机玩家',
            'is_ready' => 'Is Ready',
            'updated_at' => 'Updated At',
        ];
    }


    // 获取用户
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}
