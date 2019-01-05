<?php
namespace app\components\cache;

use Yii;
use yii\base\Component;

class MyRoomCache extends Component {
    const ROOM_INFO_NO_UPDATED_FLAG_KEY_PREFIX = 'room_info_no_update_flag_';

    public static function isNoUpdate() {
        $cache = Yii::$app->cache;
        return $cache->get(self::ROOM_INFO_NO_UPDATED_KEY_PREFIX.Yii::$app->user->id);
    }

    public static function set(){
        $cache = Yii::$app->cache;
        return $cache->set(self::ROOM_INFO_NO_UPDATED_KEY_PREFIX.Yii::$app->user->id, true);
    }

    public static function clear(){
        $cache = Yii::$app->cache;
        return $cache->delete(self::ROOM_INFO_NO_UPDATED_KEY_PREFIX.Yii::$app->user->id);
        //TODO 房间内的另一个玩家
        //return $cache->delete(self::ROOM_INFO_NO_UPDATED_KEY_PREFIX.Yii::$app->user->id);
    }

}