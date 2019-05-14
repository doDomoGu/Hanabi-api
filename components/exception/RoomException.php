<?php
namespace app\components\exception;

class RoomException extends MyException {

    public static $exception = [
        'not_found' => [
            'msg' => '房间不存在',
            'code' => 10001
        ],
        'wrong_player_number' => [
            'msg' => '房间人数错误',
            'code' => 10013
        ],
        'no_host_player' => [
            'msg' => '房间内至少有一个玩家是主机',
            'code' => 10004
        ],
        'player_not_found' => [
            'msg' => '对应的玩家找不到',
            'code' => 10012
        ],
        /*'wrong_player_number' => [
            'msg' => '房间人数错误',
            'code' => 10013
        ],
        'wrong_player_number' => [
            'msg' => '房间人数错误',
            'code' => 10013
        ],*/
    ];

}