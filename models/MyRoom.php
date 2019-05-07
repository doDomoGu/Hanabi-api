<?php

namespace app\models;

use Yii;

class MyRoom {

    /*
     *  - 根据 当前玩家id 获取所在房间ID
     *      > 找不到所在房间 返回 [false, -1]
     *      > 找到房间 获得房间ID(roomId)
     *          * 对房间ID做检查 (Room::check(房间Id))
     *          * 通过 返回 [true, 房间Id]
     *
     */
    public static function isIn(){
        $roomCount = (int) RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->count(); //所在房间数量
        # error：一个玩家不应该在多个房间
        if($roomCount > 1){
            throw new \Exception(Room::EXCEPTION_IN_MANY_ROOM_MSG,Room::EXCEPTION_IN_MANY_ROOM_CODE);
        }
        # 不在房间内，返回[false, -1]
        if ($roomCount === 0){
            return [false, -1];
        }
        # 获得所在房间ID
        $roomId = (int) RoomPlayer::find()->where(['user_id' => Yii::$app->user->id])->one()->room_id; //房间ID
        # 检查房间数据
        $room = Room::getOne($roomId);
        Room::check($room);
        # 返回 [true, 房间Id]
        return [true, $roomId];
    }

    /*
     * 获得所在房间信息
     */
    public static function getInfo() {
        $roomInfo = [];
        # 检查是否在房间内，并返回房间ID
        list($isInRoom, $roomInfo['roomId']) = MyRoom::isIn();
        # $isInRoom = false, 不在房间里 只返回 roomId = -1
        # $isInRoom = true, 在房间中，根据ID获取详细的房间信息
        if($isInRoom){
            $room = Room::getInfo($roomInfo['roomId']);
            $hostPlayer = $room->hostPlayer;
            $guestPlayer = $room->guestPlayer;
            $roomInfo['hostPlayer'] = $hostPlayer ? [
                'id' => $hostPlayer->user->id,
                'name' => $hostPlayer->user->nickname,
            ] : null;
            $roomInfo['guestPlayer'] = $guestPlayer ? [
                'id' => $guestPlayer->user->id,
                'name' => $guestPlayer->user->nickname,
            ] : null;
            $roomInfo['isHost'] = $hostPlayer && $hostPlayer->user->id == Yii::$app->user->id;
            $roomInfo['isReady'] = $guestPlayer && $guestPlayer->is_ready > 0;
        }

        return $roomInfo;
    }

}