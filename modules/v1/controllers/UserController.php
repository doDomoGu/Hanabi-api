<?php

namespace app\modules\v1\controllers;

use Yii;

use app\models\UserAuth;
use app\models\User;

use app\components\H_JWT;

use yii\helpers\ArrayHelper;
use yii\web\Response;
use yii\rest\ActiveController;
use yii\filters\auth\QueryParamAuth;
use yii\filters\Cors;

class UserController extends ActiveController
{
    public function init(){

        $this->modelClass = User::className();
        parent::init();
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        //$behaviors['contentNegotiator']['formats'] = ['application/json' => Response::FORMAT_JSON];
        $behaviors['authenticator'] = [
            'class' => QueryParamAuth::className(),
            // 设置token名称，默认是access-token
            'tokenParam' => 'access_token',
            'optional' => [
                'index',
                //'view',
                'create',
                //'signup-test',
                //'view',
                'auth',
                'auth-user-info',
                //'auth-delete',
            ],

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

    public function actionAuth(){
        $return = [
            'success' => false,
            'error_msg' => ''
        ];
        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');
        if($username!='' && $password!=''){
            $user = User::findByUsername($username);
            if($user){
                if($user->password == md5($password)){
                    $return['result'] = true;
                    $token = H_JWT::generateToken($user->id);
                    $auth = new UserAuth();
                    $auth->user_id = $user->id;
                    $auth->token = $token;
                    $auth->expired_time = date('Y-m-d H:i:s',strtotime('+1 day'));
                    $auth->save();
                    $return['success'] = true;
                    $return['token'] = $token;
                    $return['user_id'] = $user->id;
                    $return['user_info'] = $user->attributes;

                }else{
                    $return['error_msg'] = '密码错误';
                }

            }else{
                $return['error_msg'] = '用户名错误';
            }
        }else{
            $return['error_msg'] = '提交数据错误';
        }
        return $return;
    }

    public function actionAuthDelete(){
        if(strtoupper($_SERVER['REQUEST_METHOD'])== 'OPTIONS'){
            return true;
        }
        $return = [
            'success' => false,
            'error_msg' => ''
        ];
        $token = Yii::$app->request->get('access_token');

        $auth = UserAuth::find()->where(['token'=>$token])->one();

        if($auth){
            //同步退出
            $res = UserAuth::find()->select('id,expired_time')->where(['user_id'=>Yii::$app->user->id])->all();
            $ids = [];
            foreach($res as $r){
                if($r->expired_time > date('Y-m-d H:i:s')){
                    $ids[] = $r->id;
                }
            }
            UserAuth::updateAll(['expired_time'=>date('Y-m-d H:i:s',strtotime('-1 second'))],['in','id',$ids]);


            $return['success'] = true;

        }else{
            $return['error_msg'] = 'Token数据错误(002)';
        }
        return $return;
    }

    /*public function actionAuthOption(){
        return true;
    }*/

    public function actionAuthUserInfo(){
        $return = [
            'success' => false,
            'error_msg' => ''
        ];
        $token = Yii::$app->request->get('access_token');

        $auth = UserAuth::find()->where(['token'=>$token])->one();

        if($auth) {
            if($auth->expired_time > date('Y-m-d H:i:s')){
                $user = User::find()->where(['id' => $auth->user_id])->one();
                if ($user){
                    $return['success'] = true;
                    $return['token'] = $token;
                    $return['tokenForceUpdate'] = true;
                    $return['user_id'] = $user->id;
                    $return['user_info'] = $user->attributes;
                    //$return = $user->attributes;
                }else{
                    $return['error_msg'] = 'User数据错误';
                }
            }else{
                $return['error_msg'] = 'Auth过期';
            }
        }else{
            $return['error_msg'] = 'Auth数据错误';
        }
        return $return;
    }




}
