<?php

namespace app\models;

use yii\db\ActiveRecord;


class Card extends ActiveRecord
{
    #总卡牌数  5种颜色 每种颜色10张  3张"1" 2张"2"、"3"、"4"  1张"5"
    const CARD_NUM_ALL = 50;

    #颜色值对应 game_card字段color 0=>"白" 1=>"蓝" 2=>"黄" 3=>"红" 4=>"绿"
    public static $colors = ['白','蓝','黄','红','绿'];

    #数字值对应 game_card字段num 例 num=7 对应显示数字"4"  num=9 对应显示数字"5"
    public static $numbers = [1,1,1,2,2,3,3,4,4,5];

    #数字值对应num序号  例，显示为"1"的卡牌对应的 num值 可以为 0、1、2
    public static $numbers2 = [1=>[0,1,2],2=>[3,4],3=>[5,6],4=>[7,8],5=>[9]];

}