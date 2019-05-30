<?php

use yii\db\Migration;
use app\models\User;
use app\models\Room;

class m171219_143428_init extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=MyISAM';
        }

        //用户表
        $userTable = '{{%user}}';

        $this->createTable($userTable, [
            'id'         => $this->primaryKey(11)->unsigned(),
            'username'   => $this->string(100)->notNull()->unique()->comment('账号'),
            'password'   => $this->string(100)->notNull()->comment('密码'),
            'nickname'   => $this->string(100)->notNull()->comment('显示用昵称'),
            'mobile'     => $this->string(20)->notNull()->unique(),
            'email'      => $this->string(100)->null(),
            'avatar'     => $this->string(200)->null(),
            'gender'     => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0)->comment('性别，0:无;1:男,2:女'),
            'birthday'   => $this->date()->null(),
            'status'     => $this->boolean()->notNull()->unsigned()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $tableOptions.' AUTO_INCREMENT=100001');

        //初始化玩家1
        $user = new User();
        $user->username = 'player1';
        $user->password = md5('123123');
        $user->nickname = '玩家1';
        $user->mobile = '18017865582';
        $user->save();

        //初始化玩家2
        $user = new User();
        $user->username = 'player2';
        $user->password = md5('123123');
        $user->nickname = '玩家2';
        $user->mobile = '18017865583';
        $user->save();

        //用户认证表
        $userAuthTable = '{{%user_auth}}';
        $this->createTable($userAuthTable, [
            'user_id'       => $this->integer(11)->notNull()->unsigned(),
            'token'         => $this->string(200)->notNull(),
            'expired_time'  => $this->dateTime()->notNull(),
            'created_at'    => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->addPrimaryKey('pk', $userAuthTable, 'token');

        //房间表
        $roomTable = '{{%room}}';
        $this->createTable($roomTable, [
            'id'         => $this->integer(11)->notNull()->unsigned(),
            'title'      => $this->string(100)->notNull(),
            'password'   => $this->string(100),
            'updated_at' => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->addPrimaryKey('pk', $roomTable, 'id');

        //初始化十间房间
        for($i=1; $i<=10; $i++){
            $room = new Room();
            $room->id = $i;
            $room->title = chr(mt_rand(97, 122)).chr(mt_rand(97, 122)).chr(mt_rand(97, 122));
            $room->password = mt_rand(0,1)?'123123':'';
            $room->save();
        }

        //房间玩家表
        $roomPlayerTable = '{{%room_player}}';
        $this->createTable($roomPlayerTable, [
            'user_id'    => $this->integer(11)->notNull()->unsigned(),
            'room_id'    => $this->integer(11)->notNull()->unsigned(),
            'is_host'    => $this->boolean()->notNull()->unsigned()->defaultValue(0),
            'is_ready'   => $this->boolean()->notNull()->unsigned()->defaultValue(0),
            'updated_at' => $this->dateTime()->notNull()
        ], $tableOptions);
        $this->addPrimaryKey('pk',$roomPlayerTable,'user_id');
        $this->createIndex('user_room', $roomPlayerTable, ['user_id', 'room_id'], true);

        //游戏表
        $gameTable = '{{%game}}';
        $this->createTable($gameTable, [
            'room_id'               => $this->integer(11)->notNull()->unsigned(),
            'round_num'             => $this->tinyInteger(2)->notNull()->unsigned()->defaultValue(0),
            'round_player_is_host'  => $this->boolean()->notNull()->unsigned()->defaultValue(0),
            'cue_num'               => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'chance_num'            => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'status'                => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(1),
            'score'                 => $this->string(5)->null(),
            'updated_at'            => $this->dateTime()->notNull()
        ], $tableOptions);
        $this->addPrimaryKey('pk', $gameTable, 'room_id');

        //游戏卡牌表
        $gameCardTable = '{{%game_card}}';
        $this->createTable($gameCardTable, [
            'room_id'       => $this->integer(11)->notNull()->unsigned(),
            'type'          => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'type_ord'      => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'color'         => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'num'           => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'ord'           => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'updated_at'    => $this->dateTime()->notNull()
        ], $tableOptions);
        $this->addPrimaryKey('pk', $gameCardTable, ['room_id', 'ord']);

        //游戏历史表
        $historyTable = '{{%history}}';
        $this->createTable($historyTable, [
            'id'            => $this->primaryKey(11)->unsigned(),
            'room_id'       => $this->integer(11)->notNull()->unsigned(),
            'status'        => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'score'         => $this->string(5)->notNull(),
            'created_at'    => $this->dateTime()->notNull(),
            'updated_at'    => $this->dateTime()->notNull()
        ], $tableOptions);

        //游戏记录日志表
        $historyLogTable = '{{%history_log}}';
        $this->createTable($historyLogTable, [
            'id'            => $this->primaryKey(11),
            'history_id'    => $this->integer(11)->notNull()->unsigned(),
            'type'          => $this->tinyInteger(1)->notNull()->unsigned()->defaultValue(0),
            'content_param' => $this->text(),
            'content'       => $this->text()->notNull(),
            'created_at'    => $this->dateTime()->notNull()
        ], $tableOptions);

        //历史记录
        $historyPlayerTable = '{{%history_player}}';
        $this->createTable($historyPlayerTable, [
            'history_id'    => $this->integer(11)->notNull()->unsigned(),
            'user_id'       => $this->integer(11)->notNull()->unsigned(),
            'is_host'       => $this->boolean()->notNull()->unsigned()->defaultValue(0)
        ], $tableOptions);
        $this->addPrimaryKey('pk', $historyPlayerTable, ['history_id', 'user_id']);

    }

    public function down()
    {
        $this->dropTable('{{%history_player}}');
        $this->dropTable('{{%history_log}}');
        $this->dropTable('{{%history}}');
        $this->dropTable('{{%game_card}}');
        $this->dropTable('{{%game}}');
        $this->dropTable('{{%room_player}}');
        $this->dropTable('{{%room}}');
        $this->dropTable('{{%user_auth}}');
        $this->dropTable('{{%user}}');
    }
}
