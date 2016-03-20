<?php
///////////////////////////////////////////////////////////////////////////

class FolderCache
{
    private $cache;

    ///////////////////////////////////////////////////////////////////////

    private function __construct()
    {
        $this->cache = array();
    }

    ///////////////////////////////////////////////////////////////////////

    private static function instance()
    {
        static $fc = null;

        if (is_null($fc))
            $fc = new FolderCache();

        return $fc;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function get($media_url)
    {
        $fc = FolderCache::instance();

        $f = HD::get_map_element($fc->cache, $media_url);

        if (is_null($f) || $f->is_expired())
        {
            $f = new Folder($media_url);
            $fc->cache[$media_url] = $f;
        }

        return $f;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function clear()
    {
        $fc = FolderCache::instance();
        $fc->cache = array();
    }
}

///////////////////////////////////////////////////////////////////////////
?>