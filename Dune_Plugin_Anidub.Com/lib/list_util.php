<?php
///////////////////////////////////////////////////////////////////////////

class ListUtil
{

    public static function string_to_list($list_string)
    { return explode('|',$list_string); }

    public static function list_to_string($list_items)
    { return implode('|',array($list_items)); }

    public static function is_in_list($list_string, $item_search)
    {
        $list = self::string_to_list($list_string);
        $res = "";
        foreach ($list as $item)
        {
            if ($item === $item_search)
            {
                $res = $item;
            }
        }
        return $res !== "";
    }
    
    public static function del_item($list_string,$del_item)
    {
        $list = self::string_to_list($list_string);
        $res = "";
        foreach ($list as $item)
        {
            if ($item !== $del_item)
            {
                if ($res === "") $res = $item;
                else $res = $res . '|' . $item;
            }
        }
        return $res;
    }

    public static function add_item($list_string,$item)
    {
        $list = self::string_to_list($list_string);
        $res = self::del_item($list_string,$item);
        if ($res === "") $res = $item;
        else $res = $item . '|' . $res;
        return $res;
    }

}

///////////////////////////////////////////////////////////////////////////
?>
