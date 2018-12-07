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

    /*// 房间中的主机玩家
    public function getHostPlayer()
    {
        return $this->hasOne(RoomPlayer::className(), ['room_id' => 'id'])->where(['is_host' => 1]);
    }

    // 房间中的客机玩家
    public function getGuestPlayer()
    {
        return $this->hasOne(RoomPlayer::className(), ['room_id' => 'id'])->where(['is_host' => 0]);
    }*/

    # 检查当前玩家是否在房间中， 且房间状态是否正确
    # 返回房间ID
    protected static function isInRoom(){

        $roomPlayers = RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->all();

        if(count($roomPlayers) > 1){
            throw new \Exception(Room::EXCEPTION_IN_MANY_ROOM_MSG,Room::EXCEPTION_IN_MANY_ROOM_CODE);
        }

        if (count($roomPlayers) == 0){
            return [false, null];
        }

        $roomPlayer = $roomPlayers[0];

        //RoomPlayer有在房间中记录，可是room_id对应房间却不存在，抛出异常
        if(!$roomPlayer->room){
            throw new \Exception(Room::EXCEPTION_NOT_FOUND_MSG,Room::EXCEPTION_NOT_FOUND_CODE);
        }

        return [true, $roomPlayer->room->id];
    }


    protected static function getInfo($roomId) {
        $room = Room::find()->where(['id' => $roomId])->one();

        #房间不存在，返回异常
        if(!$room){
            throw new \Exception(Room::EXCEPTION_NOT_FOUND_MSG,Room::EXCEPTION_NOT_FOUND_CODE);
        }

        $roomPlayers = RoomPlayer::find()->where(['room_id'=>$room->id])->all();

        #房间玩家人数大于2，返回异常
        if( count($roomPlayers) > 2 ){
            throw new \Exception(Room::EXCEPTION_PLAYER_OVER_LIMIT_MSG,Room::EXCEPTION_PLAYER_OVER_LIMIT_CODE);
        }

        $hostPlayer = null;
        $guestPlayer = null;
        $isHost = null;
        $isReady = null;

        if( count($roomPlayers) > 0 ) {

            foreach ($roomPlayers as $player) {
                if ($player->is_host > 0) {
                    $hostPlayer = $player;
                } else {
                    $guestPlayer = $player;
                }
            }

            #没有主机玩家，返回异常
            if (!$hostPlayer) {
                throw new \Exception(Room::EXCEPTION_NO_HOST_PLAYER_MSG, Room::EXCEPTION_NO_HOST_PLAYER_CODE);
            }

            if($hostPlayer->user->id == Yii::$app->user->id){
                $isHost = true;
            }else if($guestPlayer && $guestPlayer->user->id == Yii::$app->user->id) {
                $isHost = false;
            }

            if (!$isHost) {
                $isReady = $guestPlayer->is_ready > 0;
            }
        }


        return [$room, $hostPlayer, $guestPlayer, $isHost, $isReady];
    }

    public static function enter($roomId){
        list($isInRoom) = Room::isInRoom();

        if($isInRoom){
            throw new \Exception(Room::EXCEPTION_ENTER_HAS_IN_ROOM_MSG,Room::EXCEPTION_ENTER_HAS_IN_ROOM_CODE);
        }

        list($room, $hostPlayer, $guestPlayer, $isHost, $isReady) = Room::getInfo($roomId);

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
            $cache = Yii::$app->cache;
            $cacheKey = 'room_info_no_update_'.$hostPlayer->user->id;
            $cache->delete($cacheKey);
        }

        return true;
    }

    public static function getList($force = false) {
        $success = false;
        //$msg = '';
        $data = [
            'list' => []
        ];

        $userId = Yii::$app->user->id;
        $cache = Yii::$app->cache;
        $userCacheKey  = 'room_list_lastupdated_'.$userId;
        $sysCacheKey  = 'room_list_lastupdated';
        $userLastUpdated = $cache->get($userCacheKey); // 用户的房间列表的最后缓存更新时间
        $sysLastUpdated = $cache->get($sysCacheKey); // 系统的房间列表的最后缓存更新时间
        //如果 用户的最后缓存更新时间 < 系统的最后缓存更新时间  就要重新读取数据 并将 用户时间更新为系统时间
        if (!$force && $userLastUpdated!='' && $userLastUpdated >= $sysLastUpdated) {
            $data = ['noUpdate'=>true];
            $success = true;
        } else {
            $rooms = Room::find()->all();
            $list = [];
            foreach($rooms as $r){
                $roomPlayerCount = RoomPlayer::find()->where(['room_id'=>$r->id])->count();

                $list[] = [
                    'id'        => $r->id,
                    'title'     => $r->title,
                    'isLocked'    => $r->password!='',
                    'playerCount' => (int) $roomPlayerCount
                ];
            };
            $data['list'] = $list;
            $data['lastupdated'] = $sysLastUpdated;
            $cache->set($userCacheKey,$sysLastUpdated);
            $success = true;
        }
        //return [$success,$msg,$data];
        return [$success,$data];
    }

    public static function exitRoom(){
        list($isInRoom, $roomId) = Room::isInRoom();

        if(!$isInRoom){
            throw new \Exception(Room::EXCEPTION_EXIT_NOT_IN_ROOM_MSG,Room::EXCEPTION_EXIT_NOT_IN_ROOM_CODE);
        }

        list($room, $hostPlayer, $guestPlayer, $isHost, $isReady) = Room::getInfo($roomId);

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

        return true;
    }

    public static function info($mode='all',$force=false){

        $cache = Yii::$app->cache;

        $cacheKey  = 'room_info_no_update_'.Yii::$app->user->id;  //存在则不更新房间信息

        if(!$force) {

            if($cache->get($cacheKey)) {

                return ['noUpdate'=>true];

            }
        }

        #判断是否在房间中， 获得房间ID
        list($isInRoom, $roomId) = Room::isInRoom();

        #不在房间中，返回房间ID：-1
        if(!$isInRoom){
            return ['roomId'=>-1];
        }

        list($room, $hostPlayer, $guestPlayer, $isHost, $isReady) = Room::getInfo($roomId);

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

    public static function doReady(){

        list($isInRoom, list($room, $isHost, $isReady)) = Room::isInRoom();

        if(!$isInRoom){
            return [false, '你不在房间中'];
        }

        list($isInGame) = Game::isInGame();






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


        return [$success,$msg];
    }



}
