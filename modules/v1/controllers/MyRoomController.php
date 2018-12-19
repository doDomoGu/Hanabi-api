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

        return Room::myRoomInfo($mode, $force);

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

        Room::enter($roomId);
    }

    public function actionExit(){

        Room::exitRoom();
    }

    public function actionDoReady(){

        Room::doReady();

    }

}
