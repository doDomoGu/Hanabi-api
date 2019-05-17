<?php
namespace app\components\exception;

class MyGameException extends MyException {

    public static $exception = [
        'not_in_room' => [
            'msg' => '查询游戏信息时，不在房间中',
            'code' => 50001
        ],
        'do_start_not_in_room' => [
            'msg' => '开始游戏操作时，不在房间中',
            'code' => 50002
        ],
        'do_start_game_has_started' => [
            'msg' => '开始游戏操作时，游戏已经开始了',
            'code' => 50003
        ],
        'do_start_wrong_players' => [
            'msg' => '开始游戏操作时，房间内游戏玩家信息错误',
            'code' => 50003
        ],
        'do_start_not_host_players' => [
            'msg' => '开始游戏操作时，操作玩家不是主机玩家',
            'code' => 50004
        ],
    ];

}