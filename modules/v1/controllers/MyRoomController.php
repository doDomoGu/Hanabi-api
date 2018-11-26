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
        $return = $this->return;

        $room_id = Yii::$app->request->post('roomId');

        list($return['success'],$return['msg']) = Room::enter($room_id);

        if($return['success']){
            $return['data'] = ['roomId'=>$room_id];
        }

        return $return;
    }

    public function actionExit(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Room::exitRoom();

        return $return;
    }

    public function actionInfo(){
        $return = $this->return;

        $mode = Yii::$app->request->get('mode','all');

        $force = Yii::$app->request->get('force',false);

        list($return['success'],$return['msg'],$return['data']) = Room::getInfo($mode,$force);

        return $return;
    }

    public function actionDoReady(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Room::doReady();

        return $return;
    }

}
