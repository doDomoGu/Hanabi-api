<?php
namespace app\components\exception;

class MyException extends \Exception {

    public static $exception = [];

    public static function t($item){
        throw new \Exception(static::$exception[$item]['msg'],static::$exception[$item]['code']);
    }

}