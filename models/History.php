<?php

namespace app\models;

use app\components\exception\HistoryException;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
/**
 * This is the model class for table "history".
 *
 * @property integer $id
 * @property integer $room_id
 * @property integer $status
 * @property string $score
 * @property string $created_at
 * @property string $updated_at
 */
class History extends ActiveRecord
{
    const STATUS_PLAYING = 1;
    const STATUS_END = 2;


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'history';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at','updated_at'],
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
            [['room_id', 'status'], 'required'],
            [['room_id', 'status','score'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'room_id' => 'Room ID',
            'status' => 'Status',
            'score' => 'Score',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public static function createOne($roomId){
        $room = Room::getOne($roomId);

        $history = new History();
        $history->room_id = $roomId;
        $history->status = History::STATUS_PLAYING;
        $history->score = 0;
        if (!$history->save()) {
            HistoryException::t('create_one_failure');
        }

        $historyPlayer = new HistoryPlayer();
        $historyPlayer->history_id = $history->id;
        $historyPlayer->user_id = $room->hostPlayer->user_id;
        $historyPlayer->is_host = 1;
        if(!$historyPlayer->save()){
            HistoryException::t('create_host_history_player_failure');
        }

        $historyPlayer = new HistoryPlayer();
        $historyPlayer->history_id = $history->id;
        $historyPlayer->user_id = $room->guestPlayer->user_id;
        $historyPlayer->is_host = 0;
        if(!$historyPlayer->save()){
            HistoryException::t('create_guest_history_player_failure');
        }

    }

    public static function deleteOne($roomId){
        $history = History::find()->where(['room_id'=>$roomId,'status'=>History::STATUS_PLAYING])->one();
        if($history){
            $history->status = History::STATUS_END;
            if (!$history->save()) {
                HistoryException::t('delete_one_failure');
            }
        }else{
            HistoryException::t('delete_one_failure_not_found');
        }
    }
}
