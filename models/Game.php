<?php

namespace app\models;


use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "game".
 *
 * @property integer $room_id
 * @property integer $round_num
 * @property integer $round_player_is_host
 * @property integer $cue_num
 * @property integer $chance_num
 * @property integer $status
 * @property integer $score
 * @property string $updated_at
 */
class Game extends ActiveRecord
{
    const DEFAULT_CUE = 8;   //默认提供线索(CUE)次数
    const DEFAULT_CHANCE = 3;  //默认可燃放机会(chance)次数

    public static $cue_types = ['color','num'];


    const STATUS_PLAYING = 1;
    const STATUS_END = 2;

    const EXCEPTION_NOT_IN_ROOM_CODE  = 20001;
    const EXCEPTION_NOT_IN_ROOM_MSG   = '在判断是否在进行游戏时，发现玩家根本不在房间中';
    const EXCEPTION_WRONG_CHANCE_NUM_CODE  = 20002;
    const EXCEPTION_WRONG_CHANCE_NUM_MSG   = '机会数错误';
    const EXCEPTION_WRONG_CUE_NUM_CODE  = 20003;
    const EXCEPTION_WRONG_CUE_NUM_MSG   = '提示数错误';
    const EXCEPTION_NOT_IN_GAME_HAS_CARD_CODE  = 20004;
    const EXCEPTION_NOT_IN_GAME_HAS_CARD_MSG   = '不在游戏中，但是有卡牌存在';
    const EXCEPTION_WRONG_CARD_NUM_ALL_CODE  = 20005;
    const EXCEPTION_WRONG_CARD_NUM_ALL_MSG   = '游戏中，但是总卡牌数不对';
    const EXCEPTION_GUEST_PLAYER_NOT_READY_CODE  = 20006;
    const EXCEPTION_GUEST_PLAYER_NOT_READY_MSG   = '游戏中，但是客机玩家没有准备';
    const EXCEPTION_WRONG_PLAYERS_CODE  = 20007;
    const EXCEPTION_WRONG_PLAYERS_MSG   = '游戏中，玩家信息错误';
    const EXCEPTION_START_GAME_HAS_STARTED_CODE  = 20008;
    const EXCEPTION_START_GAME_HAS_STARTED_MSG   = '开始游戏操作，但是游戏已经开始了';
    const EXCEPTION_START_GAME_WRONG_PLAYERS_CODE  = 20009;
    const EXCEPTION_START_GAME_WRONG_PLAYERS_MSG   = '开始游戏操作，房间内游戏玩家信息错误';
    const EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_CODE  = 20010;
    const EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_MSG   = '开始游戏操作，客机玩家没有准备';
    const EXCEPTION_START_GAME_WRONG_ROOM_CODE  = 20011;
    const EXCEPTION_START_GAME_WRONG_ROOM_MSG   = '开始游戏操作，房间不存在';
    const EXCEPTION_CREATE_GAME_FAILURE_CODE  = 20012;
    const EXCEPTION_CREATE_GAME_FAILURE_MSG   = '开始游戏操作，创建失败';
    const EXCEPTION_CREATE_HISTORY_FAILURE_CODE  = 20013;
    const EXCEPTION_CREATE_HISTORY_FAILURE_MSG   = '开始游戏操作，创建游戏记录失败';
    const EXCEPTION_END_GAME_HAS_NO_GAME_CODE  = 20014;
    const EXCEPTION_END_GAME_HAS_NO_GAME_MSG   = '结束游戏操作，但是游戏不存在';
    const EXCEPTION_END_GAME_NOT_HOST_PLAYER_CODE  = 20015;
    const EXCEPTION_END_GAME_NOT_HOST_PLAYER_MSG   = '结束游戏操作，操作玩家不是主机玩家';
    const EXCEPTION_DISCARD_NOT_IN_GAME_CODE  = 20016;
    const EXCEPTION_DISCARD_NOT_IN_GAME_MSG   = '弃牌操作，不在游戏中';
    const EXCEPTION_DISCARD_NOT_PLAYER_ROUND_CODE  = 20016;
    const EXCEPTION_DISCARD_NOT_PLAYER_ROUND_MSG   = '弃牌操作，不是该玩家的回合';


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%game}}';
    }


    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => new Expression('NOW()'),  //时间戳（数字型）转为 日期字符串
                //'value'=>$this->timeTemp(),
            ]
        ];
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['room_id', 'round_num', 'round_player_is_host'], 'required'],
            [['room_id', 'round_num', 'round_player_is_host', 'cue_num', 'chance_num','status','score'], 'integer'],
            [['updated_at'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'room_id' => 'Room ID',
            'round_num' => '当前回合数',
            'round_player_is_host' => '当前回合对应的玩家', //是否是主机玩家
            'cue_num' => '剩余提示次数',
            'chance_num' => '剩余燃放机会次数',
            'status' => 'Status',  //1:游玩中,2:结束
            'score' => '分数',
            'updated_at' => 'Updated At',
        ];
    }

    public static function isInGame(){

        list($isInRoom, $roomId) = Room::isInRoom();

        #不在房间中 抛出异常
        if(!$isInRoom) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_ROOM_MSG,Game::EXCEPTION_NOT_IN_ROOM_CODE);
        }

        $game = Game::find()->where(['room_id'=>$roomId])->one();

        $gameCardCount = GameCard::find()->where(['room_id'=>$roomId])->count();

        #不在游戏中
        if(!$game) {

            #不在游戏中，但是有卡牌存在
            if($gameCardCount > 0) {
                throw new \Exception(Game::EXCEPTION_NOT_IN_GAME_HAS_CARD_MSG,Game::EXCEPTION_NOT_IN_GAME_HAS_CARD_CODE);
            }

            #返回false 和 房间号
            return [false, $roomId];
        }

        #在游戏中
        #以下是检查游戏状态

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

        if(!$hostPlayer || !$guestPlayer) {
            throw new \Exception(Game::EXCEPTION_WRONG_PLAYERS_MSG,Game::EXCEPTION_WRONG_PLAYERS_CODE);
        }

        if(!$isReady) {
            throw new \Exception(Game::EXCEPTION_GUEST_PLAYER_NOT_READY_MSG,Game::EXCEPTION_GUEST_PLAYER_NOT_READY_CODE);
        }

        if($game->chance_num < 1){
            throw new \Exception(Game::EXCEPTION_WRONG_CHANCE_NUM_MSG,Game::EXCEPTION_WRONG_CHANCE_NUM_CODE);
        }

        if($game->cue_num < 0){
            throw new \Exception(Game::EXCEPTION_WRONG_CUE_NUM_MSG,Game::EXCEPTION_WRONG_CUE_NUM_CODE);
        }

        #游戏中，但是总卡牌数不对
        if($gameCardCount <> Card::CARD_NUM_ALL){
            throw new \Exception(Game::EXCEPTION_WRONG_CARD_NUM_ALL_MSG,Game::EXCEPTION_WRONG_CARD_NUM_ALL_CODE);
        }


        return [true, $roomId];

    }


    public static function start(){

        list($isInGame, $roomId) = Game::isInGame();

        if($isInGame) {
            throw new \Exception(Game::EXCEPTION_START_GAME_HAS_STARTED_MSG,Game::EXCEPTION_START_GAME_HAS_STARTED_CODE);
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

        if(!$hostPlayer || !$guestPlayer) {
            throw new \Exception(Game::EXCEPTION_START_GAME_WRONG_PLAYERS_MSG,Game::EXCEPTION_START_GAME_WRONG_PLAYERS_CODE);
        }

        if(!$isReady) {
            throw new \Exception(Game::EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_MSG,Game::EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_CODE);
        }

        Game::createOne($roomId);

        //新建log 相关
        $history = new History();
        $history->room_id = $room->id;
        $history->status = History::STATUS_PLAYING;
        $history->score = 0;
        if($history->save()){
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
        }else{
            throw new \Exception(Game::EXCEPTION_CREATE_HISTORY_FAILURE_MSG,Game::EXCEPTION_CREATE_HISTORY_FAILURE_CODE);
        }

        $cache = Yii::$app->cache;
        $cache->delete('room_info_no_update_'.$hostPlayer->user_id);
        $cache->delete('room_info_no_update_'.$guestPlayer->user_id);

    }

    private static function createOne($room_id){
        $room = Room::find()->where(['id'=>$room_id])->one();
        if(!$room){
            throw new \Exception(Game::EXCEPTION_START_GAME_WRONG_ROOM_MSG,Game::EXCEPTION_START_GAME_WRONG_ROOM_CODE);
        }

        $game = new Game();
        $game->room_id = $room->id;
        $game->round_num = 1;
        $game->round_player_is_host = rand(0,1); //随机选择一个玩家开始第一个回合
        $game->cue_num = Game::DEFAULT_CUE;
        $game->chance_num = Game::DEFAULT_CHANCE;
        $game->status = Game::STATUS_PLAYING;
        $game->score = 0;

        if($game->save()){

            GameCard::initLibrary($room_id);

            for($i=0;$i<5;$i++){ //主机/客机玩家 各模五张牌
                GameCard::drawCard($room_id,true);
                GameCard::drawCard($room_id,false);
            }

        }else{
            throw new \Exception(Game::EXCEPTION_CREATE_GAME_FAILURE_MSG,Game::EXCEPTION_CREATE_GAME_FAILURE_CODE);
        }
    }


    public static function end(){

        list($isInGame, $roomId) = Game::isInGame();

        if(!$isInGame) {
            throw new \Exception(Game::EXCEPTION_END_GAME_HAS_NO_GAME_MSG,Game::EXCEPTION_END_GAME_HAS_NO_GAME_CODE);
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

        if(!$isHost){
            throw new \Exception(Game::EXCEPTION_END_GAME_NOT_HOST_PLAYER_MSG,Game::EXCEPTION_END_GAME_NOT_HOST_PLAYER_CODE);
        }

        // 1.删除游戏数据
        Game::deleteAll(['room_id'=>$roomId]);
        GameCard::deleteAll(['room_id'=>$roomId]);

        // 2.修改玩家2状态为"未准备"
        $guest_player = RoomPlayer::find()->where(['room_id'=>$room->id,'is_host'=>0])->one();
        if($guest_player){
            $guest_player->is_ready = 0;
            $guest_player->save();
        }

        //游戏结束 修改日志状态
        $history = History::find()->where(['room_id'=>$room->id,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            $history->status = History::STATUS_END;
            $history->save();
        }

        $cache = Yii::$app->cache;
        $cache->delete('room_info_no_update_'.$hostPlayer->user_id);
        $cache->delete('room_info_no_update_'.$guestPlayer->user_id);

    }

    public static function info($mode='all',$force=false){

        $cache = Yii::$app->cache;

        $cacheKey  = 'game_info_no_update_'.Yii::$app->user->id;  //存在则不更新游戏信息

        if(!$force) {

            if($cache->get($cacheKey)) {

                return ['noUpdate'=>true];

            }
        }

        #判断是否在游戏中， 获得房间ID
        list($isInGame, $roomId) = Game::isInGame();

        if(!$isInGame) {

            return ['isPlaying'=>false,'roomId' => $roomId];

        }

        $data = [];

        $data['isPlaying'] = true;
        $data['roomId'] = $roomId;

        if ($mode == 'all') {

            $game = Game::find()->where(['room_id'=>$roomId])->one();
            
            $data['game'] = [
                'roundNum' => $game->round_num,
                'roundPlayerIsHost' => $game->round_player_is_host == 1,
            ];

            $cardInfo = Game::getCardInfo($game->room_id);

            $data['card'] = $cardInfo;


            list(, , $data['log']) = HistoryLog::getList($game->room_id);

            $data['game']['lastUpdated'] = HistoryLog::getLastUpdate($game->room_id);

            $cache->set($cacheKey, true);
        }

        return $data;
    }

    public static function getCardInfo($room_id){
        $data = [];

        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game) {
            $user_id = Yii::$app->user->id;
            //获取当前玩家角色  只获取对手手牌信息（花色和数字）  自己的手牌只获取排序信息
            $room_player = RoomPlayer::find()->where(['user_id' => $user_id, 'room_id' => $game->room_id])->one();
            if ($room_player) {
                $player_is_host = $room_player->is_host == 1;

                $card_type = $player_is_host ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;


                $hostCard = GameCard::find()->where(['room_id' => $room_id, 'type' => $card_type])->orderBy('type_ord asc')->all();
                $guestCard = GameCard::find()->where(['room_id' => $room_id, 'type' => $card_type])->orderBy('type_ord asc')->all();


                $hostHands = [];
                $guestHands = [];

                if($player_is_host){
                    foreach ($hostCard as $card) {
                        $cardArr = [
                            'ord' => $card->type_ord
                        ];
                        $hostHands[] = $cardArr;
                    }

                    foreach ($guestCard as $card) {
                        $cardArr = [
                            'color' => $card->color,
                            'num' => $card->num,
                            'ord' => $card->type_ord
                        ];
                        $guestHands[] = $cardArr;
                    }
                }else{
                    foreach ($hostCard as $card) {
                        $cardArr = [
                            'color' => $card->color,
                            'num' => $card->num,
                            'ord' => $card->type_ord
                        ];
                        $hostHands[] = $cardArr;
                    }


                    foreach ($guestCard as $card) {
                        $cardArr = [
                            'ord' => $card->type_ord
                        ];
                        $guestHands[] = $cardArr;
                    }
                }


                $libraryCardCount = GameCard::find()->where(['room_id' => $room_id, 'type' => GameCard::TYPE_IN_LIBRARY])->orderBy('type_ord asc')->count();
                $tableCard = GameCard::find()->where(['room_id' => $room_id, 'type' => GameCard::TYPE_SUCCEEDED])->orderBy('type_ord asc')->all();
                $discardCardCount = GameCard::find()->where(['room_id' => $room_id, 'type' => GameCard::TYPE_DISCARDED])->orderBy('type_ord asc')->count();


                $table_cards = [0,0,0,0,0];
                foreach ($tableCard as $card) {
                    //TODO 完整性检查
                    $table_cards[$card->color]++;
                }

                $data['hostHands'] = $hostHands;
                $data['guestHands'] = $guestHands;
                $data['libraryCardsNum'] = $libraryCardCount;
                $data['discardCardsNum'] = $discardCardCount;

                $data['successCards'] = $table_cards;

                $data['cueNum'] = $game->cue_num;
                $data['chanceNum'] = $game->chance_num;
                $data['score'] = $game->score;
            }else{
                //TODO
            }
        }else{
            //TODO
        }
        return $data;
    }

    #弃牌操作
    public static function discard($typeOrd){

        list($isInGame, $roomId) = Game::isInGame();

        if(!$isInGame) {
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_IN_GAME_MSG,Game::EXCEPTION_DISCARD_NOT_IN_GAME_CODE);
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

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

    #出牌操作
    public static function play($typeOrd){

        list($isInGame, $roomId) = Game::isInGame();

        if(!$isInGame) {
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_IN_GAME_MSG,Game::EXCEPTION_DISCARD_NOT_IN_GAME_CODE);
        }

        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId);

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
                Game::end();
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


    //提示
    public static function cue($ord,$type){

        list($isInGame, $roomId) = Game::isInGame();

        $success = false;
        $msg = '';
        $data = [];
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $game = Game::find()->where(['room_id'=>$room_player->room_id,'status'=>Game::STATUS_PLAYING])->one();
            if($game){
                if($game->round_player_is_host==$room_player->is_host){
                    $gameCardCount = GameCard::find()->where(['room_id'=>$game->room_id])->count();
                    if($gameCardCount==Card::CARD_NUM_ALL){
                        //提示一张牌
                        list($success,$cards_ord, $msg) = GameCard::cue($game->room_id,$ord,$type);

                        if($success){
                            //插入日志 record
                            //TODO
                            $history = History::find()->where(['room_id'=>$game->room_id,'status'=>History::STATUS_PLAYING])->one();
                            if($history){
                                list($get_content_success,$content_param,$content) = HistoryLog::getContentByCue($game->room_id,$ord,$type,$cards_ord);
                                if($get_content_success){
                                    $historyLog = new HistoryLog();
                                    $historyLog->history_id = $history->id;
                                    $historyLog->type = HistoryLog::TYPE_CUE_CARD;
                                    $historyLog->content_param = $content_param;
                                    $historyLog->content = $content;
                                    $historyLog->save();
                                    //var_dump($historyLog->errors);exit;
                                }
                            }

                            //消耗一个提示数
                            Game::useCue($game->room_id);

                            //交换(下一个)回合
                            Game::changeRoundPlayer($game->room_id);

                            $cache = Yii::$app->cache;
                            $cache_key = 'game_info_no_update_'.$user_id;
                            $cache->set($cache_key,false);

                            $other_user = RoomPlayer::find()->where(['room_id'=>$game->room_id,'is_host'=>$room_player->is_host?0:1])->one();
                            if($other_user){
                                $cache_key2 = 'game_info_no_update_'.$other_user->user_id;
                                $cache->set($cache_key2,false);
                            }

                        }


                    }else{
                        $msg = '总卡牌数错误';
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

    /*
     *
     * 自动打牌（挂机）
     *
     * 1. 有剩余提示数 则随机提示一张牌的 颜色或者数字
     *
     * 2. 没有剩余提示数 则随机丢弃一张牌
     *
     */
    public static function autoPlay(){
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

    private static function recoverCue($room_id){
        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game){
            if($game->cue_num < Game::DEFAULT_CUE){
                $game->cue_num = $game->cue_num + 1;
                if($game->save())
                    return true;
            }
        }
        return false;
    }


    private static function useCue($room_id){
        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game){
            if($game->cue_num > 0 ){
                $game->cue_num = $game->cue_num - 1;
                if($game->save())
                    return true;
            }
        }
        return false;
    }

    private static function useChance($room_id){
        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game){
            if($game->chance_num > 0){
                $game->chance_num = $game->chance_num - 1;
                if($game->save())
                    return [true,$game->chance_num];
            }
        }
        return false;
    }

    private static function changeRoundPlayer($room_id){
        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game){
            $game->round_player_is_host = $game->round_player_is_host==1?0:1;
            $game->round_num = $game->round_num+1;
            if($game->save())
                return true;
        }
        return false;
    }

    private static function checkGame(){
        $result = false;
        $msg = '';
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player) {
            $room = Room::find()->where(['id' => $room_player->room_id])->one();
            if ($room) {
                $game = Game::find()->where(['room_id' => $room->id])->one();
                if ($game) {
                    if ($game->status == Game::STATUS_PLAYING) {
                        if ($game->chance_num > 0) {
                            //TODO 更多检测

                            $result = true;
                        } else {
                            $msg = '游戏剩余机会次数为0';
                        }
                    } else {
                        $msg = '游戏不是游玩状态';
                    }
                } else {
                    $msg = '游戏不存在';
                }
            }else{
                $msg = '房间不存在';
            }
        }else{
            $msg = '不在房间中';
        }
        return $result;
    }
}
