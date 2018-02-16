<?php

namespace app\commands;

use Yii;
use yii\console\Controller;

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
        $res = $this->request_post($url, $post_data);
        print_r($res);

    }

    /**
    * 模拟post进行url请求
    * @param string $url
    * @param array $post_data
    */
    private function request_post($url = '', $post_data = array()) {
        if (empty($url) ) {
            return false;
        }

        $o = "";
        foreach ( $post_data as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);

        $postUrl = $url;
        $curlPost = $post_data;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);

        return $data;
    }
}
