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
        $room_id = Yii::$app->request->post('roomId');

        list($success,$msg) = Room::enter($room_id);

        if($success){
            return $this->sendSuccess(['roomId'=>$room_id]);
        }else{
            return $this->sendError(0000, $msg);
        }
    }

    public function actionExit(){
        list($return['success'],$return['msg']) = Room::exitRoom();

        return $return;
    }

    public function actionInfo(){

        $mode = Yii::$app->request->get('mode','all');

        $force = Yii::$app->request->get('force',false);

        list($success, $data, $msg) = Room::getInfo($mode,$force);

        if($success){
            return $this->sendSuccess($data);
        }else{
            return $this->sendError(0000,$msg);
        }
    }

    public function actionDoReady(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Room::doReady();

        return $return;
    }

}
