<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use app\components\CurlRequest;

class GameController extends Controller
{
    public function actionCheck()
    {
        $url = Yii::$app->params['host'].'/v1/my-game/auto-play';
        $post_data = [];
//        $post_data['appid']       = '10';
//        $post_data['appkey']      = 'cmbohpffXVR03nIpkkQXaAA1Vf5nO4nQ';
//        $post_data['member_name'] = 'zsjs124';
//        $post_data['password']    = '123456';
//        $post_data['email']    = 'zsjs124@126.com';
        //$post_data = array();
        $res = CurlRequest::postRequest($url, $post_data);
        print_r($res);
        echo "\n";

    }


}
