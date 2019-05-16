<?php
namespace app\components\exception;

class MyGameException extends MyException {

    public static $exception = [
        'not_in_room' => [
            'msg' => '查询游戏信息时，不在房间中',
            'code' => 50001
        ],
    ];

}