<?php

namespace app\modules\v1\controllers;

use app\components\MyQueryParamAuth;
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
            //'class' => QueryParamAuth::className(),
            'class' => MyQueryParamAuth::className(),
            // 设置token名称，默认是access-token
//            'tokenParam' => 'accessToken',
            'tokenParam' => 'X-Token',
            'optional' => [
                'index',
                //'view',
                'create',
                //'signup-test',
                //'view',
                'auth',
                'register',
                'auth-user-info',
                'admin-login',
                'admin-info',
                'admin-logout'
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


    /**
     * @apiDefine GroupUser
     *
     * 用户
     */


    /**
     * @api {post} /auth 登录验证
     * @apiName Auth
     * @apiGroup GroupUser
     *
     * @apiVersion 1.0.0
     *
     * @apiParam {string} username 用户名
     * @apiParam {string} password 密码
     *
     */

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
                    $return['userId'] = $user->id;
                    $return['userInfo'] = $user->attributes;

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


    /**
     * @api {post} /register 注册
     * @apiName Register
     * @apiGroup GroupUser
     *
     * @apiVersion 1.0.0
     *
     * @apiParam {string} username 用户名
     * @apiParam {string} password 密码
     *
     */

    public function actionRegister(){
        $return = [
            'success' => false,
            'error_msg' => ''
        ];
        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');
        if($username!='' && $password!=''){
            $pattern = '/[0-9a-z]/';
            if(preg_match($pattern,$username)){
                $user = User::findByUsername($username);
                if(!$user){
                    $newUser = new User();
                    $newUser->username = $username;
                    $newUser->password = md5($password);
                    $newUser->nickname = strtoupper($username);
                    $newUser->mobile = '000';
                    $newUser->gender = 0;
                    $newUser->status = 1;
                    if($newUser->save()){
                        $return['result'] = true;
                        $token = H_JWT::generateToken($newUser->id);
                        $auth = new UserAuth();
                        $auth->user_id = $newUser->id;
                        $auth->token = $token;
                        $auth->expired_time = date('Y-m-d H:i:s',strtotime('+1 day'));
                        $auth->save();
                        $return['success'] = true;
                        $return['token'] = $token;
                        $return['userId'] = $newUser->id;
                        $return['userInfo'] = $newUser->attributes;
                    }else{
                        $return['error_msg'] = json_encode($newUser->errors).' 222注册错误,001';
                    }
                }else{
                    $return['error_msg'] = '用户名已经存在';
                }
            }else{
                $return['error_msg'] = '用户名格式错误，只允许数字+小写字母';
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
        $token = Yii::$app->request->get('accessToken');

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
        $token = Yii::$app->request->get('accessToken');

        $auth = UserAuth::find()->where(['token'=>$token])->one();

        if($auth) {
            if($auth->expired_time > date('Y-m-d H:i:s')){
                $user = User::find()->where(['id' => $auth->user_id])->one();
                if ($user){
                    $return['success'] = true;
                    $return['token'] = $token;
                    $return['tokenForceUpdate'] = true;
                    $return['userId'] = $user->id;
                    $return['userInfo'] = $user->attributes;
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

    public function actionAdminLogin(){
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
                    if($user->username === 'admin'){
                        $return['success'] = true;
                        $return['data']['token'] = 'admin';
                    }else{
                        $reutrn['error_msg'] = '不是管理员';
                    }
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

    public function actionAdminInfo(){
        $return = [
            'success' => false,
            'error_msg' => ''
        ];
        $token = Yii::$app->request->get('token');
        if($token == 'admin'){
            $return['success'] = true;
            $return['data'] = [
                'roles' => 'admin',
                'name' => 'admin',
                'avatar' => 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif'
            ];
        }else{
            $return['error_msg'] = '管理员不存在';
        }
        return $return;
    }

    public function actionAdminLogout(){
        $return = [
            'success' => false,
            'error_msg' => ''
        ];
        $token = Yii::$app->request->get('token');
        if($token == 'admin'){
            $return['success'] = true;
        }else{
            $return['error_msg'] = '管理员不存在';
        }
        return $return;
    }

}
