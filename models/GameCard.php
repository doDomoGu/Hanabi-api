<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "game_card".
 *
 * @property integer $room_id
 * @property integer $type
 * @property integer $type_ord
 * @property integer $color
 * @property integer $num
 * @property integer $ord
 * @property string $updated_at
 */
class GameCard extends ActiveRecord
{
    const TYPE_IN_HAND = 1;
    const TYPE_IN_LIBRARY = 2;
    const TYPE_SUCCESSED = 3;
    const TYPE_DISCARDED = 4;

    public static $host_hands_type_ord = [0,1,2,3,4];
    public static $guest_hands_type_ord = [5,6,7,8,9];
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%game_card}}';
    }

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
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['room_id'], 'required'],
            [['room_id', 'type', 'type_ord', 'color', 'num', 'ord'], 'integer'],
            [['updated_at'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'room_id' => '房间ID',  //对应房间ID
            'type' => 'Type',   //牌类型 1:玩家手上,2:牌库中,3:桌面上(燃放成功),4:弃牌堆(包括燃放失败也进入弃牌堆)
            'type_ord' => 'Type Ord', //初始值 和 ord字段一样代表生成的随机花色和颜色排序（0至49），根据type不同，意义不同:1在玩家手中表示 从左至右的顺序(0-4是主机玩家 5-9是来宾玩家),3设置为0，4表示弃牌堆的顺序从0开始增加 越大表示越近丢弃的
            'color' => 'Color', //颜色Card中colors数组 0-4
            'num' => 'Num', //数字Card中numbers数组 0-9
            'ord' => 'Ord', //初始排序 0-49  #不会改变
            'updated_at' => 'Updated At',
        ];
    }

    //初始化牌库
    public static function initLibrary($room_id){
        $return = false;
        $game = Game::find()->where(['room_id'=>$room_id,'status'=>Game::STATUS_PLAYING])->one();
        if($game){
            $cardCount = self::find()->where(['room_id'=>$game->room_id])->count();
            if($cardCount==0){
                $cardArr = [];
                foreach(Card::$colors as $k=>$v){
                    foreach(Card::$numbers as $k2=>$v2){
                        $cardArr[] = [$k,$k2];
                    }
                }
                shuffle($cardArr);

                $insertArr = [];
                $ord = 0;
                foreach($cardArr as $c){
                    $insertArr[] = [$room_id,self::TYPE_IN_LIBRARY,$ord,$c[0],$c[1],$ord,date('Y-m-d H:i:s')];
                    $ord++;
                }

                Yii::$app->db->createCommand()->batchInsert(
                    self::tableName(),
                    ['room_id','type','type_ord','color','num','ord','updated_at'],
                    $insertArr
                )->execute();

                $cards = GameCard::find()->where(['room_id'=>$game->room_id])->count();
                if($cards==Card::CARD_NUM_ALL){
                    $return = true;
                }else{
                    //TODO 错误处理
                }
            }else{
                //TODO 错误处理

                //echo 'game card exist';exit;
            }
        }else{
            //TODO 错误处理

            //game not exit
        }
        return $return;
    }

    //摸一张牌
    public static function drawCard($room_id,$player_is_host){
        $return = false;
        //统计牌的总数 应该为50张
        $count = self::find()->where(['room_id'=>$room_id])->count();
        if($count==Card::CARD_NUM_ALL){
            //选取牌库上的第一张牌
            $card = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_LIBRARY])->orderBy('type_ord asc')->one();
            if($card){
                //根据player_is_host 限制type_ord 范围
                if($player_is_host==1){
                    //房主 序号 0~4
                    $type_orders = self::$host_hands_type_ord;
                }else{
                    //来宾 序号5~9
                    $type_orders = self::$guest_hands_type_ord;
                }
                //最多5张手牌
                $player_card_count = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND,'type_ord'=>$type_orders])->count();
                if($player_card_count<5){ //小于5张才能摸牌
                    //查找玩家手上排序最大的牌，确定摸牌的序号 type_ord
                    $the_biggest_card = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND,'type_ord'=>$type_orders])->orderBy('type_ord desc')->one();
                    if($the_biggest_card){
                        $ord = $the_biggest_card->type_ord + 1;
                    }else{
                        if($player_is_host==1){
                            $ord = 0;
                        }else{
                            $ord = 5;
                        }
                    }
                    $card->type = self::TYPE_IN_HAND;
                    $card->type_ord = $ord;
                    if($card->save()){
                        $return = true;
                    }
                }else{
                    echo '手牌不能超过5张';
                }

            }else{
                echo 'no card to draw';
            }
        }else{
            echo 'game card num wrong';
        }
        return $return;
    }


    public static function discardCard($room_id,$type_ord){
        $success = false;
        $card_ord = -1;
        $msg = '';
        //统计牌的总数 应该为50张
        $count = self::find()->where(['room_id'=>$room_id])->count();
        if($count==Card::CARD_NUM_ALL){
            //所选择的牌
            $cardSelected = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND,'type_ord'=>$type_ord])->one();
            if($cardSelected){
                $card_ord = $cardSelected->ord;
                //将牌丢进弃牌堆
                $cardSelected->type = self::TYPE_DISCARDED;
                $cardSelected->type_ord = self::getInsertDiscardOrd($room_id);
                if($cardSelected->save()){
                    if(self::moveHandCardsByLackOfCard($room_id,$type_ord)){
                        //给这个玩家摸一张牌
                        if(in_array($type_ord,self::$host_hands_type_ord)){
                            GameCard::drawCard($room_id,true);
                        }else if(in_array($type_ord,self::$guest_hands_type_ord)){
                            GameCard::drawCard($room_id,false);
                        }
                        $success = true;
                    }else{
                        $msg = '补牌失败';
                    }
                }else{
                    $msg = '弃牌失败';
                }
            }else{
                $msg = '没有找到选择的牌';
            }
        }else{
            $msg = 'game card num wrong';
        }
        return [$success,$card_ord,$msg];
    }

    public static function playCard($room_id,$type_ord){
        $success = false;
        $result = false;
        $card_ord = -1;
        $msg = '';
        //统计牌的总数 应该为50张
        $count = self::find()->where(['room_id'=>$room_id])->count();
        if($count==Card::CARD_NUM_ALL){
            if(RoomPlayer::isHostPlayer()){
                $type_ords = self::$host_hands_type_ord;
            }else{
                $type_ords = self::$guest_hands_type_ord;
            }

            if(in_array($type_ord,$type_ords)){
                //所选择的牌
                $cardSelected = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND,'type_ord'=>$type_ord])->one();
                if($cardSelected){
                    $game = Game::find()->where(['room_id'=>$room_id])->one();
                    if($game){
                        $cardsSuccessTop = self::getCardsSuccessTop($room_id);

                        $colorTopNum = $cardsSuccessTop[$cardSelected->color]; //对应花色的目前成功的最大数值
                        $num = Card::$numbers[$cardSelected->num];              //选中牌的数值
                        if($colorTopNum + 1 == $num){
                            $cardSelected->type = GameCard::TYPE_SUCCESSED;
                            $cardSelected->type_ord = 0;
                            $cardSelected->save();

                            $game->score +=1;
                            $game->save();

                            $result = true;
                        }else{
                            $cardSelected->type = self::TYPE_DISCARDED;
                            $cardSelected->type_ord = self::getInsertDiscardOrd($room_id);
                            $cardSelected->save();
                            $result = false;
                        }
                        $card_ord = $cardSelected->ord;
                        self::moveHandCardsByLackOfCard($room_id,$type_ord);
                        $success = true;
                    }else{
                        $msg='游戏未开始';
                    }
                }else{
                    $msg='没有找到选择的牌';
                }
            }else{
                $msg='选择手牌排序错误';
            }
        }else{
            $msg='game card num wrong';
        }
        return [$success,$result,$card_ord,$msg];
    }


    public static function cue($room_id,$type_ord,$type){
        $success = false;
        $cards_ord = [];
        //统计牌的总数 应该为50张
        $count = self::find()->where(['room_id'=>$room_id])->count();
        if($count==Card::CARD_NUM_ALL){
            //所选择的牌
            $cardSelected = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND,'type_ord'=>$type_ord])->one();
            if($cardSelected){
                $game = Game::find()->where(['room_id'=>$room_id])->one();
                if($game){
                    $hands_ord = $game->round_player_is_host?self::$guest_hands_type_ord:self::$host_hands_type_ord;


                    if($type=='color'){
                        $cardCueList = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND,'color'=>$cardSelected->color])->andWhere(['in','type_ord',$hands_ord])->orderby('type_ord asc')->all();

                    }elseif($type=='num'){
                        $cardCueList = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND])->andWhere(['in','num',Card::$numbers2[Card::$numbers[$cardSelected->num]]])->andWhere(['in','type_ord',$hands_ord])->orderby('type_ord asc')->all();
                    }else{
                        $msg = '提示类型不正确';
                    }

                    if(isset($cardCueList) && !empty($cardCueList)){
                        foreach($cardCueList as $c){
                            $cards_ord[] = $c->type_ord;
                        }
                        $success = true;
                    }else{
                        $msg = '提示列表为空';
                    }




                }else{
                    echo '游戏未开始';
                }
            }else{
                echo '没有找到选择的牌';
            }
        }else{
            echo 'game card num wrong';
        }
        return [$success,$cards_ord];
    }

    //交换手牌顺序
    /*public static function changePlayerCardOrd($game_id,$player,$cardId1,$cardId2){
        $card1 = self::find()->where(['game_id'=>$game_id,'type'=>self::TYPE_IN_PLAYER,'player'=>$player,'id'=>$cardId1,'status'=>1])->one();
        $card2 = self::find()->where(['game_id'=>$game_id,'type'=>self::TYPE_IN_PLAYER,'player'=>$player,'id'=>$cardId2,'status'=>1])->one();
        if($card1 && $card2){
            $card1->ord = $card2->ord;
            $card2->ord = $card1->ord;
            $card1->save();
            $card2->save();
        }else{
            echo 'card info wrong';
        }
    }


    //获取牌库/手牌 等信息
    public static function getCardInfo($game_id){
        $cardInfo = [
            'player_1'=>[],
            'player_2'=>[],
            'library'=>[],
            'table'=>[],
            'discard'=>[],
        ];
        $gameCard = self::find()->where(['game_id'=>$game_id,'status'=>1])->orderBy('ord asc')->all();
        if(count($gameCard)==50){
            foreach($gameCard as $gc){
                $temp = ['id'=>$gc->id,'color'=>$gc->color,'num'=>$gc->num];
                if($gc->type==self::TYPE_IN_PLAYER){
                    if($gc->player==1){
                        $cardInfo['player_1'][]=$temp;
                    }elseif($gc->player==2){
                        $cardInfo['player_2'][]=$temp;
                    }
                }elseif($gc->type==self::TYPE_IN_LIBRARY){
                    $cardInfo['library'][]=$temp;
                }elseif($gc->type==self::TYPE_ON_TABLE){
                    $cardInfo['table'][]=$temp;
                }elseif($gc->type==self::TYPE_IN_DISCARD){
                    $cardInfo['discard'][]=$temp;
                }
            }
        }
        return $cardInfo;
    }*/

    //获取当前应插入弃牌堆的ord数值，即当前弃牌堆最小排序的数值加1，没有则为0
    private static function getInsertDiscardOrd($room_id){
        $lastDiscardCard = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_DISCARDED])->orderBy('type_ord desc')->one();
        if($lastDiscardCard){
            $ord = $lastDiscardCard->type_ord + 1;
        }else{
            $ord = 0;
        }
        return $ord;
    }

    //整理手牌排序 （当弃牌或者打出手牌后，进行操作）
//    public static function sortCardOrdInPlayer($game_id,$player){
//        $cards = GameCard::find()->where(['game_id'=>$game_id,'type'=>GameCard::TYPE_IN_PLAYER,'player'=>$player,'status'=>1])->orderBy('ord asc')->all();
//        $i=0;
//        foreach($cards as $c){
//            $c->ord = $i;
//            $c->save();
//            $i++;
//        }
//    }

    //移动手牌 因为打出/弃掉一张牌
    private static function moveHandCardsByLackOfCard($room_id,$ord){
        //根据type_ord 判断是否is_host
        $type_ords = [];
        if(in_array($ord,self::$host_hands_type_ord)){
            $type_ords = self::$host_hands_type_ord;
        }else if(in_array($ord,self::$guest_hands_type_ord)){
            $type_ords = self::$guest_hands_type_ord;
        }


        if(!empty($type_ords)){
            //将排序靠后的手牌都往前移动
            for($i = $ord+1;$i<=max($type_ords);$i++){
                $otherCard = self::find()->where(['room_id'=>$room_id,'type'=>self::TYPE_IN_HAND,'type_ord'=>$i])->one();
                if($otherCard){
                    $otherCard->type_ord = $otherCard->type_ord - 1;
                    $otherCard->save();
                }
            }

            return true;
        }else{
            //echo '选择的手牌排序错误';
            return false;
        }
    }

    //获取成功燃放的烟花 卡牌 每种花色的最高数值
    private static function getCardsSuccessTop($room_id){
        $cardsTypeSuccess = [
            [0,0,0,0,0],
            [0,0,0,0,0],
            [0,0,0,0,0],
            [0,0,0,0,0],
            [0,0,0,0,0]
        ];
        $cards = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_SUCCESSED])->orderBy('color ,num')->all();

        foreach($cards as $c){
            $k1=$c->color;
            $k2=Card::$numbers[$c->num] - 1;
            $cardsTypeSuccess[$k1][$k2] = 1;
        }

        $verify = true;//验证卡牌 ，按数字顺序
        $cardsTop = [0,0,0,0,0]; //每种颜色的最大数值
        foreach($cardsTypeSuccess as $k1 => $row){
            $count = 0;
            $top = 0;
            foreach($row as $k2=>$r){
                if($r==1){
                    $count++;
                    $top = $k2+1;
                }
            }
            if($count==$top){
                $cardsTop[$k1] = $top;
            }else{
                $verify=false;
            }
        }

        if($verify){
            return $cardsTop;
        }else{
            return false;
        }

    }
}
