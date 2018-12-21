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

    const EXCEPTION_NOT_FOUND_CODE  = 10001;
    const EXCEPTION_NOT_FOUND_MSG   = '房间不存在';
    const EXCEPTION_PLAYER_OVER_LIMIT_CODE  = 10002;
    const EXCEPTION_PLAYER_OVER_LIMIT_MSG   = '房间人数超过限制（大于2人）';
    const EXCEPTION_IN_MANY_ROOM_CODE  = 10003;
    const EXCEPTION_IN_MANY_ROOM_MSG   = '一个玩家在多个房间内';
    const EXCEPTION_NO_HOST_PLAYER_CODE  = 10004;
    const EXCEPTION_NO_HOST_PLAYER_MSG   = '房间内至少有一个玩家是主机';
    const EXCEPTION_EXIT_NOT_IN_ROOM_CODE  = 10005;
    const EXCEPTION_EXIT_NOT_IN_ROOM_MSG   = '退出操作，但是不在房间内';
    const EXCEPTION_EXIT_DELETE_FAILURE_CODE  = 10006;
    const EXCEPTION_EXIT_DELETE_FAILURE_MSG   = '退出操作，删除玩家失败';
    const EXCEPTION_ENTER_HAS_IN_ROOM_CODE  = 10007;
    const EXCEPTION_ENTER_HAS_IN_ROOM_MSG   = '进入操作，但是已经在房间中';
    const EXCEPTION_ENTER_PLAYER_FULL_CODE  = 10008;
    const EXCEPTION_ENTER_PLAYER_FULL_MSG   = '进入操作，但是房间已满';
    const EXCEPTION_DO_READY_NOT_IN_ROOM_CODE = 10009;
    const EXCEPTION_DO_READY_NOT_IN_ROOM_MSG  = '准备操作，但是不在房间内';
    const EXCEPTION_DO_READY_NOT_GUEST_PLAYER_CODE = 10010;
    const EXCEPTION_DO_READY_NOT_GUEST_PLAYER_MSG  = '准备操作，但是不是客机玩家';
    const EXCEPTION_DO_READY_FAILURE_CODE = 10011;
    const EXCEPTION_DO_READY_FAILURE_MSG  = '准备操作，失败';
    const EXCEPTION_PLAYER_NOT_FOUND_CODE = 10012;
    const EXCEPTION_PLAYER_NOT_FOUND_MSG  = '对应的玩家找不到';

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

    // 房间中的主机玩家
    public function getHostPlayer()
    {
        return $this->hasOne(RoomPlayer::className(), ['room_id' => 'id'])->where(['is_host' => 1]);
    }

    // 房间中的客机玩家
    public function getGuestPlayer()
    {
        return $this->hasOne(RoomPlayer::className(), ['room_id' => 'id'])->where(['is_host' => 0]);
    }

    # 检查当前玩家是否在房间中
    # 在房间内，检查房间内部数据是否正常，返回[true,room对象]
    # 不在房间内，返回[false,null]
    public static function isInRoom(){

        $roomCount = RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->count();

        if($roomCount > 1){
            throw new \Exception(Room::EXCEPTION_IN_MANY_ROOM_MSG,Room::EXCEPTION_IN_MANY_ROOM_CODE);
        }

        if ($roomCount == 0){
            return [false, null];
        }

        #由roomPlayer得知，玩家在房间内，开始检查房间数据
        $roomPlayer = RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->one();

        $room = Room::check($roomPlayer->room_id);

        return [true, $room];
    }

    #检查房间状态（非空，至少有一个主机玩家）
    private static function check($roomId){
        $room = Room::find()->where(['id'=>$roomId])->one();

        #房间不存在，返回异常
        if(!$room){
            throw new \Exception(Room::EXCEPTION_NOT_FOUND_MSG,Room::EXCEPTION_NOT_FOUND_CODE);
        }

        $roomPlayersCount = RoomPlayer::find()->where(['room_id'=>$roomId])->count();

        #房间玩家人数大于2，返回异常
        if( $roomPlayersCount > 2 ){
            throw new \Exception(Room::EXCEPTION_PLAYER_OVER_LIMIT_MSG,Room::EXCEPTION_PLAYER_OVER_LIMIT_CODE);
        }

        #玩家ID 找不到对应玩家 （主机玩家）
        if($room->hostPlayer){
            if(!$room->hostPlayer->user){
                throw new \Exception(Room::EXCEPTION_PLAYER_NOT_FOUND_MSG,Room::EXCEPTION_PLAYER_NOT_FOUND_CODE);
            }
            $hostPlayer = $room->hostPlayer;
        }

        #玩家ID 找不到对应玩家 （客机玩家）
        if($room->guestPlayer){
            if(!$room->guestPlayer->user){
                throw new \Exception(Room::EXCEPTION_PLAYER_NOT_FOUND_MSG,Room::EXCEPTION_PLAYER_NOT_FOUND_CODE);
            }
            $guestPlayer = $room->guestPlayer;
        }

        #没有主机玩家，返回异常
        if (!$hostPlayer) {
            throw new \Exception(Room::EXCEPTION_NO_HOST_PLAYER_MSG, Room::EXCEPTION_NO_HOST_PLAYER_CODE);
        }

        return $room;
    }


    public static function getInfo($roomId) {
        $room = Room::check($roomId);

        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

        $isHost = null;
        $isReady = null;

        if($hostPlayer && $hostPlayer->user->id == Yii::$app->user->id){
            $isHost = true;
        }else if($guestPlayer && $guestPlayer->user->id == Yii::$app->user->id) {
            $isHost = false;
        }

        if ($guestPlayer) {
            $isReady = $guestPlayer->is_ready > 0;
        }

        return [$room, [$hostPlayer, $guestPlayer, $isHost, $isReady]];
    }


    public static function myRoomInfo($mode='all',$force=false){

        $cache = Yii::$app->cache;

        $cacheKey  = 'room_info_no_update_'.Yii::$app->user->id;  //存在则不更新房间信息

        if(!$force) {

            if($cache->get($cacheKey)) {

                return ['noUpdate'=>true];

            }
        }

        list($isInRoom, $room) = Room::isInRoom();

        #不在房间中，返回房间ID：-1
        if(!$room){
            return ['roomId'=>-1];
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($room->id);

        if ($mode == 'all') {

            /*$game = Game::find()->where(['room_id' => $room->id])->one();
            if ($game) {
                //如果游戏已经开始 isGameStart => true
                $data['gameStart'] = true;
            }*/

            $cache->set($cacheKey, true);

            return [
                'roomId' => $room->id,
                'isHost' => $isHost,
                'isReady' => $isReady,
                'hostPlayer' => $hostPlayer ? [
                    'id' => $hostPlayer->user->id,
                    //'username' => $player->user->username,
                    'name' => $hostPlayer->user->nickname,
                ] : null,
                'guestPlayer' => $guestPlayer ? [
                    'id' => $guestPlayer->user->id,
                    //'username' => $player->user->username,
                    'name' => $guestPlayer->user->nickname,
                ] : null
            ];

        } else {
            return [
                'roomId' => $room->id
            ];
        }
    }

    public static function enter($roomId){
        $cache = Yii::$app->cache;

        list($isInRoom) = Room::isInRoom();

        if($isInRoom){
            throw new \Exception(Room::EXCEPTION_ENTER_HAS_IN_ROOM_MSG,Room::EXCEPTION_ENTER_HAS_IN_ROOM_CODE);
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

        if ($room->password != '') {
            //TODO 房间密码处理
            throw new \Exception('房间有密码',12345);
        }

        if($hostPlayer && $guestPlayer) {
            throw new \Exception(Room::EXCEPTION_ENTER_PLAYER_FULL_MSG,Room::EXCEPTION_ENTER_PLAYER_FULL_CODE);
        }

        if (!$hostPlayer) {
            #成为主机玩家
            $newRoomPlayer = new RoomPlayer();
            $newRoomPlayer->room_id = $roomId;
            $newRoomPlayer->user_id = Yii::$app->user->id;
            $newRoomPlayer->is_host = 1;
            $newRoomPlayer->is_ready = 0;
            $newRoomPlayer->save();
        }else {
            #成为客机玩家
            $newRoomPlayer = new RoomPlayer();
            $newRoomPlayer->room_id = $roomId;
            $newRoomPlayer->user_id = Yii::$app->user->id;
            $newRoomPlayer->is_host = 0;
            $newRoomPlayer->is_ready = 0;
            $newRoomPlayer->save();

            //清空房主的房间信息缓存
            $cacheKey = 'room_info_no_update_'.$hostPlayer->user->id;
            $cache->delete($cacheKey);
        }

        $roomListSysCacheKey  = 'room_list_lastupdated';
        $cache->set($roomListSysCacheKey, date('Y-m-d H:i:s'));

        return true;
    }

    public static function exitRoom(){

        list($isInRoom, $roomId) = Room::isInRoom();

        if(!$isInRoom){
            throw new \Exception(Room::EXCEPTION_EXIT_NOT_IN_ROOM_MSG,Room::EXCEPTION_EXIT_NOT_IN_ROOM_CODE);
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

        $rows_effect_count = RoomPlayer::deleteAll(['user_id'=>Yii::$app->user->id]);

        if( $rows_effect_count === 0 ){
            throw new \Exception(Room::EXCEPTION_EXIT_DELETE_FAILURE_MSG,Room::EXCEPTION_EXIT_DELETE_FAILURE_CODE);
        }

        $cache = Yii::$app->cache;
        $cacheKey = 'room_info_no_update_'.Yii::$app->user->id;
        $cache->delete($cacheKey);


        #原本是主机玩家  要对应改变客机玩家的状态 （原本的客机玩家变成这个房间的主机玩家，准备状态清空）

        if($isHost){
            if($guestPlayer){
                $guestPlayer->is_host = 1;
                $guestPlayer->is_ready = 0;
                $guestPlayer->save();

                //清空房主(此时的房主是原先的访客)的房间信息缓存
                $cacheKey = 'room_info_no_update_'.$guestPlayer->user_id;
                $cache->delete($cacheKey);
            }

        }else{
            if($hostPlayer){
                //清空房主的房间信息缓存
                $cacheKey = 'room_info_no_update_'.$hostPlayer->user_id;
                $cache->delete($cacheKey);
            }
        }

        $roomListSysCacheKey  = 'room_list_lastupdated';
        $cache->set($roomListSysCacheKey, date('Y-m-d H:i:s'));

        return true;
    }

    public static function doReady(){

        list($isInRoom, $roomId) = Room::isInRoom();

        if(!$isInRoom){
            throw new \Exception(Room::EXCEPTION_DO_READY_NOT_IN_ROOM_MSG,Room::EXCEPTION_DO_READY_NOT_IN_ROOM_CODE);
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

        if($isHost !== false){
            throw new \Exception(Room::EXCEPTION_DO_READY_NOT_GUEST_PLAYER_MSG,Room::EXCEPTION_DO_READY_NOT_GUEST_PLAYER_CODE);
        }

        /*
         * TODO 检查游戏状态
         * list($isInGame) = Game::isInGame();
        $game = Game::find()->where(['room_id'=>$room->id,'status'=>Game::STATUS_PLAYING])->one();*/


        $guestPlayer->is_ready = $guestPlayer->is_ready > 0 ? 0 : 1;
        if($guestPlayer->save()){
            $cache = Yii::$app->cache;

            //清空房主的房间信息缓存
            $cacheKey = 'room_info_no_update_'.$hostPlayer->user->id;
            $cache->delete($cacheKey);

            $cacheKey = 'room_info_no_update_'.$guestPlayer->user->id;
            $cache->delete($cacheKey);
        }else{
            throw new \Exception(Room::EXCEPTION_DO_READY_FAILURE_MSG,Room::EXCEPTION_DO_READY_FAILURE_CODE);
        }
        return true;
    }



}
