<?php
///////////////////////////////////////////////////////////////////////////

class VideoCache
{
    private $cache;

    private function __construct()
    {
        $this->cache = array();
    }

    private static function instance()
    {
        static $vc = null;

        if (is_null($vc))
            $vc = new VideoCache();

        return $vc;
    }

    public static function get($video_id)
    {
        return HD::get_map_element(VideoCache::instance()->cache, $video_id);
    }

    public static function put($v)
    {
        HD::decode_user_data($v, $media_str, $user_data);
	    if (HD::has_attribute($user_data, 'video_id'))
	    {
	        VideoCache::instance()->cache[$user_data->video_id] = $v;
	    }
    }
}

///////////////////////////////////////////////////////////////////////////
?>