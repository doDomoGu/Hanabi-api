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


    public static function start(){
        $success = false;
        $msg = '';
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            //只有玩家1可以进行"开始游戏操作"
            if($room_player->is_host==1){
                $room = Room::find()->where(['id'=>$room_player->room_id])->one();
                if($room){
                    //存在对应的game 即表示有开始的游戏
                    $game = Game::find()->where(['room_id'=>$room->id])->one();
                    if(!$game){
                        $room_player_count = RoomPlayer::find()->where(['room_id'=>$room->id])->count();
                        if($room_player_count == 2){
                            $guest_player = RoomPlayer::find()->where(['room_id'=>$room->id,'is_host'=>0])->one();
                            if($guest_player){
                                if($guest_player->is_ready==1){
                                    if(self::createOne($room->id)){

                                        //新建log 相关
                                        $history = new History();
                                        $history->room_id = $room->id;
                                        $history->status = History::STATUS_PLAYING;
                                        $history->score = 0;
                                        if($history->save()){
                                            $historyPlayer = new HistoryPlayer();
                                            $historyPlayer->history_id = $history->id;
                                            $historyPlayer->user_id = $room_player->user_id;
                                            $historyPlayer->is_host = 1;
                                            $historyPlayer->save();

                                            $historyPlayer = new HistoryPlayer();
                                            $historyPlayer->history_id = $history->id;
                                            $historyPlayer->user_id = $guest_player->user_id;
                                            $historyPlayer->is_host = 0;
                                            $historyPlayer->save();
                                        }

                                        $roomPlayers = RoomPlayer::find()->where(['room_id' => $room->id])->all();
                                        $cache = Yii::$app->cache;
                                        foreach ($roomPlayers as $player) {
                                            $cacheKey = 'room_info_no_update_'.$player->user_id;
                                            $cache->set($cacheKey,false);
                                        }

                                        $success = true;
                                    }else{
                                        $msg = '创建游戏失败';
                                    }
                                }else{
                                    $msg = '来宾玩家的状态不是"已准备"';
                                }
                            }else{
                                $msg = '来宾玩家不存在';
                            }
                        }else{
                            $msg = '房间中人数不等于2，数据错误';
                        }
                    }else{
                        $msg = '游戏已开始';
                    }
                }else{
                    $msg = '房间不存在！';
                }
            }else{
                $msg = '玩家角色错误';
            }
        }else{
            $msg = '你不在房间中，错误';
        }

        return [$success,$msg];
    }

    private static function createOne($room_id){
        $success = false;
        $room = Room::find()->where(['id'=>$room_id])->one();
        if($room){
            $game = new Game();
            $game->room_id = $room->id;
            $game->round_num = 1;
            $game->round_player_is_host = rand(0,1); //随机选择一个玩家开始第一个回合
            $game->cue_num = self::DEFAULT_CUE;
            $game->chance_num = self::DEFAULT_CHANCE;
            $game->status = Game::STATUS_PLAYING;
            $game->score = 0;
            if($game->save()){
                if(GameCard::initLibrary($room_id)){
                    for($i=0;$i<5;$i++){ //玩家 1 2 各模五张牌
                        GameCard::drawCard($room_id,1);
                        GameCard::drawCard($room_id,0);
                    }
                    $success = true;
                }
            }else{
                echo 11;exit;
                //TODO 错误处理
            }

        }else{
            echo 44;exit;
            //TODO 错误处理
        }
        return $success;
    }


    public static function end(){
        $success = false;
        $msg = '';
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            /*//TODO 暂时只有玩家1可以进行"结束游戏操作"
            if($room_player->is_host == 1){*/
                $room = Room::find()->where(['id'=>$room_player->room_id])->one();
                if($room){
                    $game = Game::find()->where(['room_id'=>$room->id,'status'=>Game::STATUS_PLAYING])->all();
                    if($game) {
                        // 1.删除游戏数据
                        Game::deleteAll(['room_id'=>$room->id]);
                        GameCard::deleteAll(['room_id'=>$room->id]);

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

                        $roomPlayers = RoomPlayer::find()->where(['room_id' => $room->id])->all();
                        $cache = Yii::$app->cache;
                        foreach ($roomPlayers as $player) {
                            $cacheKey = 'room_info_no_update_'.$player->user_id;
                            $cache->set($cacheKey,false);
                        }

                        $success = true;
                    }else{
                        $msg = '你所在房间游戏未开始，错误';
                    }
                }else{
                    $msg = '房间不存在！';
                }
            /*}else{
                $msg = '玩家角色错误';
            }*/
        }else{
            $msg = '你不在房间中/不止在一个房间中，错误';
        }

        return [$success,$msg];
    }

    public static function getInfo($mode,$force){
        $success = false;
        $msg = '';
        $data = [];
        $user_id = Yii::$app->user->id;
        $cache = Yii::$app->cache;
        $cache_key  = 'game_info_no_update_'.$user_id;  //存在则不更新房间信息
        $cache_data = $cache->get($cache_key);
        if(!$force && $cache_data){
            $data = ['noUpdate'=>true];
            $success = true;
        }else {


            $room_player = RoomPlayer::find()->where(['user_id' => $user_id])->one();
            if ($room_player) {
                $game = Game::find()->where(['room_id' => $room_player->room_id])->one();
                if ($game) {
                    $data['isPlaying'] = true;
                    if ($mode == 'all') {
                        $gameCardCount = GameCard::find()->where(['room_id' => $game->room_id])->count();
                        if ($gameCardCount == Card::CARD_NUM_ALL) {

                            $data['game'] = [
                                'roundNum' => $game->round_num,
                                'roundPlayerIsHost' => $game->round_player_is_host == 1,
                            ];

                            $cardInfo = self::getCardInfo($game->room_id);
                            $data['card'] = $cardInfo;


                            list(, , $data['log']) = HistoryLog::getList($game->room_id);

                            $data['game']['lastUpdated'] = HistoryLog::getLastUpdate($game->room_id);

                            $cache->set($cache_key, true);
                            /*}*/

                            $success = true;
                        } else {
                            $msg = '总卡牌数错误';
                        }
                    }else{
                        $success = true;
                    }
                } else {
                    $msg = '你所在房间游戏未开始，错误';
                }
            } else {
                $msg = '你不在房间中，错误';
            }
        }

        return [$success,$msg,$data];
    }

    private static function getCardInfo($room_id){
        $data = [];

        $game = Game::find()->where(['room_id'=>$room_id])->one();
        if($game) {
            $user_id = Yii::$app->user->id;
            //获取当前玩家角色  只获取对手手牌信息（花色和数字）  自己的手牌只获取排序信息
            $room_player = RoomPlayer::find()->where(['user_id' => $user_id, 'room_id' => $game->room_id])->one();
            if ($room_player) {
                $player_is_host = $room_player->is_host;
                //房主手牌 序号 0~4
                $type_orders_is_host = [0,1,2,3,4];
                //来宾手牌 序号5~9
                $type_orders_not_host = [5,6,7,8,9];


                $hostCard = GameCard::find()->where(['room_id' => $room_id, 'type' => GameCard::TYPE_IN_HAND, 'type_ord' => $type_orders_is_host])->orderBy('type_ord asc')->all();
                $guestCard = GameCard::find()->where(['room_id' => $room_id, 'type' => GameCard::TYPE_IN_HAND, 'type_ord' => $type_orders_not_host])->orderBy('type_ord asc')->all();


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
                $tableCard = GameCard::find()->where(['room_id' => $room_id, 'type' => GameCard::TYPE_SUCCESSED])->orderBy('type_ord asc')->all();
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


    public static function discard($ord){
        $success = false;
        $msg = '';
        $data = [];
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $game = Game::find()->where(['room_id'=>$room_player->room_id,'status'=>Game::STATUS_PLAYING])->one();
            if($game){
                $room_id = $game->room_id;
                if($game->round_player_is_host==$room_player->is_host){
                    $gameCardCount = GameCard::find()->where(['room_id'=>$room_id])->count();

                    if($gameCardCount==Card::CARD_NUM_ALL){
                        //丢弃一张牌
                        list($discard_success,$card_ord) =GameCard::discardCard($room_id,$ord);
                        if($discard_success){
                            //恢复一个提示数
                            self::recoverCue($room_id);

                            //插入日志 record
                            //TODO
                            $history = History::find()->where(['room_id'=>$room_id,'status'=>History::STATUS_PLAYING])->one();
                            if($history){
                                list($get_content_success,$content_param,$content) = HistoryLog::getContentByDiscard($room_id,$card_ord);
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
                            self::changeRoundPlayer($room_id);

                            $cache = Yii::$app->cache;
                            $cache_key = 'game_info_no_update_'.$user_id;
                            $cache->set($cache_key,false);

                            $other_user = RoomPlayer::find()->where(['room_id'=>$room_id,'is_host'=>$room_player->is_host?0:1])->one();
                            if($other_user){
                                $cache_key2 = 'game_info_no_update_'.$other_user->user_id;
                                $cache->set($cache_key2,false);
                            }

                            $success = true;
                        }else{
                            $msg = '弃牌发生问题';
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

    //打出一张牌
    public static function play($ord){
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
                        if(in_array($ord,[0,1,2,3,4])){

                            //打出一张牌
                            list($success,$data['play_result'],$card_ord) = GameCard::playCard($game->room_id,$ord);

                            //给这个玩家摸一张牌
                            GameCard::drawCard($game->room_id,$room_player->is_host);

                            if($data['play_result']){
                                //恢复一个提示数
                                self::recoverCue($game->room_id);
                            }else{
                                //消耗一次机会
                                self::useChance($game->room_id);

                                $result = self::checkGame();
                                if(!$result){
                                    self::end();
                                }
                            }


                            //插入日志 record
                            $history = History::find()->where(['room_id'=>$game->room_id,'status'=>History::STATUS_PLAYING])->one();
                            if($history){
                                list($get_content_success,$content_param,$content) = HistoryLog::getContentByPlay($game->room_id,$card_ord,$data['play_result']);
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
                            self::changeRoundPlayer($game->room_id);


                            $cache = Yii::$app->cache;
                            $cache_key = 'game_info_no_update_'.$user_id;
                            $cache->set($cache_key,false);

                            $other_user = RoomPlayer::find()->where(['room_id'=>$game->room_id,'is_host'=>$room_player->is_host?0:1])->one();
                            if($other_user){
                                $cache_key2 = 'game_info_no_update_'.$other_user->user_id;
                                $cache->set($cache_key2,false);
                            }
                        }else{
                            $msg = '卡牌顺序错误';
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


    //提示
    public static function cue($ord,$type){
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
                        list($success,$cards_ord) = GameCard::cue($game->room_id,$ord,$type);

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
                            self::useCue($game->room_id);

                            //交换(下一个)回合
                            self::changeRoundPlayer($game->room_id);

                            $cache = Yii::$app->cache;
                            $cache_key = 'game_info_no_update_'.$user_id;
                            $cache->set($cache_key,false);

                            $other_user = RoomPlayer::find()->where(['room_id'=>$game->room_id,'is_host'=>$room_player->is_host?0:1])->one();
                            if($other_user){
                                $cache_key2 = 'game_info_no_update_'.$other_user->user_id;
                                $cache->set($cache_key2,false);
                            }

                        }else{
                            $msg = '提示失败';
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

                        return self::cue(rand(0,4),rand(0,1));

                    }else{

                        return self::discard(rand(0,4));

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
            if($game->cue_num < self::DEFAULT_CUE){
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
