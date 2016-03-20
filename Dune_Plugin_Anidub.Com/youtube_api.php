<?php
///////////////////////////////////////////////////////////////////////////

define('YOUTUBE_API_URL', 'http://gdata.youtube.com/feeds/api');
define('YOUTUBE_DEV_KEY', 'AI39si5fQPGVdvQRcSilgV8XdGC1GqgqZ-OBWE0EzTBp_iMEQgakJ78DFfyElMiY_B-x6hIF53DaI9ZsidHbBsfXh4oilyHKOw');
define('YOUTUBE_PRODUCT', 'YouTube plugin for Dune HD');
define('YOUTUBE_PAGE_SIZE', '4');

///////////////////////////////////////////////////////////////////////////

class Youtube
{
    public static function get_watch_url($id)
    {
        return "http://www.youtube.com/watch?v=$id";
    }

    ///////////////////////////////////////////////////////////////////////

    public static function retrieve_playback_url($id, &$plugin_cookies)
    {
        // hack! but it seems to be helpfull, no more plugin restarts
        set_time_limit(0);
        $video_quality = 'medium';//isset($plugin_cookies->video_quality) ? $plugin_cookies->video_quality : 'hd1080';
        switch ($plugin_cookies->video_quality)
        {
        case 4:
            $video_quality = 'hd1080';
            break;
        case 3:
            $video_quality = 'hd720';
            break;
        }
        $mp4 = 'http-mp4';//isset($plugin_cookies->mp4) ? $plugin_cookies->mp4 : 'http-mp4';

        $doc =
                HD::http_get_document(
                        Youtube::get_watch_url($id),
                        array(
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_HEADER => true,
                            CURLOPT_HTTPHEADER => array('X-GData-Key', YOUTUBE_DEV_KEY),
                ));
        //hd_print("----- doc: $doc");
        //hd_print("--> Retrieving playback URL for $id...");

        if (preg_match("/ytplayer.config = ({.*});/", $doc, $m) !== 1)
        {
            hd_print("--> Can't find playerConfig.");
            $playback_url ='http://groo.pp.ua/1.mp4';

            if (preg_match("/\"fmt_url_map\":/", $doc, $m) === 1)
                hd_print("==> FOUND fmt_url_map !!!!!");
			$playback_url ='http://groo.pp.ua/1.mp4';
            return $playback_url;
        }

        $cfg = json_decode($m[1]);
	
        $str = $cfg->args->url_encoded_fmt_stream_map;
        $lst = explode(',', $str);

        $first_found = "";
        $last_found = "";
        $first_quality = "";
        $last_quality = "";

        foreach ($lst as $l) {
            $str = urldecode($l);
            // fix sig to signature
            $str = str_replace("sig=", "signature=", $str);
			$str=str_replace('; codecs="avc1.64001F, mp4a.40.2"','',$str);
			$str=str_replace('; codecs="avc1.42001E, mp4a.40.2"','',$str);
			//hd_print("1 url--->: $str");
            // fix %2C to ,
            $str = str_replace("%2C", ",", $str);
            // create args array
            parse_str($str, $str_args);
            // itag used to decode mp4 stream
            // FORMAT_TYPE={'18':'mp4','22':'mp4','34':'flv','35':'flv','37':'mp4','38':'mp4','43':'webm','44':'webm','45':'webm','46':'webm'};
            $itag = $str_args['itag'];
            $quality = $str_args['quality'];
            if (in_array($itag, array('18', '22', '37', '38')))
            {
                // create stream url
                $url = $str_args['url'];
                foreach ($str_args as $key => $value) {
                    if ($key !== 'url') {
                        $url .= "&{$key}={$value}";
                    }
                    //hd_print("-----> {$key} => {$value}");
                }
                
                $playback_url = $url;
				$playback_url = str_replace("itag=18&itag=18", "itag=18", $playback_url);
				$playback_url = str_replace("itag=22&itag=22", "itag=22", $playback_url);
				$playback_url = str_replace("itag=37&itag=37", "itag=37", $playback_url);
				$playback_url = str_replace("itag=38&itag=38", "itag=38", $playback_url);
				//hd_print("2 url--->: $playback_url");
				
                //hd_print("---> itag: $itag, quality: $quality, url: $playback_url");
				if (preg_match("/&s=/i",$playback_url))
				   {
						$tmp = explode('&s=', $playback_url);
						$urlpart_1 = $tmp[0];
						//hd_print("3(1)--->  url: $urlpart_1");
						$urlpart = $tmp[1];
						//hd_print("3(2)--->  url: $urlpart");
						$urlpart_2 = strstr($urlpart, '&');
						//hd_print("3(3)--->  url: $urlpart_2");
						$tmp = explode('&', $urlpart);
						$s = $tmp[0];
						hd_print("s --->:$s");
						$n_s = strlen($s);
						hd_print("n_s --->:$n_s");
						$url = 'http://dune-club.info/echo?message=' . $s;
						$signature = file_get_contents($url);
						hd_print("signature --->  url: $signature");
						$playback_url = $urlpart_1 . "&signature=" .$signature . $urlpart_2;
						//hd_print("3 decode --->  url: $playback_url");
				   }
                if ($first_found === "") {
                    $first_found = $playback_url;
                    $first_quality = $quality;
                }
                $last_found = $playback_url;
                $last_quality = $quality;

                if (($quality === $video_quality) || (($quality !== 'medium') && ($video_quality === 'hdonly'))) {
                    hd_print("return 1 --->  url: $playback_url");
                    return $playback_url;
                }
            }
        }

        if (($last_found !== "") && ($video_quality !== 'hdonly')) {
            if ($video_quality === 'hd1080') {
                 hd_print("return 2 first_found  --->  url: $first_found");
                return $first_found;
            } else {
                 hd_print("return 3 last_found  --->  url: $last_found");
                return $last_found;
            }
        } else {
            hd_print("--> video: $id; no mp4-stream.");
			$playback_url ='http://groo.pp.ua/1.mp4';
            return $playback_url;
        }
    }

}

///////////////////////////////////////////////////////////////////////////
?>