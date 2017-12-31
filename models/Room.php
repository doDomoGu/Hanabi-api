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
            //room如果有其他玩家(2p,自己是1P) 要对应改变其状态
            if($room_player->is_host==1){
                $guest_player = RoomPlayer::find()->where(['room_id'=>$room_player->room_id,'is_host'=>0])->one();
                if($guest_player){
                    $guest_player->is_host = 1;
                    $guest_player->is_ready = 0;
                    $guest_player->save();
                }
            }
            RoomPlayer::deleteAll(['user_id'=>$user_id]);
            $success = true;
        }else{
            $msg = '不在房间中';
        }
        return [$success,$msg];
    }


    public static function getInfo(){
        $success = false;
        $msg = '';
        $data = [
            'host_player'=>
            [
                'id'=>0,
                'username'=>'',
                'name'=>''
            ],
            'guest_player'=>
            [
                'id'=>0,
                'username'=>'',
                'name'=>'',
                'is_ready'=>false
            ],
        ];
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $room = Room::find()->where(['id'=>$room_player->room_id])->one();
            if($room){
                $room_players = RoomPlayer::find()->where(['room_id'=>$room->id])->all();
                if(count($room_players)>2){
                    $msg = '房间中人数大于2，数据错误';
                }else{
                    foreach($room_players as $player){
                        if($player->is_host){
                            $data['host_player'] = [
                                'id'=>$player->user->id,
                                'username'=>$player->user->username,
                                'name'=>$player->user->nickname,
                            ];
                        }else{
                            $data['guest_player'] = [
                                'id'=>$player->user->id,
                                'username'=>$player->user->username,
                                'name'=>$player->user->nickname,
                                'is_ready'=>$player->is_ready==1
                            ];
                        }
                    }
                    $success = true;
                }
            }else{
                $msg = '房间不存在！';
            }
        }else{
            $msg = '你不在房间中!';
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
