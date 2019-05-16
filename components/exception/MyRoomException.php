<?php
namespace app\components\exception;

class MyRoomException extends MyException {

    public static $exception = [
        'in_too_many_rooms' => [
            'msg' => '一个玩家在多个房间内',
            'code' => 20001
        ],
        'do_enter_already_in_room' => [
            'msg' => '进入操作，但是已经在房间中',
            'code' => 20002
        ],
        'do_enter_locked_room_by_wrong_password' => [
            'msg' => '进入操作，有密码的房间，输入了错误的密码',
            'code' => 20003
        ],
        'do_enter_full_room' => [
            'msg' => '进入操作，但是房间已满',
            'code' => 20004
        ],
        'do_exit_not_in_room' => [
            'msg' => '退出操作，但是不在房间内',
            'code' => 20005
        ],
        'do_exit_failure' => [
            'msg' => '退出操作，删除玩家失败',
            'code' => 20006
        ],
        'do_ready_not_in_room' => [
            'msg' => '准备操作，但是不在房间内',
            'code' => 20007
        ],
        'do_ready_not_guest_player' => [
            'msg' => '准备操作，但是不是客机玩家',
            'code' => 20008
        ],
        'do_ready_failure' => [
            'msg' => '准备操作，失败',
            'code' => 20009
        ]

    ];

}