<?php

namespace app\models;

use Yii;

class MyRoom {

    /*
     *  - 根据 当前玩家id 获取所在房间
     *      > 找不到所在房间 返回 [false, -1]
     *      > 找到房间 获得房间ID(roomId)
     *          * 对房间ID做检查 (Room::check(roomId))
     *          * 通过 返回 [true, roomId]
     *
     */
    public static function isIn(){
        $roomCount = (int) RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->count(); //房间数
        # error：一个玩家不应该在多个房间
        if($roomCount > 1){
            throw new \Exception(Room::EXCEPTION_IN_MANY_ROOM_MSG,Room::EXCEPTION_IN_MANY_ROOM_CODE);
        }
        # 不在房间内，返回[false, -1]
        if ($roomCount !== 1){
            return [false, -1];
        }
        #由roomPlayer得知，玩家在房间内，开始检查房间数据
        $roomId = (int) RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->one()->room_id; //房间ID
        Room::check($roomId);
        return [true, $roomId];
    }

    /*
     *
     */
    public static function getInfo() {
        list($isInRoom, $roomId) = MyRoom::isIn();
        # 不在房间中，返回房间ID：-1
        if(!$isInRoom){
            return [false, ['roomId' => -1]];
        }

        $data = ['roomId' => $roomId];
        $room = Room::getInfo($roomId);
        $data['hostPlayer'] = $hostPlayer = $room->hostPlayer;
        $data['guestPlayer'] = $guestPlayer = $room->guestPlayer;
        /*if($hostPlayer && $hostPlayer->user->id == Yii::$app->user->id){
            $isHost = true;
        }else if($guestPlayer && $guestPlayer->user->id == Yii::$app->user->id) {
            $isHost = false;
        }
        if ($guestPlayer) {
            $isReady = $guestPlayer->is_ready > 0;
        }*/
        $data['isHost'] = $hostPlayer && $hostPlayer->user->id == Yii::$app->user->id;
        $data['isReady'] = $guestPlayer && $guestPlayer->is_ready > 0;
        return [true, $data];
    }

}