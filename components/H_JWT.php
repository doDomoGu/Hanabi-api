<?php
namespace app\components;

use Yii;
use yii\base\Component;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;

//use yii\helpers\ArrayHelper;

class H_JWT extends Component {


    /*public static function parse($token){

        $token = (new Parser())->parse((string)$token); // Parses from a string

        $claims =  $token->getClaims();

        //return $token->verify()
        var_dump($claims);//exit;
        //echo $token->getClaim('user_id'); // will print "1"
    }*/


    public static function generateToken($user_id){
        $signer = new Sha256();

        $expired = time() + 3600;

        $token = (new Builder())
            ->set('user_id', $user_id) // Configures a new claim, called "uid"
            ->setExpiration($expired)
            ->sign($signer, Yii::$app->params['jwt_sign']) // creates a signature using "testing" as key
            ->getToken()// Retrieves the generated token
            ->__toString();

        return $token;
    }

    /*public static function generatePassword($password){
        $signer = new Sha256();


        $token = (new Builder())
            ->set('password', $password) // Configures a new claim, called "uid"
            ->sign($signer, Yii::$app->params['jwt_sign']) // creates a signature using "testing" as key
            ->getToken();// Retrieves the generated token
            //->__toString();

        return $token;
    }*/


}