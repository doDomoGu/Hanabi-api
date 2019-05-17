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
            'code' => 50004
        ],
        'do_start_not_host_player' => [
            'msg' => '开始游戏操作时，操作玩家不是主机玩家',
            'code' => 50005
        ],
        'do_start_guest_player_not_ready' => [
            'msg' => '开始游戏操作时，客机玩家没有准备',
            'code' => 50006
        ],
        'do_start_create_game_failure' => [
            'msg' => '开始游戏操作时，创建游戏失败',
            'code' => 50007
        ],
        'game_card_library_init_but_has_card' => [
            'msg' => '初始化游戏牌库时，已经有卡牌存在',
            'code' => 50008
        ],
        'game_card_library_init_wrong_card_total_num' => [
            'msg' => '初始化游戏牌库后，总卡牌数不对',
            'code' => 50009
        ],

    ];

}