<?php

namespace app\modules\v1\controllers;

use Yii;
use app\models\Room;

class MyRoomController extends MyActiveController
{
    public function init(){
        $this->modelClass = Room::className();
        parent::init();
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        return $behaviors;
    }

    /**
     * @apiDefine ParamAuthToken
     *
     * @apiParam {string} authToken 身份认证的token
     */

    /**
     * @apiDefine GroupMyRoom
     *
     * 玩家对应的房间
     */

    public function actionInfo(){

        $mode = Yii::$app->request->get('mode','all');

        $force = Yii::$app->request->get('force',false);

        try {

            return $this->sendSuccess(Room::info($mode, $force));

        }catch ( \Exception $e) {

            return $this->sendException($e);

        }
    }


    /**
     * @api {post} /my-room/enter 进入房间
     * @apiName AutoPlay
     * @apiGroup GroupMyRoom
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int} roomId 房间ID
     *
     */
    public function actionEnter(){
        $roomId = (int) Yii::$app->request->post('roomId');

        try {
            Room::enter($roomId);

            return $this->sendSuccess();

        }catch ( \Exception $e) {

            return $this->sendException($e);

        }
    }

    public function actionExit(){
        try {
            Room::exitRoom();

            return $this->sendSuccess();

        }catch ( \Exception $e) {

            return $this->sendException($e);

        }
    }

    public function actionDoReady(){

        list($success,$msg) = Room::doReady();

        if($success){
            return $this->sendSuccess();
        }else{
            return $this->sendError(0000, $msg);
        }
    }

}
