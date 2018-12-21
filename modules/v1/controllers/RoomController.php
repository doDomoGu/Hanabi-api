<?php

namespace app\modules\v1\controllers;

use app\models\RoomPlayer;
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

        $cache = Yii::$app->cache;

        $userCacheKey  = 'room_list_lastupdated_'.Yii::$app->user->id;
        $sysCacheKey  = 'room_list_lastupdated';
        $userLastUpdated = $cache->get($userCacheKey); // 用户的房间列表的最后缓存更新时间
        $sysLastUpdated = $cache->get($sysCacheKey); // 系统的房间列表的最后缓存更新时间
        //如果 用户的最后缓存更新时间 < 系统的最后缓存更新时间  就要重新读取数据 并将 用户时间更新为系统时间
        if (!$force && $userLastUpdated!='' && $userLastUpdated >= $sysLastUpdated) {
            return ['noUpdate'=>true];
        } else {
            $rooms = Room::find()->all();
            $list = [];
            foreach($rooms as $r){
                $roomPlayerCount = RoomPlayer::find()->where(['room_id'=>$r->id])->count();

                $list[] = [
                    'id'        => $r->id,
                    'title'     => $r->title,
                    'isLocked'    => $r->password!='',
                    'playerCount' => (int) $roomPlayerCount
                ];
            };
            $data['list'] = $list;
            $now = date('Y-m-d H:i:s');
//            $data['lastupdated'] = $now;
            $cache->set($userCacheKey,$now);
            $cache->set($sysCacheKey,$now);
            return $data;
        }
    }

    //测试用
    public function actionRefreshSysLastupdated(){
        $userId = Yii::$app->user->id;
        $cache = Yii::$app->cache;
        $roomListUpdateCacheKey  = 'room_list_lastupdated';  //存在则不更新房间信息
        $cache->set($roomListUpdateCacheKey, date('Y-m-d H:i:s'));
        return date('Y-m-d H:i:s');
    }
}