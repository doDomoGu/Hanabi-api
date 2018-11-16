<?php
namespace app\components;

use yii\filters\auth\AuthMethod;


/*
 * 1、重写了authenticate方法，原本是用get方式接受token现在使用post方式
 *
 * 2、tokenParam 默认为 accessToken
 *
 */

class MyQueryParamAuth extends AuthMethod
{
    public $tokenParam = 'accessToken';

    public function authenticate($user, $request, $response)
    {
        $accessToken = $request->getHeaders()->get($this->tokenParam);
        if (is_string($accessToken)) {
            $identity = $user->loginByAccessToken($accessToken, get_class($this));
            if ($identity !== null) {
                return $identity;
            }
        }
        if ($accessToken !== null) {
            $this->handleFailure($response);
        }

        return null;
    }


    /*public function authenticate($user, $request, $response)
    {
        $accessToken = $request->post($this->tokenParam);
        if (is_string($accessToken)) {
            $identity = $user->loginByAccessToken($accessToken, get_class($this));
            if ($identity !== null) {
                return $identity;
            }
        }
        if ($accessToken !== null) {
            $this->handleFailure($response);
        }

        return null;
    }*/
}
