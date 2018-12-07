<?php

namespace app\modules\v1\controllers;

use app\components\MyQueryParamAuth;
use app\models\User;
use Yii;

use yii\helpers\ArrayHelper;
use yii\web\Response;
use yii\rest\ActiveController;
use yii\filters\auth\QueryParamAuth;
use yii\filters\Cors;

class MyActiveController extends ActiveController
{
    public $_code = 0;
    public $_data = null;
    public $_msg = null;


    public function init(){
        if($this->modelClass==NULL){
            $this->modelClass = 'null';
        }
        parent::init();
    }

    private function sendResponse($code = 0, $data = null, $msg = null){
        return [
            'code'  =>  $code,
            'data'  =>  $data,
            'msg'   =>  $msg
        ];
    }

    protected function sendException(\Exception $e){
        $code = $e->getCode();
        $code = $code === 0 ? 400 : $code;
        $msg = $e->getMessage();
        $errorData = [
            'code' => $code,
            'msg'  => $msg,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
        Yii::error($errorData);

        return $this->sendError($code,$msg);
    }

    protected function sendError($errorCode=0000,$errorMsg='未知错误'){
        return $this->sendResponse($errorCode, null, $errorMsg);
    }

    protected function sendSuccess($data=null) {
        return $this->sendResponse(0 , $data, null);
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        //$behaviors['contentNegotiator']['formats'] = ['application/json' => Response::FORMAT_JSON];
        $behaviors['authenticator'] = [
//            'class' => QueryParamAuth::className(),
            'class' => MyQueryParamAuth::className(),
            // 设置token名称，默认是access-token
            'tokenParam' => 'X-Token',
//            'tokenParam' => 'token',
            'optional' => [
                'option'
            ]
        ];

        $behaviors = ArrayHelper::merge([
            [
                'class' => Cors::className(),
            ],
        ], $behaviors);

        /*$behaviors['cors'] = [
            'class' => Cors::className(),
            'cors' => [
                'Origin' => ['http://localhost'],//定义允许来源的数组
                'Access-Control-Request-Method' => ['GET','POST','PUT','DELETE', 'HEAD', 'OPTIONS'],//允许动作的数组
            ],
            'actions' => [
                'index' => [
                    'Access-Control-Allow-Credentials' => true,
                ]
            ]
        ];*/
        return $behaviors;
    }

    //重写checkAccess 控制权限
    /*public function checkAccess($action, $model = null, $params = [])
    {

        throw new \yii\web\ForbiddenHttpException(sprintf('You can only %s articles that you\'ve created.', $action));

    }*/


    /**
     * @api {options} /{anything} 用来收集通过OPTIONS方式发出的请求  允许其请求
     * @apiName AllowOptions
     * @apiGroup MyActive
     *
     * @apiVersion 1.0.0
     *
     */
    public function actionOption(){
        $request = Yii::$app->request;
        if($request->isOptions){
            Yii::$app->getResponse()->getHeaders()->set('Allow', 'POST GET PUT');
            return $this->sendSuccess();
        }
    }
}
