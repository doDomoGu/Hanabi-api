<?php
namespace app\components;

use Yii;
use yii\base\Component;

class CurlRequest extends Component {


    /**
     * 模拟post进行url请求
     * @param string $url
     * @param array $post_data
     */
    public static function postRequest($url = '', $post_data = array()) {
        if (empty($url) ) {
            return false;
        }

        if(!empty($post_data)){
            $o = "";
            foreach ( $post_data as $k => $v )
            {
                $o.= "$k=" . urlencode( $v ). "&" ;
            }
            $post_data = substr($o,0,-1);
        }else{
            $post_data = '';
        }


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


    /**
     * 模拟get进行url请求
     * @param string $url
     * @param array $get_data
     */
    public static function getRequest($url = '', $get_data = array()) {
        if (empty($url) ) {
            return false;
        }

        if(!empty($get_data)){
            $o = "";
            foreach ( $get_data as $k => $v )
            {
                $o.= "$k=" . urlencode( $v ). "&" ;
            }
            $param = substr($o,0,-1);
        }else{
            $param = '';
        }


        if($param!='') {
            $url .= '?'.$param;
        }
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$url);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        $data = curl_exec($ch);//运行curl
        curl_close($ch);

        return $data;
    }

}