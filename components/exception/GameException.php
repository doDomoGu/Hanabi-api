<?php
namespace app\components\exception;

class GameException extends MyException {

    public static $exception = [
        'wrong_players' => [
            'msg' => '游戏玩家错误',
            'code' => 30001
        ],
        'guest_player_not_ready' => [
            'msg' => '客机玩家不是准备状态',
            'code' => 30002
        ],
        'wrong_chance_num' => [
            'msg' => '剩余机会数应大于0',
            'code' => 30003
        ],
        'wrong_cue_num' => [
            'msg' => '剩余提示数应大于0',
            'code' => 30004
        ],
        'wrong_game_card_total_num' => [
            'msg' => '总卡牌数不正确',
            'code' => 30005
        ],

    ];

}