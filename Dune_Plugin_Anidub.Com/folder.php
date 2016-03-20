<?php
///////////////////////////////////////////////////////////////////////////

class Folder
{
    private $media_url;

    private $cursor;
    private $elements;
    private $expired;

    private $last_accessed_time;

    ///////////////////////////////////////////////////////////////////////

    public function __construct($media_url)
    {
        $this->media_url = $media_url;

        $this->cursor = new Cursor($media_url);
        $this->elements = array();

        $this->expired = false;
        $this->last_accessed_time = time();
    }

    ///////////////////////////////////////////////////////////////////////
    
    public function get_num_elements()
    {
        return count($this->elements);
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_elements($from_ndx, $plugin_cookies)
    {
        $this->last_accessed_time = time();

        if ($from_ndx < count($this->elements))
            return array_slice($this->elements, $from_ndx);

        $chunk = $this->cursor->next_chunk($plugin_cookies);

        if ($chunk === false)
        {
            hd_print("--> Folder::get_elements(): no more available.");
            return false; // no more items;
        }

        foreach ($chunk as $v)
        {
	        HD::decode_user_data($v, $media_str, $user_data);
		    if (HD::has_attribute($user_data, 'video_id'))
		    {
		            $c = VideoCache::get($user_data->video_id);
		            if (is_null($c))
            		    {
                		    VideoCache::put($v);
                		    $c = $v;
            		    }
      
            		    array_push($this->elements, $c);
		    }
        }

        if ($from_ndx < count($this->elements))
            return array_slice($this->elements, $from_ndx);

        return array();
    }

    ///////////////////////////////////////////////////////////////////////
    public function set_expired()
    {
        $this->expired = true;
    }
    
    public function is_expired()
    {
        if ($this->expired === true)
            return true;
        // Cache expires in 1 hour.
        return time() - $this->last_accessed_time > 1 * 60 * 60;
    }
}

///////////////////////////////////////////////////////////////////////////
?>