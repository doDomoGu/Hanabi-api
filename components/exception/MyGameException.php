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
        'do_start_create_game_history_failure' => [
            'msg' => '开始游戏操作时，创建游戏记录失败',
            'code' => 50010
        ],
        'do_end_not_in_room' => [
            'msg' => '结束游戏操作时，不在房间中',
            'code' => 50011
        ],
        'do_end_not_in_game' => [
            'msg' => '结束游戏操作时，不在游戏中',
            'code' => 50012
        ],
        'do_end_not_host_player' => [
            'msg' => '结束游戏操作时，操作人不是主机玩家',
            'code' => 50013
        ],
        'do_operation_not_in_room' => [
            'msg' => '卡牌操作时，不在房间中',
            'code' => 50014
        ],
        'do_operation_not_in_game' => [
            'msg' => '卡牌操作时，不在游戏中',
            'code' => 50015
        ],
        'do_operation_not_player_round' => [
            'msg' => '卡牌操作时，不是该玩家操作的回合',
            'code' => 50015
        ],
    ];

}