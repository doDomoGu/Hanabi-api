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
/*    const STATUS_AVAILABLE = 0;  //可用的空房间
    const STATUS_PREPARING = 1;  //准备中，未开始
    const STATUS_PLAYING = 2;    //游玩中，已开始*/

//    const EXCEPTION_NOT_FOUND_CODE  = 10001;
//    const EXCEPTION_NOT_FOUND_MSG   = '房间不存在';
//    const EXCEPTION_PLAYER_OVER_LIMIT_CODE  = 10002;
//    const EXCEPTION_PLAYER_OVER_LIMIT_MSG   = '房间人数超过限制（大于2人）';
//    const EXCEPTION_IN_MANY_ROOM_CODE  = 10003;
//    const EXCEPTION_IN_MANY_ROOM_MSG   = '一个玩家在多个房间内';
//    const EXCEPTION_NO_HOST_PLAYER_CODE  = 10004;
//    const EXCEPTION_NO_HOST_PLAYER_MSG   = '房间内至少有一个玩家是主机';
//    const EXCEPTION_EXIT_NOT_IN_ROOM_CODE  = 10005;
//    const EXCEPTION_EXIT_NOT_IN_ROOM_MSG   = '退出操作，但是不在房间内';
    const EXCEPTION_EXIT_DELETE_FAILURE_CODE  = 10006;
    const EXCEPTION_EXIT_DELETE_FAILURE_MSG   = '退出操作，删除玩家失败';
//    const EXCEPTION_ENTER_HAS_IN_ROOM_CODE  = 10007;
//    const EXCEPTION_ENTER_HAS_IN_ROOM_MSG   = '进入操作，但是已经在房间中';
//    const EXCEPTION_ENTER_PLAYER_FULL_CODE  = 10008;
//    const EXCEPTION_ENTER_PLAYER_FULL_MSG   = '进入操作，但是房间已满';
//    const EXCEPTION_DO_READY_NOT_IN_ROOM_CODE = 10009;
//    const EXCEPTION_DO_READY_NOT_IN_ROOM_MSG  = '准备操作，但是不在房间内';
    const EXCEPTION_DO_READY_NOT_GUEST_PLAYER_CODE = 10010;
    const EXCEPTION_DO_READY_NOT_GUEST_PLAYER_MSG  = '准备操作，但是不是客机玩家';
    const EXCEPTION_DO_READY_FAILURE_CODE = 10011;
    const EXCEPTION_DO_READY_FAILURE_MSG  = '准备操作，失败';
//    const EXCEPTION_PLAYER_NOT_FOUND_CODE = 10012;
//    const EXCEPTION_PLAYER_NOT_FOUND_MSG  = '对应的玩家找不到';
//    const EXCEPTION_PLAYER_NUMBER_WRONG_MSG   = '房间人数错误';
//    const EXCEPTION_PLAYER_NUMBER_WRONG_CODE  = 10013;



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

    # 检查房间状态
    # 参数：$roomId
    # 无返回值
    public static function check($room){
        # error: 房间不存在
        if(!$room){
            RoomException::t('not_found');
        }
        $roomPlayerNumber = (int) RoomPlayer::find()->where(['room_id'=>$room->id])->count(); //房间玩家人数
        # error: 房间玩家人数错误
        if( !in_array($roomPlayerNumber,[0, 1, 2])){
            RoomException::t('wrong_player_number');
        }

        # 房间为空结束检查
        if($roomPlayerNumber == 0){
            exit;
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
                if(!$hostPlayer) {
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

    # 获取room对象
    public static function getOne($roomId) {
        $room = self::find()->where(['id'=>$roomId])->one();
//        Room::check($room);
        return $room;
    }

}
