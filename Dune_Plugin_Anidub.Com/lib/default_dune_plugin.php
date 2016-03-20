<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/user_input_handler_registry.php';

class DefaultDunePlugin implements DunePlugin
{
    public function get_folder_view($media_url, &$plugin_cookies)
    { return array ( ); }

    public function get_next_folder_view($media_url, &$plugin_cookies)
    { return array ( ); }

    public function get_tv_info($media_url, &$plugin_cookies)
    { return array ( ); }

    public function get_tv_stream_url($media_url, &$plugin_cookies)
    { return ''; }

    public function get_vod_info($media_url, &$plugin_cookies)
    { return array ( ); }

    public function get_vod_stream_url($media_url, &$plugin_cookies)
    { return ''; }

    public function get_regular_folder_items($media_url, $from_ndx, &$plugin_cookies)
    { return array ( ); }

    public function get_day_epg($channel_id, $day_start_ts, &$plugin_cookies)
    { return array ( ); }

    public function get_tv_playback_url($channel_id, $archive_ts, $protect_code, &$plugin_cookies)
    { return ''; }

    public function change_tv_favorites($op_type, $channel_id, &$plugin_cookies)
    { }

    public function handle_user_input(&$user_input, &$plugin_cookies)
    {  return array ( ); }

    protected function add_screen($scr)
    {
        if (isset($this->screens[$scr->get_id()]))
        {
            throw new Exception('Screen already registered');
        }

        $this->screens[$scr->get_id()] = $scr;
    }

}

///////////////////////////////////////////////////////////////////////////
?>
