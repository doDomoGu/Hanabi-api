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

        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(100)->notNull()->unique(),
            'password' => $this->string(100)->notNull(),
            'nickname' => $this->string(100)->notNull(),
            'mobile' => $this->string(11)->notNull()->unique(),
            'email' => $this->string(100)->null(),
            'avatar' => $this->string(200)->null(),
            'gender' => $this->smallInteger(1)->notNull()->defaultValue(0),
            'birthday' => $this->date()->null(),
            'status' => $this->smallInteger(1)->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $tableOptions.' AUTO_INCREMENT=10001');

        $user = new User();
        $user->username = 'admin';
        $user->password = md5('123123');
        $user->nickname = 'Admin';
        $user->mobile = '18017865582';
        $user->save();

        $user = new User();
        $user->username = 'admin2';
        $user->password = md5('123123');
        $user->nickname = 'Admin2';
        $user->mobile = '18017865583';
        $user->save();

        $this->createTable('{{%user_auth}}', [
            'id' => $this->primaryKey(),
            'user_id'=> $this->integer(11)->notNull(),
            'token' => $this->string(200)->notNull(),
            'expired_time' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->createTable('{{%room}}', [
            'id' => $this->integer(11)->notNull(),
            'title' => $this->string(100)->notNull(),
            'password' => $this->string(100),
            'updated_at' => $this->dateTime()->notNull(),
        ], $tableOptions);
        $this->addPrimaryKey('pk','{{%room}}','id');

        for($i=1;$i<=10;$i++){
            $room = new Room();
            $room->id = $i;
            $room->title = chr(mt_rand(97, 122)).chr(mt_rand(97, 122)).chr(mt_rand(97, 122));
            $room->password = mt_rand(0,1)?'123123':'';
            $room->save();
        }

        $this->createTable('{{%room_player}}', [
            'user_id' => $this->integer(11)->notNull()->unsigned(),
            'room_id' => $this->integer(11)->notNull()->unsigned(),
            'is_host' => $this->smallInteger(1)->notNull()->unsigned(),
            'is_ready' => $this->smallInteger(1)->notNull()->unsigned(),
            'updated_at' => $this->dateTime()->notNull()
        ], $tableOptions);
        $this->addPrimaryKey('pk','{{%room_player}}','user_id');
        $this->createIndex('user_room','{{%room_player}}',['user_id','room_id'],true);


        $this->createTable('{{%game}}', [
            'room_id'               => $this->integer(11)->notNull()->unsigned(),
            'round_num'             => $this->smallInteger(1)->notNull()->unsigned(),
            'round_player_is_host'  => $this->smallInteger(1)->notNull()->unsigned(),
            'cue_num'               => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(0),
            'chance_num'            => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(0),
            'status'                => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(1),
            'score'                 => $this->string(5)->null(),
            'updated_at'            => $this->dateTime()->notNull()
        ], $tableOptions);
        $this->addPrimaryKey('pk','{{%game}}','room_id');

        $this->createTable('{{%game_card}}', [
            'room_id'       => $this->integer(11)->notNull()->unsigned(),
            'type'          => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(0),
            'type_ord'      => $this->smallInteger(2)->notNull()->unsigned()->defaultValue(0),
            'color'         => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(0),
            'num'           => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(0),
            'ord'           => $this->smallInteger(2)->notNull()->unsigned()->defaultValue(0),
            'updated_at'    => $this->dateTime()->notNull()
        ], $tableOptions);
        $this->addPrimaryKey('pk','{{%game_card}}',['room_id','ord']);

        $this->createTable('{{%history}}', [
            'id'            => $this->primaryKey(),
            'room_id'       => $this->integer(11)->notNull()->unsigned(),
            'status'        => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(0),
            'score'         => $this->string(5)->notNull(),
            'created_at'    => $this->dateTime()->notNull(),
            'updated_at'    => $this->dateTime()->notNull()
        ], $tableOptions);

        $this->createTable('{{%history_log}}', [
            'id'            => $this->primaryKey(),
            'history_id'    => $this->integer(11)->notNull()->unsigned(),
            'type'          => $this->smallInteger(1)->notNull()->unsigned()->defaultValue(0),
            'content_param' => $this->text()->notNull(),
            'content'       => $this->text()->notNull(),
            'created_at'    => $this->dateTime()->notNull()
        ], $tableOptions);


        $this->createTable('{{%history_player}}', [
            'history_id'    => $this->integer(11)->notNull()->unsigned(),
            'user_id'       => $this->integer(11)->notNull()->unsigned(),
            'is_host'       => $this->smallInteger(1)->notNull()->unsigned(),
        ], $tableOptions);
        $this->addPrimaryKey('pk','{{%history_player}}','history_id,user_id');

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
