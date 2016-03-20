<?php
///////////////////////////////////////////////////////////////////////////
require_once 'utils.php';

class Cursor
{

	    private $m_feed;
	    private $m_feed_url;
	    private $m_items_count;
        private $m_last_ref;

    ///////////////////////////////////////////////////////////////////////

    public function __construct($media_url)
    {
        if ($media_url === 'main_menu:fav')
            $this->m_feed_url = 'main_menu:fav';
        else
            HD::decode_user_data($media_url, $media_str, $this->m_feed_url);

        $this->m_items_count = 0;
        $this->m_feed = 0;
        $this->m_last_ref = "";
    }

    private function parse_page($url)
    {
        $api = HOST_API_URL;
        
        $items = array();
		$url = $url;
        $doc = HD::http_get_document($url);
		
		////////hd_print("--->>> doc: $doc");
		$doc = str_replace('  ', '', $doc);
        $videos = explode('<div class="newstitle">', $doc);
        unset($videos[0]);
        $videos = array_values($videos);

        foreach($videos as $video)
        {	
            $tmp = explode('<a href="', $video);
			if (count($tmp) < 2)
                continue;
			$season_ref = str_remove_spec(strstr($tmp[1], '">', true));
            
            $tmp = explode('data-original="', $video);
            $season_image = str_remove_spec(strstr($tmp[1], '"', true));
            if ($season_image == false)
			{
			$season_image = 'plugin_file://skins/no_cover.png';
			}
			
			$tmp = explode('<div class="title" itemprop="name">', $video);
            if (count($tmp) < 2)
                continue;
            $season_title = str_remove_spec(strstr($tmp[1], '</div>', true));
			$season_title = strip_tags($season_title);
			
            
			// //hd_print("--->>> season_ref: $season_ref");
            // //hd_print("--->>> season_title: $season_title");
            // //hd_print("--->>> season_image: $season_image"); 
            
            array_push
            (
                $items,
                HD::encode_user_data
                (
                    array
                    (
                        'video_id' => $season_ref,
                        'season_title' => $season_title,
                        'season_ref' => $season_ref,
                        'season_image' => $season_image,
                        'is_has_new' => false
                    )
                )
            );
            ++$this->m_items_count;
        }
        
        return $items;
    }
////////////////////////////////////////////////////////////////
	private function parse_page_search($url)
    {
        $api = HOST_API_URL;
        
        $items = array();

        $post_data= $url;
		$urls = $api . '/index.php?do=search';
        $doc = HD::http_post_document($urls, $post_data);
        $videos = explode('<div class="newstitle">', $doc);
        unset($videos[0]);
        $videos = array_values($videos);

        foreach($videos as $video)
        {	
            $tmp = explode('<a href="', $video);
            if (count($tmp) < 2)
                continue;
            $season_ref = str_remove_spec(strstr($tmp[1], '"', true));
            
			$tmp = explode($season_ref . '" >', $video);
            if (count($tmp) < 2)
                continue;
            $season_title = str_remove_spec(strstr($tmp[1], '</a>', true));
			$season_title = strip_tags($season_title);
			
			$tmp = explode('<div class="poster_img"><img src="', $video);
            if (count($tmp) < 2)
                continue;
            $season_image = str_remove_spec(strstr($tmp[1], '"', true));	
			
			
            
            // //hd_print("--->>> season_ref: $season_ref");
            // //hd_print("--->>> season_title: $season_title");
            // //hd_print("--->>> season_image: $season_image");
            
            array_push
            (
                $items,
                HD::encode_user_data
                (
                    array
                    (
                        'video_id' => $season_ref,
                        'season_title' => $season_title,
                        'season_ref' => $season_ref,
                        'season_image' => $season_image,
                        'is_has_new' => false
                    )
                )
            );
            ++$this->m_items_count;
        }
        
        return $items;
    }
	///////////////////////////////////////////////////////////////////
    private function parse_subcategory()
    {
        // //hd_print("--> parse subcategory: started...");
        $subcategory = $this->m_feed_url;
        $ref = $subcategory->subcategory_ref;
        $api = HOST_API_URL;
        
        $url = $api . $ref;
        
        if ($this->m_feed > 0)
            $url = $url . '/page/' . ($this->m_feed + 1) . '/';

        return $this->parse_page($url);
    }

    ///////////////////////////////////////////////////////////////////////
    private function parse_search_result($search)
    {
        // hd_print("--> parse search result: started... $search");
        $api = HOST_API_URL;
        $ref = "do=search&subaction=search&search_start=1&full_search=1&result_from=1&titleonly=3&story=$search";
        if ($this->m_feed > 0)
            return false;
        
        $url = $ref;
		// hd_print("url--->>>: $url");
        return $this->parse_page_search($url);
    }
    ///////////////////////////////////////////////////////////////////////

    private function get_next_fav(&$plugin_cookies)
    {
        ////////hd_print("--> get favorites: started... $this->m_feed");
        
        $fav_items = isset($plugin_cookies->fav_items) ? $plugin_cookies->fav_items : '';
        
        $ref = "";
        
        $n = 0;
        foreach (ListUtil::string_to_list($fav_items) as $item)
        {
            if ($item == "")
                continue;
            
            if ($n == $this->m_feed)
            {
                $ref = $item;
            }

            ++$n;
            
        }

        if ($ref == "")
            return false;
        
        $items = array();

        $c = VideoCache::get($ref);
        if (!is_null($c))
        {
            array_push
            (
                $items,
                $c
            );

            ++$this->m_items_count;

            return $items;
            
        }

        $api = HOST_API_URL;
        
        $doc = HD::http_get_document($ref);
		////////hd_print("doc: --->>>$doc");
		$tmp = strstr($doc, "<div id='dle-content'>");
        $info_block = strstr($tmp, '<div id="our1" >', true);
		$info_block = str_replace('&laquo;', '«', $info_block);
		$info_block = str_replace('&ndash;', '-', $info_block);
		$info_block = str_replace("vkontakte.ru", "vk.com", $info_block);
        // title
        $tmp = explode('<h1 class="titlfull" itemprop="name">', $info_block);
        $season_title = str_remove_spec(strstr($tmp[1], '</h1>', true));
        $season_title = strip_tags($season_title);
		
		// image
        $tmp = explode('<link rel="image_src" href="', $info_block);
        $season_image = str_remove_spec(strstr($tmp[1], '"', true));

        $series_block = '';
        $tmp = strstr($doc, '<select size="1" name="fileId" onchange="this.form. submit();">');
        
        $is_has_new = false;

        if ($tmp && isset($plugin_cookies->$ref))
        {
            $series_block = strstr($tmp, '</select>', true);
            $videos = explode('value="', $series_block);
            unset($videos[0]);
            $videos = array_values($videos);

            foreach($videos as $video)
            {
                $fileid = strstr($video, '"', true);
                $episode_ref = $url . "?fileId=$fileid";
                
                ////////hd_print("--->>> episode_ref: $episode_ref");

                if (isset($plugin_cookies->$ref))
                    if (!ListUtil::is_in_list($plugin_cookies->$ref, $episode_ref))
                        $is_has_new = true;
                            
                if ($is_has_new)
                    break;
            }
        }
        
        array_push
        (
            $items,
            HD::encode_user_data
            (
                array
                (
                    'video_id' => $ref,
                    'season_title' => $season_title,
                    'season_ref' => $ref,
                    'season_image' => $season_image,
                    'is_has_new' => $is_has_new
                )
            )
        );

        ++$this->m_items_count;

        return $items;
    }
    ///////////////////////////////////////////////////////////////////////

    public function next_chunk($plugin_cookies)
    {
	    ////////hd_print("--> next_chunk(): started...");

	    $items = array();

        if ($this->m_feed_url === 'main_menu:fav')
        {
            ////////hd_print("--> parse favorites: started...");
            
            $items = $this->get_next_fav($plugin_cookies);
            ++$this->m_feed;

            if ($items === false)
                return false;
            
            return count($items) > 0 ? $items : false;
        }
	    if (HD::has_attribute($this->m_feed_url, 'ser_feed'))
	    {
		    ////////hd_print("--> search: started...");
            
		    $search_text = $this->m_feed_url->ser_feed;
            
            $items = $this->parse_search_result($search_text);
            ++$this->m_feed;
            
		    ////////hd_print("--> search for: " . $search_text);

            if ($items === false)
                return false;
                
            ////////hd_print("--> next_chunk(): found " . count($items) . " items.");
            return count($items) > 0 ? $items : false;
	    }
        else if (HD::has_attribute($this->m_feed_url, 'subcategory_ref'))
        {
           $items = $this->parse_subcategory();
            ++$this->m_feed;

            if ($items === false)
                return false;
                
            ////////hd_print("--> next_chunk(): found " . count($items) . " items.");
            return count($items) > 0 ? $items : false;
        }

        return false;
    }
}

///////////////////////////////////////////////////////////////////////////
?>