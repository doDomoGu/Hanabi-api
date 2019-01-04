<?php

namespace app\models;

class MyGame {

    /*
     * 2
     */
    public static function getInfo() {
        list($isPlaying, $roomId) = Game::isPlaying();

        Game::getInfo($roomId);

        return 1;
    }

}