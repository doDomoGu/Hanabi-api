<?php

namespace app\modules\v1\controllers;

use Yii;
use app\models\Room;

class RoomController extends MyActiveController
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
}