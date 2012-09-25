<?php
/**
 * User: brooke.bryan
 * Date: 25/09/12
 * Time: 11:48
 * Description: Basic Data Filtering
 */

class Flite_Filter
{

    public static function Email($email)
    {
        return strtolower(trim($email));
    }

    public static function Trim($string, $charlist = null)
    {
        return trim($string, $charlist);
    }

    public static function LeftTrim($string, $charlist = null)
    {
        return ltrim($string, $charlist);
    }

    public static function RightTrim($string, $charlist = null)
    {
        return rtrim($string, $charlist);
    }

    public static function Lower($string)
    {
        return strtolower($string);
    }

    public static function Upper($string)
    {
        return strtoupper($string);
    }

    public static function UpperWords($string)
    {
        return ucwords(strtolower($string));
    }

    public static function Clean($string)
    {
        return strip_tags(trim($string));
    }

    public static function Boolean($string)
    {
        return in_array($string, array('true', '1', 1, true), true);
    }

    public static function Int($string)
    {
        return intval($string);
    }

    public static function Float($string)
    {
        return floatval($string);
    }

    public static function Arr($string)
    {
        if(is_array($string)) return $string;
        if(is_object($string)) return FC::object_to_array($string);
        if(stristr($string, ',')) return explode(',', $string);
        else return array($string);
    }

    public static function Name($full_name)
    {
        $_name             = new stdClass();
        $_name->name       = $full_name;
        $parts             = explode(' ', $_name->name, 3);
        $_name->first_name = $_name->middle_name = $_name->last_name = null;
        switch(count($parts))
        {
            case 1:
                $_name->first = $parts[0];
                break;
            case 2:
                $_name->first = $parts[0];
                $_name->last  = $parts[1];
                break;
            case 3:
                $_name->first  = $parts[0];
                $_name->middle = $parts[1];
                $_name->last   = $parts[2];
                break;
        }

        return $_name;
    }
}
