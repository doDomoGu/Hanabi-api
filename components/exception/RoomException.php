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
        ]
    ];

}