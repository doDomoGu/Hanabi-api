<?php

namespace app\models;


use app\components\cache\MyGameCache;
use app\components\cache\MyRoomCache;
use app\components\cache\RoomListCache;
use app\components\exception\GameException;
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

    const EXCEPTION_START_GAME_NOT_HOST_PLAYER_CODE  = 20018;
    const EXCEPTION_START_GAME_NOT_HOST_PLAYER_MSG   = '开始游戏操作，操作人不是主机玩家';
    const EXCEPTION_PLAY_NOT_PLAYER_ROUND_CODE  = 20019;
    const EXCEPTION_PLAY_NOT_PLAYER_ROUND_MSG   = '出牌操作，不是该玩家的回合';
    const EXCEPTION_PLAY_NOT_IN_GAME_CODE  = 20020;
    const EXCEPTION_PLAY_NOT_IN_GAME_MSG   = '出牌操作，不在游戏中';
    const EXCEPTION_CUE_NOT_IN_GAME_CODE  = 20021;
    const EXCEPTION_CUE_NOT_IN_GAME_MSG   = '提示操作，不在游戏中';
    const EXCEPTION_CUE_NOT_PLAYER_ROUND_CODE  = 20022;
    const EXCEPTION_CUE_NOT_PLAYER_ROUND_MSG   = '提示操作，不是该玩家的回合';


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

    public static function getOne($roomId, $check=true) {
        $game = self::findOne(['room_id'=>$roomId]);
        if($check){
            self::check($roomId);
        }
        return $game;
    }

    // 通过roomId 判断是否在游戏中
    public static function isPlaying($roomId){
        $game = self::getOne($roomId);
        return !!$game;
    }

    # 检查游戏状态
    # 参数：$roomId
    # 无返回值
    public static function check($roomId){
        $game = self::getOne($roomId, false);

        # 游戏未开始 检查结束
        if(!$game){
            return ;
        }

        # 游戏中
        # 获取房间和玩家信息
        $room =  Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;
        $isReady = $guestPlayer->is_ready == 1;

        # 主客机玩家有任何的错误
        if(!$hostPlayer || !$hostPlayer->user || !$guestPlayer || !$guestPlayer->user) {
            GameException::t('wrong_players');
        }

        # 客机玩家非准备状态
        if(!$isReady) {
            GameException::t('guest_player_not_ready');
        }

        # 剩余机会数应大于0
        if($game->chance_num < 1){
            GameException::t('wrong_chance_num');
        }

        # 剩余提示数应大于0
        if($game->cue_num < 0){
            GameException::t('wrong_cue_num');
        }

        # 总卡牌数不正确
        $gameCardCount = (int) GameCard::find()->where(['room_id'=>$roomId])->count();
        if($gameCardCount != Card::$total_num){
            GameException::t('wrong_game_card_total_num');
        }
    }

    public static function createOne($roomId){
        $game = new Game();
        $game->room_id = $roomId;
        $game->round_num = 1; //当前回合数 1
        $game->round_player_is_host = rand(0,1); //随机选择一个玩家开始第一个回合
        $game->cue_num = Game::DEFAULT_CUE; //剩余提示数
        $game->chance_num = Game::DEFAULT_CHANCE; //剩余机会数
        $game->status = Game::STATUS_PLAYING;  //TODO 暂时无用因为永远是1 PLAYING
        $game->score = 0; //当前分数
        if(!$game->save()){
            GameException::t('create_game_failure');
        }
    }

    /*
     * 结束游戏
     */

    public static function deleteOne($roomId){
        $room = Room::getOne($roomId);
        $guestPlayer = $room->guestPlayer;

        # 删除游戏数据
        Game::deleteAll(['room_id'=>$roomId]);
        GameCard::deleteAll(['room_id'=>$roomId]);

        # 修改客机玩家状态为"未准备"
        if($guestPlayer){
            $guestPlayer->is_ready = 0;
            $guestPlayer->save();
        }

    }

    public static function getCardInfo($roomId){
        $data = [];

        $game = self::getOne($roomId);
        $user_id = Yii::$app->user->id;
        //获取当前玩家角色  只获取对手手牌信息（花色和数字）  自己的手牌只获取排序信息
        $room_player = RoomPlayer::find()->where(['user_id' => $user_id, 'room_id' => $game->room_id])->one();
        if ($room_player) {
            $player_is_host = $room_player->is_host == 1;

            $hostCard = GameCard::find()->where(['room_id' => $roomId, 'type' => GameCard::TYPE_HOST_HANDS])->orderBy('type_ord asc')->all();
            $guestCard = GameCard::find()->where(['room_id' => $roomId, 'type' => GameCard::TYPE_GUEST_HANDS])->orderBy('type_ord asc')->all();


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


            $libraryCardCount = (int) GameCard::find()->where(['room_id' => $roomId, 'type' => GameCard::TYPE_IN_LIBRARY])->orderBy('type_ord asc')->count();
            $tableCard = GameCard::find()->where(['room_id' => $roomId, 'type' => GameCard::TYPE_SUCCEEDED])->orderBy('type_ord asc')->all();
            $discardCardCount = (int) GameCard::find()->where(['room_id' => $roomId, 'type' => GameCard::TYPE_DISCARDED])->orderBy('type_ord asc')->count();


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
            $data['score'] = (int) $game->score;
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


    /*
     * 指定房间的游戏得分+1
     */
    public static function addScore($roomId){
        $game = self::getOne($roomId);
        $game->score += 1;
        if(!$game->save()){
            GameException::t('add_score_failure');
        }
    }

    /*
     * 指定房间的游戏恢复一个提示数，最大不会超过默认初始提示数（Game::DEFAULT_CUE）
     */
    public static function recoverCue($roomId){
        $game = self::getOne($roomId);
        if($game->cue_num < Game::DEFAULT_CUE){
            $game->cue_num = $game->cue_num + 1;
            if(!$game->save()){
                GameException::t('recover_cue_failure');
            }
        }
    }

    /*
     * 指定房间的游戏消耗一个提示数，最小不会小于0
     */
    public static function useCue($roomId){
        $game = self::getOne($roomId);
        if($game->cue_num > 0 ){
            $game->cue_num = $game->cue_num - 1;
            if(!$game->save()){
                GameException::t('use_cue_failure');
            }
        }
    }

    /*
     * 指定房间的游戏消耗一个机会数，最小不会小于0
     */
    public static function useChance($roomId){
        $game = self::getOne($roomId);
        if($game->chance_num > 0){
            $game->chance_num = $game->chance_num - 1;
            if(!$game->save()){
                GameException::t('use_chance_failure');
            }
        }
    }

    /*
     * 指定房间的游戏进入下一个回合
     */
    public static function nextRound($roomId){
        $game = self::getOne($roomId);
        # 查看对家是否有手牌，有则交换操作玩家
        $cardType = $game->round_player_is_host == 1 ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

        $handsCount = (int) GameCard::find()->where(['room_id'=>$roomId,'type'=>$cardType])->count();

        if($handsCount > 0) {
            $game->round_player_is_host = $game->round_player_is_host == 1 ? 0 : 1;
        }

        $game->round_num = $game->round_num + 1;

        if(!$game->save()) {
            GameException::t('change_round_player_failure');
        }
    }

}
