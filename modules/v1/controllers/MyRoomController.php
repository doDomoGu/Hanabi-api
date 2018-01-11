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


    public function actionEnter(){
        $return = $this->return;

        $room_id = Yii::$app->request->post('room_id');

        list($return['success'],$return['msg']) = Room::enter($room_id);

        if($return['success']){
            $return['data'] = ['room_id'=>$room_id];
        }

        return $return;
    }

    public function actionExit(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Room::exitRoom();

        return $return;
    }


    public function actionIsInRoom(){
        $return = $this->return;


        list($return['success'],$return['msg'],$return['data']) = Room::isInRoom();

        return $return;
    }

    public function actionGetInfo(){
        $return = $this->return;

        $mode = Yii::$app->request->post('mode','all');

        $force = Yii::$app->request->post('force',false);

        list($return['success'],$return['msg'],$return['data']) = Room::getInfo($mode,$force);

        return $return;
    }

    public function actionDoReady(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Room::doReady();

        return $return;
    }

}
