<?php

namespace app\modules\v1\controllers;

use Yii;
use app\models\Game;

class MyGameController extends MyActiveController
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


    public function actionStart(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Game::start();

        return $return;
    }

    public function actionGetInfo(){
        $return = $this->return;

        $mode = Yii::$app->request->post('mode','all');

        $force = Yii::$app->request->post('force',false);

        list($return['success'],$return['msg'],$return['data']) = Game::getInfo($mode,$force);

        return $return;
    }

    public function actionEnd(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Game::end();

        return $return;
    }

    public function actionDoDiscard(){
        $return = $this->return;

        $ord = Yii::$app->request->post('cardSelectOrd');

        list($return['success'],$return['msg']) = Game::discard($ord);

        return $return;
    }

    public function actionDoPlay(){
        $return = $this->return;

        $ord = Yii::$app->request->post('cardSelectOrd');

        list($return['success'],$return['msg']) = Game::play($ord);

        return $return;
    }

    public function actionDoCue(){
        $return = $this->return;

        $ord = Yii::$app->request->post('cardSelectOrd');
        $type = Yii::$app->request->post('cueType');

        list($return['success'],$return['msg']) = Game::cue($ord,$type);

        return $return;
    }
}
