<?php

namespace app\modules\v1\controllers;

use app\components\cache\MyGameCache;
use app\components\cache\MyRoomCache;
use app\components\exception\GameCardException;
use app\components\exception\MyGameException;
use app\models\GameCard;
use app\models\History;
use app\models\HistoryLog;
use app\models\HistoryPlayer;
use app\models\MyGame;
use app\models\MyRoom;
use app\models\Room;
use app\models\RoomPlayer;
use Yii;
use app\models\Game;

class MyGameController extends MyActiveController
{
    public $roomId;
    public $isHost;
    public $hostPlayer;
    public $guestPlayer;

    public $game;

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
        list($isInRoom, $roomId) = MyRoom::isIn();
        # error: 不在房间中
        if(!$isInRoom) {
            MyGameException::t('do_start_not_in_room');
        }
        # error：游戏已经开始
        if(Game::isPlaying($roomId)) {
            MyGameException::t('do_start_game_has_started');
        }

        $room =  Room::getOne($roomId, false); //MyRoom:isIn() 已经对Room做过检查
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

        # error：游戏玩家错误
        if(!$hostPlayer || !$guestPlayer) {
            MyGameException::t('do_start_wrong_players');
        }
        # error：操作人不是主机玩家
        if($hostPlayer->user->id != Yii::$app->user->id) {
            MyGameException::t('do_start_not_host_player');
        }
        # error：客机玩家没有准备
        if($guestPlayer->is_ready != 1) {
            MyGameException::t('do_start_guest_player_not_ready');
        }

        # 开始创建游戏
        $game = new Game();
        $game->room_id = $roomId;
        $game->round_num = 1; //当前回合数 1
        $game->round_player_is_host = rand(0,1); //随机选择一个玩家开始第一个回合
        $game->cue_num = Game::DEFAULT_CUE; //剩余提示数
        $game->chance_num = Game::DEFAULT_CHANCE; //剩余机会数
        $game->status = Game::STATUS_PLAYING;  //TODO 暂时无用因为永远是1 PLAYING
        $game->score = 0; //当前分数
        if($game->save()){
            //初始化牌库
            GameCard::initLibrary($roomId);
            //主机/客机玩家 各模五张牌
            for($i=0;$i<5;$i++){
                GameCard::drawCard($roomId,true);
                GameCard::drawCard($roomId,false);
            }
            #创建游戏History
            $history = new History();
            $history->room_id = $room->id;
            $history->status = History::STATUS_PLAYING;
            $history->score = 0;
            if ($history->save()) {
                $historyPlayer = new HistoryPlayer();
                $historyPlayer->history_id = $history->id;
                $historyPlayer->user_id = $hostPlayer->user_id;
                $historyPlayer->is_host = 1;
                $historyPlayer->save();
                $historyPlayer = new HistoryPlayer();
                $historyPlayer->history_id = $history->id;
                $historyPlayer->user_id = $guestPlayer->user_id;
                $historyPlayer->is_host = 0;
                $historyPlayer->save();
            } else {
                MyGameException::t('do_start_create_game_history_failure');
            }
        }else{
            MyGameException::t('do_start_create_game_failure');
        }

        MyRoomCache::clear($hostPlayer->user_id);
        MyRoomCache::clear($guestPlayer->user_id);

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
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            MyGameException::t('do_end_not_in_room');
        }
        # 游戏未开始
        if(!Game::isPlaying($roomId)) {
            MyGameException::t('do_end_not_in_game`');
        }

        $room = Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

        // TODO 暂时主机玩家可以强制结束游戏
        # 操作人不是主机玩家
        if($hostPlayer->user_id != Yii::$app->user->id){
            MyGameException::t('do_end_not_host_player');
        }

        # 删除游戏数据
        Game::deleteAll(['room_id'=>$roomId]);
        GameCard::deleteAll(['room_id'=>$roomId]);

        # 修改客机玩家状态为"未准备"
        if($guestPlayer){
            $guestPlayer->is_ready = 0;
            $guestPlayer->save();
        }

        #游戏结束 修改日志状态
        $history = History::find()->where(['room_id'=>$room->id,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            $history->status = History::STATUS_END;
            $history->save();
        }

        #清除主客玩家的房间缓存
        MyRoomCache::clear($hostPlayer->user_id);
        MyRoomCache::clear($guestPlayer->user_id);
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
        if(!$force) {
            if(MyGameCache::isNoUpdate(Yii::$app->user->id)) {//存在则不更新游戏信息
                return ['noUpdate'=>true];
            }
        }
        #判断是否在游戏中， 获得房间ID
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            MyGameException::t('not_in_room');
        }

        $isPlaying = Game::isPlaying($roomId);

        $data = [];
        $data['isPlaying'] = $isPlaying;
        $data['roomId'] = $roomId;
        if(!$isPlaying) {
            return $data;
        }
        if ($mode == 'all') {
            $game = Game::getOne($roomId);

            $data['game'] = [
                'roundNum' => $game->round_num,
                'roundPlayerIsHost' => $game->round_player_is_host == 1,
            ];
            $data['card'] = Game::getCardInfo($game->room_id);
            list(, , $data['log']) = HistoryLog::getList($game->room_id);
            $data['game']['lastUpdated'] = HistoryLog::getLastUpdate($game->room_id);
            MyGameCache::set(Yii::$app->user->id);
        }
        return $data;
    }

    /*
     * 玩家操作前的验证
     */
    private function checkDo(){
        # 判断是否在游戏中， 获得房间ID
        list($isInRoom, $roomId) = MyRoom::isIn();
        # 不在房间中 抛出异常
        if(!$isInRoom) {
            MyGameException::t('do_operation_not_in_room');
        }

        # 不在游戏中
        if(!Game::isPlaying($roomId)) {
            MyGameException::t('do_operation_not_in_game');
        }

        $room =  Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $isHost = $hostPlayer->user_id == Yii::$app->user->id;  //操作玩家是否是主机玩家

        $game = Game::getOne($roomId);
        $roundPlayerIsHost = $game->round_player_is_host == 1; // 当前回合玩家是否是主机玩家

        if($roundPlayerIsHost != $isHost){ // 判断是不是当前玩家操作的回合
            MyGameException::t('do_operation_not_player_round');
        }

        $this->roomId = $roomId;
        $this->isHost = $isHost;
        $this->hostPlayer = $room->hostPlayer;
        $this->guestPlayer = $room->guestPlayer;

        $this->game = $game;

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

        # 操作前的检查
        $this->checkDo();

        # 根据isHost，确定类型是主机手牌还是客机手牌
        $cardType = $this->isHost ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

        # 找到所选择的牌
        $cardSelected = GameCard::getOne($this->roomId, $cardType, $typeOrd);

        # 将牌丢进弃牌堆
        GameCard::discard($this->roomId, $cardSelected->ord);

        # 牌序移动
        GameCard::moveHandCardsByLackOfCard($this->roomId, $this->isHost, $typeOrd);

        # 摸牌
        GameCard::drawCard($this->roomId, $this->isHost);

        # 恢复一个提示数
        Game::recoverCue($this->roomId);

        # 记录日志
        $history = History::find()->where(['room_id'=>$this->roomId,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            list($get_content_success,$content_param,$content) = HistoryLog::getContentByDiscard($this->roomId,$cardSelected->ord);
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
        Game::changeRoundPlayer($this->roomId);

        MyGameCache::clear($this->hostPlayer->user_id);
        MyGameCache::clear($this->guestPlayer->user_id);
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

        #判断是否在游戏中， 获得房间ID
        list($isInRoom, $roomId) = MyRoom::isIn();
        # 不在房间中 抛出异常
        if(!$isInRoom) {
            MyGameException::t('do_play_not_in_room');
        }

        # 不在游戏中
        if(!Game::isPlaying($roomId)) {
            MyGameException::t('do_play_not_in_game');
        }

//        Game::playCard($roomId, $typeOrd);


        $room = Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;
        $isHost = $hostPlayer->user_id == Yii::$app->user->id;

        $game = Game::getOne($roomId);

        # 不是当前玩家操作的回合
        if($game->round_player_is_host != $isHost){
            GameCardException::t('wrong_hands_type_ord');
            throw new \Exception(Game::EXCEPTION_PLAY_NOT_PLAYER_ROUND_MSG,Game::EXCEPTION_PLAY_NOT_PLAYER_ROUND_CODE);
        }

        # 手牌排序参数错误
        if(!in_array($typeOrd, GameCard::$handsTypeOrds)) {
            throw new \Exception(GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_MSG,GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_CODE);
        }

        #根据isHost，选择GameCard的type
        $cardType = $isHost ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

        #找到所选择的牌
        $cardSelected = GameCard::find()->where(['room_id'=>$roomId,'type'=>$cardType,'type_ord'=>$typeOrd])->one();
        if(!$cardSelected){
            throw new \Exception(GameCard::EXCEPTION_NOT_FOUND_HANDS_MSG,GameCard::EXCEPTION_NOT_FOUND_HANDS_CODE);
        }

        $cardsSuccessTop = GameCard::getCardsSuccessTop($roomId);

        $colorTopNum = $cardsSuccessTop[$cardSelected->color];  //对应花色的目前成功的最大数值
        $num = Card::$numbers[$cardSelected->num];              //选中牌的数值
        if($colorTopNum + 1 == $num){
            #出牌成功
            $cardSelected->type = GameCard::TYPE_SUCCEEDED;
            $cardSelected->type_ord = GameCard::getInsertSucceededOrd($roomId);
            $cardSelected->save();
            $playSuccess = true;
        }else{
            #出牌失败
            $cardSelected->type = GameCard::TYPE_DISCARDED;
            $cardSelected->type_ord = GameCard::getInsertDiscardOrd($roomId);
            $cardSelected->save();
            $playSuccess = false;
        }


        if($playSuccess){
            $game->score +=1;
            $game->save();

            //恢复一个提示数
            Game::recoverCue($roomId);
            $result = true;
        }else{
            //消耗一次机会
            list($result, $chance_num) = Game::useChance($roomId);
            if($result){
                if($chance_num === 0) {
                    # 失败机会次数耗尽  结束游戏
                    Game::end();
                }
            }else{

                //TODO  使用机会失败
            }
        }

        $cardOrd = $cardSelected->ord;
        GameCard::moveHandCardsByLackOfCard($roomId, $isHost, $typeOrd);


        //给这个玩家摸一张牌
        GameCard::drawCard($roomId,$isHost);


        //插入日志 record
        $history = History::find()->where(['room_id'=>$roomId,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            list($get_content_success,$content_param,$content) = HistoryLog::getContentByPlay($roomId,$cardOrd,$result);
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

        MyGameCache::clear($hostPlayer->user_id);
        MyGameCache::clear($guestPlayer->user_id);

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
        $cueType = Yii::$app->request->post('cueType');

        #判断是否在游戏中， 获得房间ID
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_ROOM_MSG,Game::EXCEPTION_NOT_IN_ROOM_CODE);
        }

        if(!Game::isPlaying($roomId)) {
            throw new \Exception(Game::EXCEPTION_CUE_NOT_IN_GAME_MSG,Game::EXCEPTION_CUE_NOT_IN_GAME_CODE);
        }

        Game::cueCard($roomId, $ord, $cueType);
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
    /*
     *
     * 自动打牌（挂机）
     *
     * 1. 有剩余提示数 则随机提示一张牌的 颜色或者数字
     *
     * 2. 没有剩余提示数 则随机丢弃一张牌
     *
     */
    public function actionAutoPlay(){

        $success = false;
        $msg = '';
        $data = [];
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $game = Game::find()->where(['room_id'=>$room_player->room_id,'status'=>Game::STATUS_PLAYING])->one();
            if($game){
                if($game->round_player_is_host==$room_player->is_host){
                    if($game->cue_num>0){

                        $rand = $room_player->is_host == 1 ? rand(5,9) : rand(0,4);

                        return Game::cue($rand,rand(0,1) ? 'num':'color');

                    }else{

                        $rand = $room_player->is_host != 1 ? rand(5,9) : rand(0,4);

                        return Game::discard($rand);

                    }
                }else{
                    $msg = '当前不是你的回合';
                }
            }else{
                $msg = '你所在房间游戏未开始/或者有多个游戏，错误';
            }
        }else{
            $msg = '你不在房间中/不止在一个房间中，错误';
        }

        return [$success,$msg,$data];

    }
}
