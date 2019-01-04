<?php
namespace app\components\cache;

use Yii;
use yii\base\Component;

class RoomListCache extends Component {
    const SYS_LAST_UPDATED_KEY = 'room_list_last_updated';
    const USER_LAST_UPDATED_KEY_PREFIX = 'room_list_last_updated_';

    public static function isNoUpdate() {
        $cache = Yii::$app->cache;
        # 当前玩家对应的房间列表的最后缓存更新时间
        $userLastUpdated = $cache->get(self::USER_LAST_UPDATED_KEY_PREFIX.Yii::$app->user->id);
        # 系统的房间列表的最后缓存更新时间
        $sysLastUpdated = $cache->get(self::SYS_LAST_UPDATED_KEY);
        # 如果 当前玩家的最后缓存更新时间 < 系统的最后缓存更新时间  就要重新读取数据 并将 当前玩家缓存更新时间更新为系统时间
        return $userLastUpdated!='' && $userLastUpdated >= $sysLastUpdated;
    }

    public static function updateUserKey() {
        $cache = Yii::$app->cache;
        $now = date('Y-m-d H:i:s');
        $cache->set(self::USER_LAST_UPDATED_KEY_PREFIX.Yii::$app->user->id, $now);
    }

    public static function updateSysKey() {
        $cache = Yii::$app->cache;
        $now = date('Y-m-d H:i:s');
        $cache->set(self::SYS_LAST_UPDATED_KEY, $now);
    }
}