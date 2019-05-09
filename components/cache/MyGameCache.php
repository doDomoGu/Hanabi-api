<?php
namespace app\components\cache;

use Yii;
use yii\base\Component;

class MyGameCache extends Component {
    const GAME_INFO_NO_UPDATED_FLAG_KEY_PREFIX = 'game_info_no_update_flag_';

    public static function isNoUpdate($userId) {
        $cache = Yii::$app->cache;
        $re = $cache->get(self::GAME_INFO_NO_UPDATED_FLAG_KEY_PREFIX.$userId);
        return !!$re;
    }

    public static function set($userId){
        $cache = Yii::$app->cache;
        $cache->set(self::GAME_INFO_NO_UPDATED_FLAG_KEY_PREFIX.$userId, true);
    }

    public static function clear($userId){
        $cache = Yii::$app->cache;
        $cache->delete(self::GAME_INFO_NO_UPDATED_FLAG_KEY_PREFIX.$userId);
        //TODO 房间内的另一个玩家
        //return $cache->delete(self::ROOM_INFO_NO_UPDATED_KEY_PREFIX.Yii::$app->user->id);
    }

}