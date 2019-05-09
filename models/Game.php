<?php

namespace app\models;


use app\components\cache\MyGameCache;
use app\components\cache\MyRoomCache;
use app\components\cache\RoomListCache;
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
    const EXCEPTION_DISCARD_NOT_PLAYER_ROUND_CODE  = 20017;
    const EXCEPTION_DISCARD_NOT_PLAYER_ROUND_MSG   = '弃牌操作，不是该玩家的回合';
    const EXCEPTION_START_GAME_NOT_HOST_PLAYER_CODE  = 20018;
    const EXCEPTION_START_GAME_NOT_HOST_PLAYER_MSG   = '开始游戏操作，操作人不是主机玩家';
    const EXCEPTION_PLAY_NOT_PLAYER_ROUND_CODE  = 20019;
    const EXCEPTION_PLAY_NOT_PLAYER_ROUND_MSG   = '出牌操作，不是该玩家的回合';
    const EXCEPTION_PLAY_NOT_IN_GAME_CODE  = 20020;
    const EXCEPTION_PLAY_NOT_IN_GAME_MSG   = '出牌操作，不在游戏中';
    const EXCEPTION_CUE_NOT_IN_GAME_CODE  = 20021;
    const EXCEPTION_CUE_NOT_IN_GAME_MSG   = '提示操作，不在游戏中';


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

    // 通过roomId 判断是否在游戏中状态
    public static function isPlaying($roomId){
        $game = Game::find()->where(['room_id'=>$roomId])->one();
        $gameCardCount = (int) GameCard::find()->where(['room_id'=>$roomId])->count();
        #不在游戏中
        if(!$game) {
            #不在游戏中，但是有卡牌存在
            if($gameCardCount > 0) {
                throw new \Exception(Game::EXCEPTION_NOT_IN_GAME_HAS_CARD_MSG,Game::EXCEPTION_NOT_IN_GAME_HAS_CARD_CODE);
            }
            #返回false
            return false;
        }
        #在游戏中
        #以下是检查游戏状态
        //list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
        $room =  Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;
        $isReady = $guestPlayer->is_ready == 1;

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
        if($gameCardCount != Card::CARD_NUM_ALL){
            throw new \Exception(Game::EXCEPTION_WRONG_CARD_NUM_ALL_MSG,Game::EXCEPTION_WRONG_CARD_NUM_ALL_CODE);
        }
        return true;
    }

//    public static function check($roomId, $notCheck = []){
//
//        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
//
//        $game = Game::find()->where(['room_id'=>$roomId])->one();
//        $gameCardCount = (int) GameCard::find()->where(['room_id'=>$roomId])->count();
//
//        if(!$hostPlayer || !$guestPlayer) {
//            throw new \Exception(Game::EXCEPTION_WRONG_PLAYERS_MSG,Game::EXCEPTION_WRONG_PLAYERS_CODE);
//        }
//        if(!$isReady) {
//            throw new \Exception(Game::EXCEPTION_GUEST_PLAYER_NOT_READY_MSG,Game::EXCEPTION_GUEST_PLAYER_NOT_READY_CODE);
//        }
//        /*if ($game->status != Game::STATUS_PLAYING) {
//
//        }*/
//        if(!in_array('chance_num', $notCheck)){
//            if($game->chance_num < 1){
//                throw new \Exception(Game::EXCEPTION_WRONG_CHANCE_NUM_MSG,Game::EXCEPTION_WRONG_CHANCE_NUM_CODE);
//            }
//        }
//        if($game->cue_num < 0){
//            throw new \Exception(Game::EXCEPTION_WRONG_CUE_NUM_MSG,Game::EXCEPTION_WRONG_CUE_NUM_CODE);
//        }
//        #游戏中，但是总卡牌数不对
//        if($gameCardCount != Card::CARD_NUM_ALL){
//            throw new \Exception(Game::EXCEPTION_WRONG_CARD_NUM_ALL_MSG,Game::EXCEPTION_WRONG_CARD_NUM_ALL_CODE);
//        }
//    }

    public static function createOne($roomId){
        $room =  Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;
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
            GameCard::initLibrary($roomId);
            for($i=0;$i<5;$i++){ //主机/客机玩家 各模五张牌
                GameCard::drawCard($roomId,true);
                GameCard::drawCard($roomId,false);
            }
            #创建游戏History
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
        }else{
            throw new \Exception(Game::EXCEPTION_CREATE_GAME_FAILURE_MSG,Game::EXCEPTION_CREATE_GAME_FAILURE_CODE);
        }

        MyRoomCache::clear($hostPlayer->user_id);
        MyRoomCache::clear($guestPlayer->user_id);
    }

    public static function deleteOne($roomId){
        $room = Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

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

        #清除主客玩家的房间缓存  //TODO
        MyRoomCache::clear($hostPlayer->user_id);
        MyRoomCache::clear($guestPlayer->user_id);
    }

    public static function getInfo($roomId){
        return 2;
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

    /*
     *  出牌操作
     *  - 检查当前玩家是否是本回合玩家
     *  - 判断出牌是否成功
     *      > 出牌成功
     *          * 将牌移入成功燃放区 (type_succeeded)
     *          * 游戏分数 加一
     *          * 恢复一个提示数（如果提示数没有超过最大值）
     *          * 记录"出牌成功"日志
     *      > 出牌失败
     *          * 将牌移入弃牌区 (type_discard)
     *          * 游戏的剩余失败机会数 减一
     *          * 记录"出牌失败"日志
     *  - 检查游戏状态
     *      > 以下状态会进入游戏结束流程
     *          * 游戏的剩余失败机会数(chance_num) 等于 0
     *          * 牌都出完了：主客机玩家手牌数 + 牌库卡牌数 等于 0
     *          * 游戏分数不能再增加 （获得满分25分或者剩余的牌（手牌+牌库的牌）都不能再被成功打出）
     *      > 继续游戏
     *          * 移动手牌牌序
     *          * 摸一张牌
     *          * 交换玩家回合
     *
     */

    public static function playCard($roomId, $typeOrd){
        $myGame = MyGame::getInfo();

        if($game->round_player_is_host != $isHost){
            #不是当前玩家操作的回合
            throw new \Exception(Game::EXCEPTION_PLAY_NOT_PLAYER_ROUND_MSG,Game::EXCEPTION_PLAY_NOT_PLAYER_ROUND_CODE);
        }



        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
        $game = Game::find()->where(['room_id'=>$roomId])->one();
        if(!$game){
            throw new \Exception(GameCard::EXCEPTION_NOT_IN_GAME_MSG,GameCard::EXCEPTION_NOT_IN_GAME_CODE);
        }
        if($game->round_player_is_host != $isHost){ #不是当前玩家操作的回合
            throw new \Exception(Game::EXCEPTION_PLAY_NOT_PLAYER_ROUND_MSG,Game::EXCEPTION_PLAY_NOT_PLAYER_ROUND_CODE);
        }

        #根据isHost，选择GameCard的type
        $cardType = $isHost ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

        if(!in_array($typeOrd, GameCard::$handsTypeOrds)) {
            throw new \Exception(GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_MSG,GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_CODE);
        }

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
            $cardSelected->type_ord = 0;
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


//        return [$result,$cardOrd];
    }

    public static function checkIsPlayerRound(){

    }



    public static function discardCard($roomId, $typeOrd){
        list($room, list($hostPlayer, $guestPlayer, $isHost, $isReady)) = Room::getInfo($roomId, true);
        $game = Game::find()->where(['room_id'=>$roomId])->one();
        if($game->round_player_is_host != $isHost){ #不是当前玩家操作的回合
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_PLAYER_ROUND_MSG,Game::EXCEPTION_DISCARD_NOT_PLAYER_ROUND_CODE);
        }

        #根据isHost，选择GameCard的type
        $cardType = $isHost ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

        if(!in_array($typeOrd, GameCard::$handsTypeOrds)) {
            throw new \Exception(GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_MSG,GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_CODE);
        }

        #找到所选择的牌
        $cardSelected = GameCard::find()->where(['room_id'=>$roomId,'type'=>$cardType,'type_ord'=>$typeOrd])->one();
        if(!$cardSelected){
            throw new \Exception(GameCard::EXCEPTION_NOT_FOUND_HANDS_MSG,GameCard::EXCEPTION_NOT_FOUND_HANDS_CODE);
        }

        #卡牌固定排序（唯一不变）
        $cardOrd = $cardSelected->ord;

        #将牌丢进弃牌堆
        $cardSelected->type = GameCard::TYPE_DISCARDED;
        $cardSelected->type_ord = GameCard::getInsertDiscardOrd($roomId);
        if(!$cardSelected->save()){
            throw new \Exception(GameCard::EXCEPTION_DISCARD_FAILURE_MSG,GameCard::EXCEPTION_DISCARD_FAILURE_CODE);
        }

        #牌序移动
        GameCard::moveHandCardsByLackOfCard($roomId, $isHost, $typeOrd);

        #摸牌
        GameCard::drawCard($roomId, $isHost);


        //恢复一个提示数
        Game::recoverCue($roomId);
        //插入日志 record
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

        MyGameCache::clear($hostPlayer->user_id);
        MyGameCache::clear($guestPlayer->user_id);

    }

    public static function cueCard($roomId, $typeOrd, $cueType) {
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
                        list($success,$cards_ord, $msg) = GameCard::cue($roomId, $typeOrd, $cueType);

                        if($success){
                            //插入日志 record
                            //TODO
                            $history = History::find()->where(['room_id'=>$game->room_id,'status'=>History::STATUS_PLAYING])->one();
                            if($history){
                                list($get_content_success,$content_param,$content) = HistoryLog::getContentByCue($roomId, $typeOrd, $cueType,$cards_ord);
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

                            MyGameCache::clear($user_id);

                            $other_user = RoomPlayer::find()->where(['room_id'=>$game->room_id,'is_host'=>$room_player->is_host?0:1])->one();
                            if($other_user){
                                MyGameCache::clear($other_user->user_id);
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
    }

    public static function recoverCue($room_id){
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


    public static function useCue($room_id){
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

    public static function useChance($room_id){
        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game){
            if($game->chance_num > 0){
                $game->chance_num = $game->chance_num - 1;
                if($game->save())
                    return [true, (int) $game->chance_num];
            }else{
                throw new \Exception(GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_MSG,GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_CODE);
            }
        }else{
            throw new \Exception(Game::EXCEPTION_NOT_FOUND_GAME_MSG,Game::EXCEPTION_NOT_FOUND_GAME_CODE);
        }
        return [false, -1];
    }

    public static function changeRoundPlayer($room_id){
        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game){
            $game->round_player_is_host = $game->round_player_is_host==1?0:1;
            $game->round_num = $game->round_num+1;
            if($game->save())
                return true;
        }
        return false;
    }


}
