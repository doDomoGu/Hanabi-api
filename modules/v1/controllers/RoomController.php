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

    public function actionList(){
        $force = !!Yii::$app->request->get('force',false);

        list($success, $data) = Room::getList($force);

        if($success){
            return $this->sendSuccess($data);
        }else{
            return $this->sendError(0000,'获取房间列表错误');
        }
    }

    //测试用
    public function actionRefreshSysLastupdated(){
        $userId = Yii::$app->user->id;
        $cache = Yii::$app->cache;
        $roomListUpdateCacheKey  = 'room_list_lastupdated';  //存在则不更新房间信息
        $cache->set($roomListUpdateCacheKey, date('Y-m-d H:i:s'));
    }
}