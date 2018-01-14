<?php

namespace app\models;

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


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'room';
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

    public static function enter($roomId){
        $success = false;
        $msg = '';
        $userId = Yii::$app->user->id;
        $isInRoom = RoomPlayer::find()->where(['user_id'=>$userId])->one();
        if($isInRoom){
           $msg = '已经在房间中';
        }else{
            $room = Room::find()->where(['id' => $roomId])->one();
            if ($room) {
                if ($room->password != '') {
                    $msg = '房间被锁住了';
                } else {
                    $roomPlayerCount = (int)RoomPlayer::find()->where(['room_id' => $room->id])->count();
                    if ($roomPlayerCount<2){
                        if ($roomPlayerCount === 0) {
                            $newRoomPlayer = new RoomPlayer();
                            $newRoomPlayer->room_id = $roomId;
                            $newRoomPlayer->user_id = $userId;
                            $newRoomPlayer->is_host = 1;
                            $newRoomPlayer->is_ready = 0;
                            $newRoomPlayer->save();
                        }else if ($roomPlayerCount === 1) {
                            $newRoomPlayer = new RoomPlayer();
                            $newRoomPlayer->room_id = $roomId;
                            $newRoomPlayer->user_id = $userId;
                            $newRoomPlayer->is_host = 0;
                            $newRoomPlayer->is_ready = 0;
                            $newRoomPlayer->save();

                            //清空房主的房间信息缓存
                            $hostPlayer = RoomPlayer::find()->where(['room_id' => $room->id,'is_host'=>1])->one();
                            if($hostPlayer){
                                $cache = Yii::$app->cache;
                                $cacheKey = 'room_info_no_update_'.$hostPlayer->user_id;
                                $cache->set($cacheKey,false);
                            }
                        }
                        $success = true;
                    }else{
                        $msg = '房间已满/房间人数多于两个，错误！';
                    }
                }
            } else {
                $msg = '房间号错误';
            }
        }
        return [$success,$msg];
    }

    public static function exitRoom(){
        $success = false;
        $msg = '';
        $userId = Yii::$app->user->id;
        $roomPlayer = RoomPlayer::find()->where(['user_id'=>$userId])->one();
        if($roomPlayer){
            $cache = Yii::$app->cache;

            RoomPlayer::deleteAll(['user_id'=>$userId]);

            $cacheKey = 'room_info_no_update_'.$userId;
            $cache->set($cacheKey,false);

            //room如果有其他玩家(2p,自己是1P) 要对应改变其状态
            if($roomPlayer->is_host==1){
                $guestPlayer = RoomPlayer::find()->where(['room_id'=>$roomPlayer->room_id,'is_host'=>0])->one();
                if($guestPlayer){
                    $guestPlayer->is_host = 1;
                    $guestPlayer->is_ready = 0;
                    $guestPlayer->save();

                    //清空房主(此时的房主是原先的访客)的房间信息缓存
                    $cacheKey = 'room_info_no_update_'.$guestPlayer->user_id;
                    $cache->set($cacheKey,false);
                }

            }else{
                $hostPlayer = RoomPlayer::find()->where(['room_id'=>$roomPlayer->room_id,'is_host'=>1])->one();
                if($hostPlayer){
                    //清空房主的房间信息缓存
                    $cacheKey = 'room_info_no_update_'.$hostPlayer->user_id;
                    $cache->set($cacheKey,false);
                }
            }
            $success = true;
        }else{
            $msg = '不在房间中';
        }
        return [$success,$msg];
    }


    public static function getInfo($mode='all',$force=false){
        $success = false;
        $msg = '';
        $data = [
            'roomId' => -1,
            'isHost' => false,
            'hostPlayer' =>
            [
                'id' => -1,
                'username' => null,
                'name' => null
            ],
            'guestPlayer' =>
            [
                'id' => -1,
                'username' => null,
                'name' => null,
            ],
            'isReady' => false
        ];
        $userId = Yii::$app->user->id;
        $cache = Yii::$app->cache;
        $cacheKey  = 'room_info_no_update_'.$userId;  //存在则不更新房间信息
        $cache_data = $cache->get($cacheKey);
        if(!$force && $cache_data){
            $data = ['noUpdate'=>true];
            $success = true;
        }else {
            $roomPlayer = RoomPlayer::find()->where(['user_id' => $userId])->one();
            if ($roomPlayer) {
                $room = Room::find()->where(['id' => $roomPlayer->room_id])->one();
                if ($room) {
                    $data['roomId'] = $room->id;
                    $data['isHost'] = $roomPlayer->is_host==1;
                    if ($mode == 'all') {
                        $roomPlayers = RoomPlayer::find()->where(['room_id' => $room->id])->all();
                        if (count($roomPlayers) > 2) {
                            $msg = '房间中人数大于2，数据错误';
                        } else {
                            foreach ($roomPlayers as $player) {
                                if ($player->is_host) {
                                    $data['hostPlayer'] = [
                                        'id' => $player->user->id,
                                        'username' => $player->user->username,
                                        'name' => $player->user->nickname,
                                    ];
                                } else {
                                    $data['guestPlayer'] = [
                                        'id' => $player->user->id,
                                        'username' => $player->user->username,
                                        'name' => $player->user->nickname,

                                    ];
                                    $data['isReady'] = $player->is_ready == 1;
                                }
                            }

                            $game = Game::find()->where(['room_id' => $room->id])->one();
                            if ($game) {
                                //如果游戏已经开始 isGameStart => true
                                $data['gameStart'] = true;
                            }

                            $cache->set($cacheKey, true);
                            $success = true;
                        }
                    } else {
                        foreach ($data as $k => $v) {
                            if (!in_array($k, ['roomId'/*,'is_host'*/])) {
                                unset($data[$k]);
                            }
                        }
                        if (isset($data['roomId']) &&$data['roomId'] > 0)
                            $success = true;
                    }
                } else {
                    $msg = '房间不存在！';
                }
            } else {
                $msg = '你不在房间中!';
            }
        }
        return [$success,$msg,$data];
    }

    public static function doReady(){
        $success = false;
        $msg = '';
        $userId = Yii::$app->user->id;
        $roomPlayer = RoomPlayer::find()->where(['user_id'=>$userId])->one();
        if($roomPlayer){
            $room = Room::find()->where(['id'=>$roomPlayer->room_id])->one();
            if($room){
                $game = Game::find()->where(['room_id'=>$room->id,'status'=>Game::STATUS_PLAYING])->one();
                if(!$game){
                    $roomPlayerCount = RoomPlayer::find()->where(['room_id'=>$room->id])->count();
                    if($roomPlayerCount==2){
                        if($roomPlayer->is_host==0){
                            $roomPlayer->is_ready = $roomPlayer->is_ready==1?0:1;
                            if($roomPlayer->save()){

                                $cache = Yii::$app->cache;

                                //清空房主的房间信息缓存
                                $hostPlayer = RoomPlayer::find()->where(['room_id' => $room->id,'is_host'=>1])->one();
                                if($hostPlayer){

                                    $cacheKey = 'room_info_no_update_'.$hostPlayer->user_id;
                                    $cache->set($cacheKey,false);
                                }

                                $cacheKey = 'room_info_no_update_'.$userId;
                                $cache->set($cacheKey,false);

                                $success = true;
                            }else{
                                $msg = '保存错误';
                            }
                        }else{
                            $msg = '不是来宾角色';
                        }
                    }else{
                        $msg = '房间中人数不等于2，数据错误';
                    }
                }else{
                    $msg = '游戏已经开始';
                }
            }else{
                $msg = '房间不存在！';
            }
        }else{
            $msg = '你不在房间中，错误';
        }

        return [$success,$msg];
    }



}
