<?php

namespace app\modules\v1\controllers;

use app\components\cache\MyRoomCache;
use app\components\cache\RoomListCache;
use app\components\exception\MyRoomException;
use app\models\MyRoom;
use app\models\RoomPlayer;
use Yii;
use app\models\Room;

class MyRoomController extends MyActiveController{

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

    /*
     *  - 根据 请求的参数force 进行判断
     *      > false
     *          * 读取 当前用户的房间信息无需更新标志 (MyRoomCache::ROOM_INFO_NO_UPDATED_FLAG_KEY_PREFIX.[user->id])
     *              > true ['noUpdate'=>true]    【END】
     *              > 不存在 或者 false 继续【读取房间数据】
     *      > true
     *          * 强制【读取房间数据】
     *  - 读取房间数据
     *  - 更新 当前用户的房间信息无需更新标志 = true
     *
     */
    public function actionInfo(){
        $force = !!Yii::$app->request->get('force',false); //是否强制读取数据库，即跳过cache
        if(!$force) {
            if(MyRoomCache::isNoUpdate(Yii::$app->user->id)){
                return ['noUpdate'=>true];
            }
        }

        $info = MyRoom::getInfo();

        MyRoomCache::set(Yii::$app->user->id);

        return $info;

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
        # 检查是否不在房间内
        list($isInRoom) = MyRoom::isIn();
        if($isInRoom){
            MyRoomException::t('do_enter_already_in_room');
        }

        $roomId = (int) Yii::$app->request->post('roomId');
        $room = Room::getOne($roomId);

        if ($room->password != '') {
            //TODO 房间密码处理
            MyRoomException::t('do_enter_locked_room_by_wrong_password');
        }

        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

        # 房间已满
        if($hostPlayer && $guestPlayer) {
            MyRoomException::t('do_enter_full_room');
        }

        if (!$hostPlayer) {
            # 成为主机玩家
            $newRoomPlayer = new RoomPlayer();
            $newRoomPlayer->room_id = $roomId;
            $newRoomPlayer->user_id = Yii::$app->user->id;
            $newRoomPlayer->is_host = 1;
            $newRoomPlayer->is_ready = 0;
            $newRoomPlayer->save();
        }else {
            # 成为客机玩家
            $newRoomPlayer = new RoomPlayer();
            $newRoomPlayer->room_id = $roomId;
            $newRoomPlayer->user_id = Yii::$app->user->id;
            $newRoomPlayer->is_host = 0;
            $newRoomPlayer->is_ready = 0;
            $newRoomPlayer->save();

            # 清空房主的房间信息缓存
            MyRoomCache::clear($hostPlayer->user_id);
        }

        RoomListCache::updateSysKey();
    }

    public function actionExit(){
        # 检查是否在 房间内
        list($isInRoom, $roomId) = MyRoom::isIn();
        if(!$isInRoom){
            MyRoomException::t('do_exit_not_in_room');
        }
        # 获取room对象
        $room =  Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

        # 删除房内玩家记录
        $rowCount = RoomPlayer::deleteAll(['user_id'=>Yii::$app->user->id]);

        # 删除数等于0 ， 删除失败
        if( $rowCount === 0 ){
            MyRoomException::t('do_exit_failure');
        }

        # 清空对应玩家的房间信息缓存
        MyRoomCache::clear(Yii::$app->user->id);

        #原本是主机玩家  要对应改变客机玩家的状态 （原本的客机玩家变成这个房间的主机玩家，准备状态清空）
        if($hostPlayer && $hostPlayer->user_id == Yii::$app->user->id){
            if($guestPlayer){
                $guestPlayer->is_host = 1;
                $guestPlayer->is_ready = 0;
                $guestPlayer->save();

                //清空房主(此时的房主是原先的访客)的房间信息缓存
                MyRoomCache::clear($guestPlayer->user_id);
            }
        }else{
            if($hostPlayer){
                //清空房主的房间信息缓存
                MyRoomCache::clear($hostPlayer->user_id);
            }
        }

        RoomListCache::updateSysKey();
    }

    public function actionDoReady(){
        # 检查是否在 房间内
        list($isInRoom, $roomId) = MyRoom::isIn();
        if(!$isInRoom){
            throw new \Exception(Room::EXCEPTION_DO_READY_NOT_IN_ROOM_MSG,Room::EXCEPTION_DO_READY_NOT_IN_ROOM_CODE);
        }

        # 获取room对象
        $room =  Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

        # 判断你是否是客机玩家
        if(!$guestPlayer || !$guestPlayer->user || $guestPlayer->user->id != Yii::$app->user->id ){
            throw new \Exception(Room::EXCEPTION_DO_READY_NOT_GUEST_PLAYER_MSG,Room::EXCEPTION_DO_READY_NOT_GUEST_PLAYER_CODE);
        }

        /*
         * TODO 检查游戏状态
         * list($isInGame) = Game::isInGame();
        $game = Game::find()->where(['room_id'=>$room->id,'status'=>Game::STATUS_PLAYING])->one();*/

        $guestPlayer->is_ready = $guestPlayer->is_ready ? 0 : 1;
        if($guestPlayer->save()){
            //清空房间内玩家的信息缓存
            MyRoomCache::clear($hostPlayer->user->id);
            MyRoomCache::clear($guestPlayer->user->id);
        }else{
            throw new \Exception(Room::EXCEPTION_DO_READY_FAILURE_MSG,Room::EXCEPTION_DO_READY_FAILURE_CODE);
        }
    }

}
