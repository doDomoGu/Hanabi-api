<?php
$v = 'v1';
return [

    [
        'class' => 'yii\rest\UrlRule',
        'controller' => [$v.'/user',$v.'/room'],
        'pluralize' => false
    ],


    'POST '.$v.'/my-room/enter' => $v.'/my-room/enter',  //进入房间 （是否有位置， 如有密码，密码验证）
    'POST '.$v.'/my-room/exit' => $v.'/my-room/exit',  //退出房间
    //'POST '.$v.'/my-room/is-in-room' => $v.'/my-room/is-in-room',  //判断是否在房间中  如是返回房间i
    'POST '.$v.'/my-room/get-info' => $v.'/my-room/get-info',  //判断是否在房间中  如是返回房间i
    'POST '.$v.'/my-room/do-ready' => $v.'/my-room/do-ready',  //判断是否在房间中  如是返回房间i


    'POST '.$v.'/my-game/start' => $v.'/my-game/start',  //开始游戏
    'POST '.$v.'/my-game/get-info' => $v.'/my-game/get-info',  //开始游戏
    //'POST '.$v.'/my-game/is-in-game' => $v.'/my-game/is-in-game',  //开始游戏
    'POST '.$v.'/my-game/end' => $v.'/my-game/end',  //开始游戏
    'POST '.$v.'/my-game/do-discard' => $v.'/my-game/do-discard',  //弃牌
    'POST '.$v.'/my-game/do-play' => $v.'/my-game/do-play',  //出牌
    'POST '.$v.'/my-game/do-cue' => $v.'/my-game/do-cue',  //提示




    'POST '.$v.'/auth' => $v.'/user/auth',  //提交登录 生成token
    'DELETE '.$v.'/auth' => $v.'/user/auth-delete', //退出 清空token
    //'POST v1/auth-delete' => 'v1/user/auth-delete',  //退出 清空token

    'OPTIONS '.$v.'/auth' => $v.'/user/auth-delete',
    'GET '.$v.'/auth' => $v.'/user/auth-user-info', //读取用户信息（自动登录）



];