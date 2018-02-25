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
    /**
     * @apiDefine ParamAuthToken
     *
     * @apiParam {string} authToken 身份认证的token
     */

    /**
     * @apiDefine GroupMyGame
     *
     * 玩家对应的游戏
     */

    /**
     * @api {post} /my-game/get-info 获取游戏信息
     * @apiName GetInfo
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {string=all,simple} mode=all 获取模式
     * @apiParam {boolean=false,true} force=false 是否强制刷新
     *
     */

    public function actionGetInfo(){
        $return = $this->return;

        $mode = Yii::$app->request->post('mode','all');

        $force = Yii::$app->request->post('force',false);

        list($return['success'],$return['msg'],$return['data']) = Game::getInfo($mode,$force);

        return $return;
    }

    /**
     * @api {post} /my-game/end 结束游戏
     * @apiName End
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     */

    public function actionEnd(){
        $return = $this->return;

        list($return['success'],$return['msg']) = Game::end();

        return $return;
    }


    /**
     * @api {post} /my-game/do-discard 弃牌操作
     * @apiName DoDiscard
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选手牌的排序
     */
    public function actionDoDiscard(){
        $return = $this->return;

        $ord = Yii::$app->request->post('cardSelectOrd');

        list($return['success'],$return['msg']) = Game::discard($ord);

        return $return;
    }


    /**
     * @api {post} /my-game/do-play 打牌操作
     * @apiName DoPlay
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选手牌的排序
     */
    public function actionDoPlay(){
        $return = $this->return;

        $ord = Yii::$app->request->post('cardSelectOrd');

        list($return['success'],$return['msg']) = Game::play($ord);

        return $return;
    }

    /**
     * @api {post} /my-game/do-cue 提示操作
     * @apiName DoCue
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选对手手牌的排序
     * @apiParam {string="num","color"} cueType 提示类型
     */
    public function actionDoCue(){
        $return = $this->return;

        $ord = Yii::$app->request->post('cardSelectOrd');
        $type = Yii::$app->request->post('cueType');

        list($return['success'],$return['msg']) = Game::cue($ord,$type);

        return $return;
    }

    public function actionAutoPlay(){
        echo 11111;exit;

        list($return['success'],$return['msg']) = Game::play($ord);

        return $return;
    }
}
