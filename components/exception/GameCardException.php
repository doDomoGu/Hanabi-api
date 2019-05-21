<?php
namespace app\components\exception;

class GameCardException extends MyException {

    public static $exception = [
        'not_found' => [
            'msg' => '查询卡牌时，找不到对应卡牌',
            'code' => 60001
        ],
        'wrong_hands_type_ord' => [
            'msg' => '查询卡牌时，错误的手牌排序',
            'code' => 60002
        ],
        'do_discard_failure' => [
            'msg' => '弃牌失败',
            'code' => 60003
        ],
        'draw_card_library_empty' => [
            'msg' => '抓牌时，牌库空了',
            'code' => 60004
        ],
        'draw_card_hands_over_limit' => [
            'msg' => '抓牌时，手牌超过上限',
            'code' => 60005
        ],
    ];

}