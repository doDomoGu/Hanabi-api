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
            'msg' => '房间内玩家非空，但没有主机玩家',
            'code' => 10003
        ],
        'no_guest_player' => [
            'msg' => '房间内有两个玩家，但没有客机玩家',
            'code' => 10004
        ],
        'host_player_not_found' => [
            'msg' => '对应的主机玩家找不到',
            'code' => 10005
        ],
        'guest_player_not_found' => [
            'msg' => '对应的客机玩家找不到',
            'code' => 10006
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