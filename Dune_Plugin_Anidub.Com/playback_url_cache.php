<?php
///////////////////////////////////////////////////////////////////////////

class PlaybackUrlCache
{
    private $map;

    private function __construct()
    {
        $map = array();
    }

    private static function instance()
    {
        static $c = null;

        if (is_null($c))
            $c = new PlaybackUrlCache();

        return $c;
    }

    public static function get($v)
    {
        $c = PlaybackUrlCache::instance();

        $video_id = '1';
        if (HD::has_attribute($v, 'video_id'))
            $video_id = $v->video_id;

        if (!isset($c->map[$video_id]))
        {
            $series  = array();
        
            $playlist_url = ExUa::retrieve_playback_url($v);
        
            // get urls
            $doc = HD::http_get_document($playlist_url);
            $series_url = explode(chr(10), $doc);

            $get_titles = false;

            // get titles for those urls
            if (HD::has_attribute($v, 'page_ref'))
            {
                $doc = HD::http_get_document($v->page_ref);
                if ($doc)
                    $get_titles = true;
            }

            $n = 0;
            foreach($series_url as $s)
            {
                if (strlen($s) === 0)
                    continue;

                $title = "Серия " . ($n + 1);
                if ($get_titles)
                {
//                    hd_print($s);
                    $tmp = explode("www.ex.ua", $s);
                    // search for '/get/xxxxx'
                    $tmp = explode($tmp[1], $doc);
                    if (count($tmp) > 1)
                    {
//                        hd_print($tmp[1]);
                        $tmp = explode("title='", $tmp[1]);
                        $tmp = explode("'", $tmp[1]);
                        $title = $tmp[0];
                        if (preg_match("/s\d\de\d\d/i", $title, $regs))
                        {
//                            foreach($regs as $r)
                            {
                            $r = $regs[0];
//                                hd_print('--->>> ' . $r);
                                $season = $r[1] === '0' ? $r[2] : $r[1] . $r[2];
                                $ep = $r[4] === '0' ? $r[5] : $r[4] . $r[5];
                                $title = "Сезон $season Эпизод $ep";
                            }
                        }
                    }
                }

//                $s = str_replace("http://", "http://ts://", $s);
                array_push ($series,
                    HD::encode_user_data
                    (
                        array
                        (
                            'url' => $s,
                            'title' => $title
                        )
                    )
                );
                ++$n;
            }

            $c->map[$video_id] = $series;
        }

        return $c->map[$video_id];
    }
}

///////////////////////////////////////////////////////////////////////////
?>