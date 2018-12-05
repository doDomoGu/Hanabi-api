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




    private static function isInRoom(){

        $roomPlayer = RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->one();

        if (!$roomPlayer || !$roomPlayer->room){
            return [false, null];
        }

        $room = $roomPlayer->room;

        $isHost = $roomPlayer->is_host > 0;

        $isReady = !$isHost ? $roomPlayer->is_ready > 0 : null;

        return [true, [$room, $isHost, $isReady]];

    }

    public static function enter($roomId){
        list($isInRoom) = self::isInRoom();

        if($isInRoom){
            return [false, '已经在房间中'];
        }

        $room = Room::find()->where(['id' => $roomId])->one();

        if(!$room){
            return [false, '房间号错误'];
        }

        if ($room->password != '') {
            return [false, '房间被锁住了'];
        }

        $roomPlayerCount = (int) RoomPlayer::find()->where(['room_id' => $room->id])->count();

        if ($roomPlayerCount>1){
            return [false, '房间已满/房间人数多于两个，错误！'];
        }

        if ($roomPlayerCount === 0) {
            $newRoomPlayer = new RoomPlayer();
            $newRoomPlayer->room_id = $roomId;
            $newRoomPlayer->user_id = Yii::$app->user->id;
            $newRoomPlayer->is_host = 1;
            $newRoomPlayer->is_ready = 0;
            $newRoomPlayer->save();
        }else if ($roomPlayerCount === 1) {
            $newRoomPlayer = new RoomPlayer();
            $newRoomPlayer->room_id = $roomId;
            $newRoomPlayer->user_id = Yii::$app->user->id;
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

        return [true, null];
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

        list($isInRoom, list($room, $isHost, $isReady)) = self::isInRoom();

        if(!$isInRoom){
            return [false, '不在房间中'];
        }

        RoomPlayer::deleteAll(['user_id'=>Yii::$app->user->id]);

        $cache = Yii::$app->cache;
        $cacheKey = 'room_info_no_update_'.Yii::$app->user->id;
        $cache->set($cacheKey,false);

        //原本是主机玩家  要对应改变客机玩家的状态 （原本的客机玩家变成这个房间的主机玩家，准备状态清空）
        if($isHost){
            $guestPlayer = RoomPlayer::find()->where(['room_id'=>$room->id,'is_host'=>0])->one();
            if($guestPlayer){
                $guestPlayer->is_host = 1;
                $guestPlayer->is_ready = 0;
                $guestPlayer->save();

                //清空房主(此时的房主是原先的访客)的房间信息缓存
                $cacheKey = 'room_info_no_update_'.$guestPlayer->user_id;
                $cache->set($cacheKey,false);
            }

        }else{
            $hostPlayer = RoomPlayer::find()->where(['room_id'=>$room->id,'is_host'=>1])->one();
            if($hostPlayer){
                //清空房主的房间信息缓存
                $cacheKey = 'room_info_no_update_'.$hostPlayer->user_id;
                $cache->set($cacheKey,false);
            }
        }
        return [true, null];
    }


    public static function getInfo($mode='all',$force=false){

        $cache = Yii::$app->cache;

        $cacheKey  = 'room_info_no_update_'.Yii::$app->user->id;  //存在则不更新房间信息

        if(!$force) {

            $cacheData = $cache->get($cacheKey);

            if($cacheData) {

                return [true, ['noUpdate'=>true]];

            }
        }

        list($isInRoom, list($room,$isHost,$isReady)) = self::isInRoom();

        if(!$isInRoom) {
            return [false, null, '你不在房间中!'];
        }

        if ($mode == 'all') {



            $roomPlayers = RoomPlayer::find()->where(['room_id' => $room->id])->all();

            if (count($roomPlayers) > 2) {

                return [false, null, '房间中人数大于2，数据错误'];

            }

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
                    $data['isReady'] = $player->is_ready > 0;
                }
            }

            $game = Game::find()->where(['room_id' => $room->id])->one();
            if ($game) {
                //如果游戏已经开始 isGameStart => true
                $data['gameStart'] = true;
            }

            $data['roomId'] = $room->id;

            $data['isHost'] = $isHost;

            $cache->set($cacheKey, true);

            return [true, $data, null];

        } else {

            return [true, ['roomId'=>$room->id], null];

        }
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
