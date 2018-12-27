<?php

namespace app\modules\v1\controllers;

use app\models\GameCard;
use app\models\History;
use app\models\HistoryLog;
use app\models\HistoryPlayer;
use app\models\Room;
use app\models\RoomPlayer;
use Yii;
use app\models\Game;

class MyGameController extends MyActiveController
{
    public function init(){
        $this->modelClass = Game::className();
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
     * @apiDefine GroupMyGame
     *
     * 玩家对应的游戏
     */

    public function actionStart(){
        list($isInGame, $roomId) = Game::isInGame();
        if($isInGame) {
            throw new \Exception(Game::EXCEPTION_START_GAME_HAS_STARTED_MSG,Game::EXCEPTION_START_GAME_HAS_STARTED_CODE);
        }
        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
        if(!$hostPlayer || !$guestPlayer) {
            throw new \Exception(Game::EXCEPTION_START_GAME_WRONG_PLAYERS_MSG,Game::EXCEPTION_START_GAME_WRONG_PLAYERS_CODE);
        }
        if(!$isReady) {
            throw new \Exception(Game::EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_MSG,Game::EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_CODE);
        }
        Game::createOne($roomId);
        $cache = Yii::$app->cache;
        $cache->delete('room_info_no_update_'.$hostPlayer->user_id);
        $cache->delete('room_info_no_update_'.$guestPlayer->user_id);
    }

    /**
     * @api {post} /my-game/end 结束游戏
     * @apiName End
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     */

    public function actionEnd(){
        list($isInGame, $roomId) = Game::isInGame();
        if(!$isInGame) {
            throw new \Exception(Game::EXCEPTION_END_GAME_HAS_NO_GAME_MSG,Game::EXCEPTION_END_GAME_HAS_NO_GAME_CODE);
        }
        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
        if(!$isHost){
            throw new \Exception(Game::EXCEPTION_END_GAME_NOT_HOST_PLAYER_MSG,Game::EXCEPTION_END_GAME_NOT_HOST_PLAYER_CODE);
        }
        # 删除游戏数据
        Game::deleteAll(['room_id'=>$roomId]);
        GameCard::deleteAll(['room_id'=>$roomId]);
        # 修改客机玩家状态为"未准备"
        $guest_player = RoomPlayer::find()->where(['room_id'=>$room->id,'is_host'=>0])->one();
        if($guest_player){
            $guest_player->is_ready = 0;
            $guest_player->save();
        }
        #游戏结束 修改日志状态
        $history = History::find()->where(['room_id'=>$room->id,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            $history->status = History::STATUS_END;
            $history->save();
        }
        $cache = Yii::$app->cache;
        $cache->delete('room_info_no_update_'.$hostPlayer->user_id);
        $cache->delete('room_info_no_update_'.$guestPlayer->user_id);
    }



    /**
     * @api {get} /my-game/info 获取游戏信息
     * @apiName Info
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {string=all,simple} mode=all 获取模式
     * @apiParam {boolean=false,true} force=false 是否强制刷新
     *
     */

    public function actionInfo(){
        $mode = Yii::$app->request->get('mode','all');

        $force = !!Yii::$app->request->get('force',false);

        return Game::info($mode, $force);

    }




    /**
     * @api {post} /my-game/do-discard 弃牌操作
     * @apiName DoDiscard
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选手牌的排序
     */
    public function actionDoDiscard(){
        $typeOrd = Yii::$app->request->post('cardSelectOrd');
        list($isInGame, $roomId) = Game::isInGame();
        if(!$isInGame) {
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_IN_GAME_MSG,Game::EXCEPTION_DISCARD_NOT_IN_GAME_CODE);
        }
        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
        $game = Game::find()->where(['room_id'=>$roomId])->one();
        if($game->round_player_is_host != $isHost){ #不是当前玩家操作的回合
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_PLAYER_ROUND_MSG,Game::EXCEPTION_DISCARD_NOT_PLAYER_ROUND_CODE);
        }
        //丢弃一张牌
        $cardOrd = GameCard::discardCard($roomId, $isHost, $typeOrd);
        //恢复一个提示数
        Game::recoverCue($roomId);
        //插入日志 record
        //TODO
        $history = History::find()->where(['room_id'=>$roomId,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            list($get_content_success,$content_param,$content) = HistoryLog::getContentByDiscard($roomId,$cardOrd);
            if($get_content_success){
                $historyLog = new HistoryLog();
                $historyLog->history_id = $history->id;
                $historyLog->type = HistoryLog::TYPE_DISCARD_CARD;
                $historyLog->content_param = $content_param;
                $historyLog->content = $content;
                $historyLog->save();
                //var_dump($historyLog->errors);exit;
            }
        }
        //交换(下一个)回合
        Game::changeRoundPlayer($roomId);

        $cache = Yii::$app->cache;
        $cache->delete('game_info_no_update_'.$hostPlayer->user_id);
        $cache->delete('game_info_no_update_'.$guestPlayer->user_id);
    }

    /**
     * @api {post} /my-game/do-play 打牌操作
     * @apiName DoPlay
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选手牌的排序
     */
    public function actionDoPlay(){
        $typeOrd = Yii::$app->request->post('cardSelectOrd');
        list($isInGame, $roomId) = Game::isInGame();
        if(!$isInGame) {
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_IN_GAME_MSG,Game::EXCEPTION_DISCARD_NOT_IN_GAME_CODE);
        }
        list($game) = Game::getInfo($roomId);


        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
        $game = Game::find()->where(['room_id'=>$roomId])->one();
        if($game->round_player_is_host != $isHost){ #不是当前玩家操作的回合
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_PLAYER_ROUND_MSG,Game::EXCEPTION_DISCARD_NOT_PLAYER_ROUND_CODE);
        }
        list($data['play_result'],$cardOrd) = GameCard::playCard($roomId, $isHost, $typeOrd);
        //给这个玩家摸一张牌
        GameCard::drawCard($roomId,$isHost);

        if($data['play_result']){
            //恢复一个提示数
            Game::recoverCue($roomId);
        }else{
            //消耗一次机会
            Game::useChance($roomId);

            $result = Game::checkGame();
            if(!$result){

                //TODO

                //Game::end();
            }
        }


        //插入日志 record
        $history = History::find()->where(['room_id'=>$roomId,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            list($get_content_success,$content_param,$content) = HistoryLog::getContentByPlay($roomId,$cardOrd,$data['play_result']);
            if($get_content_success){
                $historyLog = new HistoryLog();
                $historyLog->history_id = $history->id;
                $historyLog->type = HistoryLog::TYPE_PLAY_CARD;
                $historyLog->content_param = $content_param;
                $historyLog->content = $content;
                $historyLog->save();
                //var_dump($historyLog->errors);exit;
            }
        }


        //交换(下一个)回合
        Game::changeRoundPlayer($roomId);

        $cache = Yii::$app->cache;
        $cache->delete('game_info_no_update_'.$hostPlayer->user_id);
        $cache->delete('game_info_no_update_'.$guestPlayer->user_id);

    }

    /**
     * @api {post} /my-game/do-cue 提示操作
     * @apiName DoCue
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选对手手牌的排序
     * @apiParam {string="num","color"} cueType 提示类型
     */
    public function actionDoCue(){

        $ord = Yii::$app->request->post('cardSelectOrd');

        $type = Yii::$app->request->post('cueType');

        Game::cue($ord,$type);

    }


    /**
     * @api {post} /my-game/auto-play 自动打牌
     * @apiName AutoPlay
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     *
     *
     */
    public function actionAutoPlay(){

        Game::autoPlay();

    }
}
