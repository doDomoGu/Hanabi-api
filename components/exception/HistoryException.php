<?php
namespace app\components\exception;

class HistoryException extends MyException {

    public static $exception = [
        'create_one_failure' => [
            'msg' => '创建游戏记录失败',
            'code' => 30001
        ]

    ];

}