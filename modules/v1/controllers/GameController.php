<?php

namespace app\modules\v1\controllers;

use Yii;
use app\models\Game;

class GameController extends MyActiveController
{
    public function init(){
        $this->modelClass = Game::className();
        parent::init();
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        return $behaviors;
    }
}