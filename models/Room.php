<?php

namespace app\models;

use app\components\exception\RoomException;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
/**
 * This is the model class for table "room".
 *
 * @property integer $id
 * @property string $title
 * @property string $password
 * @property string $updated_at
 */
class Room extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%room}}';
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
            [['title'], 'required'],
            [['updated_at'], 'safe'],
            [['title'], 'string', 'max' => 100],
            [['password'], 'string', 'max' => 50],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'password' => 'Password',
        ];
    }

    // 连表查询，房间中的主机玩家
    public function getHostPlayer()
    {
        return $this->hasOne(RoomPlayer::className(), ['room_id' => 'id'])->where(['is_host' => 1]);
    }

    // 连表查询，房间中的客机玩家
    public function getGuestPlayer()
    {
        return $this->hasOne(RoomPlayer::className(), ['room_id' => 'id'])->where(['is_host' => 0]);
    }

    # 根据ID 获取单个room对象
    # 参数 ： roomId 房间ID
    #        check  是否对房间数据进行检查
    public static function getOne($roomId, $check=true) {
        $room =  self::findOne(['id'=>$roomId]);
        if($check) {
            self::check($roomId);
        }
        return $room;
    }

    # 检查房间状态
    # 参数：$roomId
    # 无返回值
    public static function check($roomId){
        $room = self::getOne($roomId, false);
        # error: 房间不存在
        if(!$room){
            RoomException::t('not_found');
        }
        // 房间玩家人数
        $roomPlayerNumber = (int) RoomPlayer::find()->where(['room_id'=>$roomId])->count();
        # error: 房间玩家人数错误
        if( !in_array($roomPlayerNumber,[0, 1, 2])){
            RoomException::t('wrong_player_number');
        }

        # 房间为空结束检查
        if($roomPlayerNumber == 0){
            return;
        }

        # 房间非空开始检查玩家信息
        if($roomPlayerNumber > 0 ){
            $hostPlayer = $room->hostPlayer;

            # error: 没有主机玩家
            if(!$hostPlayer) {
                RoomException::t('no_host_player');
            }else{
                # error: 有主机玩家 但是找不到对应的玩家信息
                if(!$hostPlayer->user){
                    RoomException::t('host_player_not_found');
                }
            }

            if($roomPlayerNumber == 2){
                $guestPlayer = $room->guestPlayer;

                # error: 没有客机玩家
                if(!$guestPlayer) {
                    RoomException::t('no_guest_player');
                }else{
                    # error: 有客机玩家 但是找不到对应的玩家信息
                    if(!$guestPlayer->user){
                        RoomException::t('guest_player_not_found');
                    }
                }
            }
        }
    }



}
