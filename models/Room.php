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

    public static function enter($room_id){
        $success = false;
        $msg = '';
        $user_id = Yii::$app->user->id;
        $is_in_room = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($is_in_room){
           $msg = '已经在房间中';
        }else{
            $room = Room::find()->where(['id' => $room_id])->one();
            if ($room) {
                if ($room->password != '') {
                    $msg = '房间被锁住了';
                } else {
                    $room_player_count = (int)RoomPlayer::find()->where(['room_id' => $room->id])->count();
                    if ($room_player_count<2){
                        if ($room_player_count === 0) {
                            $new_room_player = new RoomPlayer();
                            $new_room_player->room_id = $room_id;
                            $new_room_player->user_id = $user_id;
                            $new_room_player->is_host = 1;
                            $new_room_player->is_ready = 0;
                            $new_room_player->save();
                        }else if ($room_player_count === 1) {
                            $new_room_player = new RoomPlayer();
                            $new_room_player->room_id = $room_id;
                            $new_room_player->user_id = $user_id;
                            $new_room_player->is_host = 0;
                            $new_room_player->is_ready = 0;
                            $new_room_player->save();

                            //清空房主的房间信息缓存
                            $host_player = RoomPlayer::find()->where(['room_id' => $room->id,'is_host'=>1])->one();
                            if($host_player){
                                $cache = Yii::$app->cache;
                                $cache_key = 'room_info_no_update_'.$host_player->user_id;
                                $cache->set($cache_key,false);
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

    public static function isInRoom(){
        $success = false;
        $msg = '';
        $room_id = -1;
        $is_host = -1;
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $room_id = $room_player->room_id;
            $is_host = $room_player->is_host==1;
            $success = true;
        }else{
            $msg = '不在房间中';
        }

        return [$success,$msg,['room_id'=>$room_id,'is_host'=>$is_host]];
    }


    public static function exitRoom(){
        $success = false;
        $msg = '';
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $cache = Yii::$app->cache;

            //room如果有其他玩家(2p,自己是1P) 要对应改变其状态
            if($room_player->is_host==1){
                $guest_player = RoomPlayer::find()->where(['room_id'=>$room_player->room_id,'is_host'=>0])->one();
                if($guest_player){
                    $guest_player->is_host = 1;
                    $guest_player->is_ready = 0;
                    $guest_player->save();

                    //清空房主(此时的房主是原先的访客)的房间信息缓存
                    $cache_key = 'room_info_no_update_'.$guest_player->user_id;
                    $cache->set($cache_key,false);
                }
            }
            RoomPlayer::deleteAll(['user_id'=>$user_id]);

            $cache_key = 'room_info_no_update_'.$user_id;
            $cache->set($cache_key,false);

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
            'room_id' => -1,
            'is_host' => false,
            'host_player' =>
            [
                'id' => -1,
                'username' => null,
                'name' => null
            ],
            'guest_player' =>
            [
                'id' => -1,
                'username' => null,
                'name' => null,
            ],
            'is_ready' => false
        ];
        $user_id = Yii::$app->user->id;
        $cache = Yii::$app->cache;
        $cache_key  = 'room_info_no_update_'.$user_id;  //存在则不更新房间信息
        $cache_data = $cache->get($cache_key);
        if(!$force && $cache_data){
            $data = ['no_update'=>true];
            $success = true;
        }else {
            $room_player = RoomPlayer::find()->where(['user_id' => $user_id])->one();
            if ($room_player) {
                $room = Room::find()->where(['id' => $room_player->room_id])->one();
                if ($room) {
                    $data['room_id'] = $room->id;
                    $data['is_host'] = $room_player->is_host;
                    if ($mode == 'all') {
                        $room_players = RoomPlayer::find()->where(['room_id' => $room->id])->all();
                        if (count($room_players) > 2) {
                            $msg = '房间中人数大于2，数据错误';
                        } else {
                            /*$cache = Yii::$app->cache;
                            if ($room_player->is_host) {
                                $cache_key = 'room_info_' . $room->id . '_1_no_update';  //存在则不更新游戏信息
                            } else {
                                $cache_key = 'room_info_' . $room->id . '_0_no_update';  //存在则不更新游戏信息
                            }
                            $cache_data = $cache->get($cache_key);
                            if (!$force && $cache_data) {
                                $data = ['no_update' => true];
                            } else {*/
                                foreach ($room_players as $player) {
                                    if ($player->is_host) {
                                        $data['host_player'] = [
                                            'id' => $player->user->id,
                                            'username' => $player->user->username,
                                            'name' => $player->user->nickname,
                                        ];
                                    } else {
                                        $data['guest_player'] = [
                                            'id' => $player->user->id,
                                            'username' => $player->user->username,
                                            'name' => $player->user->nickname,

                                        ];
                                        $data['is_ready'] = $player->is_ready == 1;
                                    }
                                }
                                $cache->set($cache_key, true);
                            /*}*/
                            $success = true;
                        }
                    } else {
                        foreach ($data as $k => $v) {
                            if (!in_array($k, ['room_id'/*,'is_host'*/])) {
                                unset($data[$k]);
                            }
                        }
                        if ($data['room_id'] > 0)
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
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $room = Room::find()->where(['id'=>$room_player->room_id])->one();
            if($room){
                $game = Game::find()->where(['room_id'=>$room->id,'status'=>Game::STATUS_PLAYING])->one();
                if(!$game){
                    $roomPlayerCount = RoomPlayer::find()->where(['room_id'=>$room->id])->count();
                    if($roomPlayerCount==2){
                        if($room_player->is_host==0){
                            $room_player->is_ready = $room_player->is_ready==1?0:1;
                            if($room_player->save()){

                                $cache = Yii::$app->cache;

                                //清空房主的房间信息缓存
                                $host_player = RoomPlayer::find()->where(['room_id' => $room->id,'is_host'=>1])->one();
                                if($host_player){

                                    $cache_key = 'room_info_no_update_'.$host_player->user_id;
                                    $cache->set($cache_key,false);
                                }

                                $cache_key = 'room_info_no_update_'.$user_id;
                                $cache->set($cache_key,false);

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
