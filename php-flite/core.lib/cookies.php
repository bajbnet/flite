<?php
require_once(FLITE_DIR . '/thirdparty/encryption/class.rc4crypt.php');

class Cookies
{
    public static function SetCookie($name,$variable,$expires=864000,$secure=false)
    {
        $_FLITE = Flite::Base();
        $cookie_name = Cookies::GetCookieName($name);

        $data = serialize($variable);
        if($secure)
        {
            $data = rc4crypt::encrypt(FC::get_user_ip() . $_FLITE->GetConfig('cookie_salt'), $data);
        }
        $data = base64_encode($data);
        $expires += $expires > time() ? 0 : time();
        return setcookie($cookie_name, urlencode($data),$expires,$_FLITE->GetConfig('cookie_path','/'),$_FLITE->GetConfig('cookie_domain','.'.$_FLITE->domain.'.'.$_FLITE->tld));
    }

    public static function ReadCookie($name,$secure=false)
    {
        $_FLITE = Flite::Base();
        $cookie_name = Cookies::GetCookieName($name);
        if(isset($_COOKIE[$cookie_name]))
        {
            $data = base64_decode(urldecode($_COOKIE[$cookie_name]));
            if($secure)
            {
                $data = rc4crypt::decrypt(FC::get_user_ip() . $_FLITE->GetConfig('cookie_salt'), $data);
            }
            $data = @unserialize($data);
 			return $data;
        }
        return false;
    }

    public static function RemoveCookie($name)
    {
        $_FLITE = Flite::Base();
        $cookie_name = Cookies::GetCookieName($name);
        return setcookie($cookie_name, '', time() - strtotime('-1 hour'),'/','.' . $_FLITE->domain . '.' . $_FLITE->tld);
    }

    public static function GetCookieName($plain_name)
    {
        $_FLITE = Flite::Base();
        if($_FLITE->GetConfig('is_dev')) return $plain_name;
        return md5(FC::get_user_ip() . '-bbcookies' . $plain_name);
    }
}