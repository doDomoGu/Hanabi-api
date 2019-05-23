<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
/**
 * This is the model class for table "history_log".
 *
 * @property integer $id
 * @property integer $history_id
 * @property string $content_param
 * @property string $content
 * @property string $created_at
 */
class HistoryLog extends \yii\db\ActiveRecord
{
    const TYPE_START_GAME = 1;
    const TYPE_PLAY_CARD = 2;
    const TYPE_DISCARD_CARD  = 3;
    const TYPE_CUE_CARD = 4;
    const TYPE_END_GAME = 5;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%history_log}}';
    }


    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at']
                ],
                'value' => new Expression('NOW()'),  //时间戳（数字型）转为 日期字符串
            ]
        ];
    }
    
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['history_id','type'], 'required'],
            [['history_id','type'], 'integer'],
            [['content_param', 'content'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'history_id' => 'History ID',
            'content_param' => 'Content Param',
            'content' => 'Content',
            'created_at' => 'Created At',
        ];
    }


    public static function record($roomId, $logType, $params = []){
        $history = History::find()->where(['room_id'=>$roomId,'status'=>History::STATUS_PLAYING])->one();
        if($history){

            if($logType == 'start'){
                $historyLog = new HistoryLog();
                $historyLog->history_id = $history->id;
                $historyLog->type = HistoryLog::TYPE_START_GAME;
                $historyLog->content = '游戏开始';
                $historyLog->save();
            } else if($logType == 'end'){
                $historyLog = new HistoryLog();
                $historyLog->history_id = $history->id;
                $historyLog->type = HistoryLog::TYPE_END_GAME;
                $historyLog->content = '游戏结束';
                $historyLog->save();
            } else if($logType == 'discard'){
                list($get_content_success,$content_param,$content) = HistoryLog::getContentByDiscard($roomId, $params['cardOrd']);
                if($get_content_success){
                    $historyLog = new HistoryLog();
                    $historyLog->history_id = $history->id;
                    $historyLog->type = HistoryLog::TYPE_DISCARD_CARD;
                    $historyLog->content_param = $content_param;
                    $historyLog->content = $content;
                    $historyLog->save();
                }
            } else if($logType == 'play') {
                list($get_content_success,$content_param,$content) = HistoryLog::getContentByPlay($roomId,$params['cardOrd'],$params['playSuccess']);
                if($get_content_success){
                    $historyLog = new HistoryLog();
                    $historyLog->history_id = $history->id;
                    $historyLog->type = HistoryLog::TYPE_PLAY_CARD;
                    $historyLog->content_param = $content_param;
                    $historyLog->content = $content;
                    $historyLog->save();
                }
            } else if($logType == 'cue') {
                list($get_content_success,$content_param,$content) = HistoryLog::getContentByCue($roomId, $params['cardOrd'], $params['cueType'], $params['cueCardsOrd']);
                if($get_content_success){
                    $historyLog = new HistoryLog();
                    $historyLog->history_id = $history->id;
                    $historyLog->type = HistoryLog::TYPE_CUE_CARD;
                    $historyLog->content_param = $content_param;
                    $historyLog->content = $content;
                    $historyLog->save();
                }
            }
        }
    }


    public static function getContentByDiscard($room_id,$card_ord){
        $success = false;
        $content_param = '';
        $content = '';
        $game = Game::find()->where(['room_id'=>$room_id,'status'=>Game::STATUS_PLAYING])->one();
        if($game){
            $round_num = $game->round_num;
            $card = GameCard::find()->where(['room_id'=>$room_id,'ord'=>$card_ord])->one();
            if($card){

                $player = User::find()->where(['id'=>Yii::$app->user->id])->one();

                $replace_param = [
                    'round_num'=>$round_num,
                    'card_color'=>$card->color,
                    'card_num'=>$card->num,
                    'player_name'=>$player->nickname,
                    'cue_num'=>$game->cue_num,
                    'chance_num'=>$game->chance_num
                ];

                $template = '回合[round_num]:[player_name]丢弃了[card_color]-[card_num],恢复一次提示[剩余提示次数:[cue_num]次]';

                $param = array_merge(
                    $replace_param,
                    [
                        'player_id'=>Yii::$app->user->id,
                        'template'=>$template
                    ]
                );

                $content_param = json_encode($param);

                $content = self::replaceContent($replace_param,$template);

                $success = true;
            }
        }

        return [$success,$content_param,$content];
    }

    public static function getContentByPlay($room_id,$card_ord,$result){
        $success = false;
        $content_param = '';
        $content = '';
        $game = Game::find()->where(['room_id'=>$room_id,'status'=>Game::STATUS_PLAYING])->one();
        if($game){
            $round_num = $game->round_num;
            $card = GameCard::find()->where(['room_id'=>$room_id,'ord'=>$card_ord])->one();
            if($card){

                $player = User::find()->where(['id'=>Yii::$app->user->id])->one();

                $replace_param = [
                    'round_num'=>$round_num,
                    'play_result'=>$result,
                    'card_color'=>$card->color,
                    'card_num'=>$card->num,
                    'player_name'=>$player->nickname,
                    'cue_num'=>$game->cue_num,
                    'chance_num'=>$game->chance_num
                ];
                if($result) {
                    $template = '回合[round_num]:[player_name]成功地打出了[card_color]-[card_num],恢复一次提示[剩余提示次数:[cue_num]次]';
                }else{
                    $template = '回合[round_num]:[player_name]错误地打出了[card_color]-[card_num],失去一次机会[剩余机会次数:[chance_num]次]';
                }

                $param = array_merge(
                    $replace_param,
                    [
                        'player_id'=>Yii::$app->user->id,
                        'template'=>$template
                    ]
                );

                $content_param = json_encode($param);

                $content = self::replaceContent($replace_param,$template);

                $success = true;
            }
        }

        return [$success,$content_param,$content];
    }

    public static function getContentByCue($room_id,$card_ord,$cue_type,$cards_ord){
        $success = false;
        $content_param = '';
        $content = '';
        $game = Game::find()->where(['room_id'=>$room_id,'status'=>Game::STATUS_PLAYING])->one();
        if($game){
            $player_is_host = $game->round_player_is_host;
            $round_num = $game->round_num;
            $card = GameCard::find()->where(['room_id'=>$room_id,'ord'=>$card_ord])->one();
            if($card){

                $player = User::find()->where(['id'=>Yii::$app->user->id])->one();
                $cards_ord2 = [];
                foreach($cards_ord as $c){
                    $cards_ord2[] = $c+1;
                }

                $cards_ord_str = implode(', ',$cards_ord2);
                $replace_param = [
                    'round_num'=>$round_num,
                    'card_color'=>$card->color,
                    'card_num'=>$card->num,
                    'player_name'=>$player->nickname,
                    'cue_num'=>$game->cue_num,
                    'cards_ord_str'=>$cards_ord_str
                ];
                $template = '';


                if($cue_type=='color'){
                    $template = '回合[round_num]:[player_name]提示了第[cards_ord_str]张是[card_color]色';
                }elseif($cue_type == 'num'){
                    $template = '回合[round_num]:[player_name]提示了第[cards_ord_str]张是[card_num]';
                }
                $template .= ',消耗一次提示[剩余提示次数:[cue_num]次]';


                $param = array_merge(
                    $replace_param,
                    [
                        'cards_ord'=>$cards_ord,
                        'cue_type'=>$cue_type,
                        'player_id'=>Yii::$app->user->id,
                        'template'=>$template
                    ]
                );

                $content_param = json_encode($param);

                $content = self::replaceContent($replace_param,$template);

                $success = true;
            }
        }

        return [$success,$content_param,$content];
    }


    private static function replaceContent($param,$template){
        $content = $template;
        $search = [];
        $replace = [];
        foreach($param as $k=>$v){
            if(in_array($k,['template'])){
                continue;
            }

            $search[] = '['.$k.']';

            if($k=='card_color'){
                $replace[] = Card::$colors[$v];
            }else if($k=='card_num'){
                $replace[] = Card::$numbers[$v];
            }else{
                $replace[] = $v;
            }
        }

        if(!empty($search)) {
            $content = str_replace($search, $replace, $template);
        }

        return $content;
    }



    public static function getList($room_id) {
        $success = false;
        $msg = '';
        $data = [];
        $game = Game::find()->where(['room_id' => $room_id, 'status' => Game::STATUS_PLAYING])->one();
        if ($game) {
            $history = History::find()->where(['room_id' => $room_id, 'status' => History::STATUS_PLAYING])->one();
            if ($history) {
                $logs = HistoryLog::find()->where(['history_id' => $history->id])->orderBy('created_at asc')->all();
                foreach ($logs as $log) {
                    $data[] = $log->content.' ('.date('Y-m-d H:i:s',strtotime($log->created_at)).')';
                }
                $success = true;
            } else {
                $msg = 'history不存在';
            }
        } else {
            $msg = '游戏不存在';
        }
        return [$success,$msg,$data];
    }


    public static function getLastUpdate($room_id) {
        $game = Game::find()->where(['room_id' => $room_id, 'status' => Game::STATUS_PLAYING])->one();
        if ($game) {
            $history = History::find()->where(['room_id' => $room_id, 'status' => History::STATUS_PLAYING])->one();
            if ($history) {
                $lastLog = HistoryLog::find()->where(['history_id' => $history->id])->orderBy('created_at desc')->one();
                if($lastLog){
                    return $lastLog->created_at;
                }
            }
            return $game->updated_at;
        } else {
            return null;
        }
    }
}
