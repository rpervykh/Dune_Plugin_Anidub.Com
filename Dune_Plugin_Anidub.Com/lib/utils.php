<?php
///////////////////////////////////////////////////////////////////////////
require_once 'utils.php';
class HD
{
    public static function is_map($a)
    {
        return is_array($a) &&
            array_diff_key($a, array_keys(array_keys($a)));
    }

    ///////////////////////////////////////////////////////////////////////

    public static function has_attribute($obj, $n)
    {
        $arr = (array) $obj;
        return isset($arr[$n]);
    }
    ///////////////////////////////////////////////////////////////////////

    public static function get_map_element($map, $key)
    {
        return isset($map[$key]) ? $map[$key] : null;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function starts_with($str, $pattern)
    {
        return strpos($str, $pattern) === 0;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function format_timestamp($ts, $fmt = null)
    {
        // NOTE: for some reason, explicit timezone is required for PHP
        // on Dune (no builtin timezone info?).

        if (is_null($fmt))
            $fmt = 'Y:m:d H:i:s';

        $dt = new DateTime('@' . $ts);
        return $dt->format($fmt);
    }

    ///////////////////////////////////////////////////////////////////////

    public static function format_duration($secs)
    {
        $n = intval($secs);

        if (strlen($secs) <= 0 || $n <= 0)
            return "--:--";

        // XXX (tvigle specific) $n = $n / 1000;
        $hours = $n / 3600;
        $remainder = $n % 3600;
        $minutes = $remainder / 60;
        $seconds = $remainder % 60;

        if (intval($hours) > 0)
        {
            return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
        }
        else
        {
            return sprintf("%02d:%02d", $minutes, $seconds);
        }
    }

    ///////////////////////////////////////////////////////////////////////
    public static function encode_user_data($a, $b = null)
    {
        $media_url = null;
        $user_data = null;

        if (is_array($a) && is_null($b))
        {
            $media_url = '';
            $user_data = $a;
        }
        else
        {
            $media_url = $a;
            $user_data = $b;
        }

        if (!is_null($user_data))
            $media_url .= '||' . json_encode($user_data);

        return $media_url;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function decode_user_data($media_url_str, &$media_url, &$user_data)
    {
        $idx = strpos($media_url_str, '||');

        if ($idx === false)
        {
            $media_url = $media_url_str;
            $user_data = null;
            return;
        }

        $media_url = substr($media_url_str, 0, $idx);
        $user_data = json_decode(substr($media_url_str, $idx + 2));
    }

    ///////////////////////////////////////////////////////////////////////

    public static function http_get_document($url, $opts = null)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,    10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    true);
        curl_setopt($ch, CURLOPT_TIMEOUT,           10);
        curl_setopt($ch, CURLOPT_USERAGENT,         "Mozilla/5.0 (Windows NT 5.1; rv:7.0.1) Gecko/20100101 Firefox/7.0.1");//'DuneHD/1.0');
		curl_setopt($ch, CURLOPT_ENCODING,          1);
        curl_setopt($ch, CURLOPT_URL,               $url);

        if (isset($opts))
        {
            foreach ($opts as $k => $v)
                curl_setopt($ch, $k, $v);
        }

        hd_print("HTTP fetching '$url'...");

        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if($content === false)
        {
            $err_msg = "HTTP error: $http_code (" . curl_error($ch) . ')';
            hd_print($err_msg);
            return '';
            //throw new Exception($err_msg);
        }

        if ($http_code != 200)
        {
            $err_msg = "HTTP request failed ($http_code)";
            hd_print($err_msg);
            return '';
            //throw new Exception($err_msg);
        }

        hd_print("HTTP OK ($http_code)");

        curl_close($ch);

        return $content;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function http_post_document($url, $post_data)
    {
        return self::http_get_document($url,
            array
            (
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data
            ));
    }

    ///////////////////////////////////////////////////////////////////////

    public static function parse_xml_document($doc)
    {
        $xml = simplexml_load_string($doc);

        if ($xml === false)
        {
            hd_print("Error: can not parse XML document.");
            hd_print("XML-text: $doc.");
            throw new Exception('Illegal XML document');
        }

        return $xml;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function make_json_rpc_request($op_name, $params)
    {
        static $request_id = 0;

        $request = array
        (
            'jsonrpc' => '2.0',
            'id' => ++$request_id,
            'method' => $op_name,
            'params' => $params
        );

        return $request;
    }

    ///////////////////////////////////////////////////////////////////////////

    public static function get_mac_addr()
    {
        static $mac_addr = null;

        if (is_null($mac_addr))
        {
            $mac_addr = shell_exec(
                'ifconfig  eth0 | head -1 | sed "s/^.*HWaddr //"');

            $mac_addr = trim($mac_addr);

            hd_print("MAC Address: '$mac_addr'");
        }

        return $mac_addr;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function get_out_type_code($op_code)
    {
        static $map = null;

        if (is_null($map))
        {
            $map = array
            (
                PLUGIN_OP_GET_FOLDER_VIEW           => PLUGIN_OUT_DATA_PLUGIN_FOLDER_VIEW,
                PLUGIN_OP_GET_NEXT_FOLDER_VIEW      => PLUGIN_OUT_DATA_PLUGIN_FOLDER_VIEW,
                PLUGIN_OP_GET_REGULAR_FOLDER_ITEMS  => PLUGIN_OUT_DATA_PLUGIN_REGULAR_FOLDER_RANGE,
                PLUGIN_OP_HANDLE_USER_INPUT         => PLUGIN_OUT_DATA_GUI_ACTION,
                PLUGIN_OP_GET_TV_INFO               => PLUGIN_OUT_DATA_PLUGIN_TV_INFO,
                PLUGIN_OP_GET_DAY_EPG               => PLUGIN_OUT_DATA_PLUGIN_TV_EPG_PROGRAM_LIST,
                PLUGIN_OP_GET_TV_PLAYBACK_URL       => PLUGIN_OUT_DATA_URL,
                PLUGIN_OP_GET_TV_STREAM_URL         => PLUGIN_OUT_DATA_URL,
                PLUGIN_OP_GET_VOD_INFO              => PLUGIN_OUT_DATA_PLUGIN_VOD_INFO,
                PLUGIN_OP_GET_VOD_STREAM_URL        => PLUGIN_OUT_DATA_URL,
                PLUGIN_OP_CHANGE_TV_FAVORITES       => PLUGIN_OUT_DATA_GUI_ACTION
            );
        }

        if (!isset($map[$op_code]))
        {
            hd_print("Error: get_out_type_code(): unknown operation code: '$op_code'.");
            throw new Exception("Uknown operation code");
        }

        return $map[$op_code];
    }
}

///////////////////////////////////////////////////////////////////////////
?>
