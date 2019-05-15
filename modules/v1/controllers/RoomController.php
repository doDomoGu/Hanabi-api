<?php

namespace app\modules\v1\controllers;

use Yii;
use app\components\cache\RoomListCache;
use app\models\RoomPlayer;
use app\models\Room;

class RoomController extends MyActiveController {

    /**
     * @apiDefine ParamAuthToken
     *
     * @apiParam {string} authToken 身份认证的token
     */

    /*
     *  - 根据 请求的参数force 进行判断
     *      > false
     *          * 读取当前用户的最后缓存时间(RoomListCache::USER_LAST_UPDATED_KEY_PREFIX.[user->id])
     *              > 比 系统的最后缓存时间(RoomListCache::SYS_LAST_UPDATED_KEY) 大 返回 ['noUpdate'=>true]    【END】
     *              > 不存在 或者 比系统时间小  继续【读取列表数据】
     *      > true
     *          * 强制【读取列表数据】
     *  - 读取列表数据
     *  - 更新 当前用户的最后缓存时间 = 当前时间
     *
     */
    public function actionList(){
        $force = !!Yii::$app->request->get('force',false);
        if(!$force){
            if(RoomListCache::isNoUpdate()){
                return ['noUpdate'=>true];
            }
        }
        $rooms = Room::find()->all();
        $list = [];
        foreach($rooms as $r){
            $roomPlayerCount = (int) RoomPlayer::find()->where(['room_id'=>$r->id])->count();
            $list[] = [
                'id'            => $r->id,
                'title'         => $r->title,
                'isLocked'      => $r->password!='',
                'playerCount'   => $roomPlayerCount
            ];
        };
        RoomListCache::updateUserKey();
        return ['list'=>$list];
    }

    //测试用
    /*public function actionRefreshSysLastupdated(){
        RoomListCache::updateSysKey();
        return date('Y-m-d H:i:s');
    }*/
}