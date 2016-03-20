<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/default_dune_plugin_fw.php';
require_once 'lib/default_dune_plugin.php';
require_once 'lib/utils.php';
require_once 'lib/control_factory.php';
require_once 'lib/action_factory.php';
require_once 'lib/list_util.php';

require_once 'utils.php';

require 'cursor.php';
require 'folder.php';
require 'folder_cache.php';
require 'playback_url_cache.php';
require 'video_cache.php';
require 'youtube_api.php';

///////////////////////////////////////////////////////////////////////////

$hd_interface_language = 'english';

///////////////////////////////////////////////////////////////////////////

function update_interface_language()
{
    global $hd_interface_language;

    $v =
        trim(
            shell_exec(
                "grep 'interface_language' /config/settings.properties" . ' | sed "s/^.*= *//"'));

    $lang_saved = $hd_interface_language;
    $hd_interface_language = (isset($v) && strlen($v) > 0) ? $v : 'english';

    return ($lang_saved !== $hd_interface_language) ? 1 : 0;
}

///////////////////////////////////////////////////////////////////////////

function get_interface_language()
{
    global $hd_interface_language;
    return $hd_interface_language;
}

function is_interface_language_russian_or_similar()
{
    return get_interface_language() === 'russian' ||
        get_interface_language() === 'ukrainian';
}

///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

class anidubPlugin extends DefaultDunePlugin implements UserInputHandler
{
    const ID = 'anidub';
    ///////////////////////////////////////////////////////////////////////
    
    private $fav_build_in_progess;

    private static $open_folder_action = array
    (
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
    );

    ///////////////////////////////////////////////////////////////////////

    private static $vod_play_action = array
    (
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::caption           => 'Play'
    );

    ///////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////

    private static $catalog_vip = array
    (
        ViewItemParams::icon_path => 'gui_skin://small_icons/folder.aai',
        ViewItemParams::item_layout => HALIGN_LEFT,
        ViewItemParams::icon_valign => VALIGN_CENTER,
        ViewItemParams::icon_dx => 20,
        ViewItemParams::icon_dy => -5,
        ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
        ViewItemParams::item_caption_width => 1550
    );

    public static function get_skin_path($plugin_cookies)
    {
        $skin = isset($plugin_cookies->skin) ? $plugin_cookies->skin : 'default';
        
        $skins = array
        (
            'default' =>  'plugin_file://skins/default/',
         //   'juniperus' => 'plugin_file://skins/juniperus/'
        );
        
        return $skins[$skin];
    }

    public static function get_main_vip($plugin_cookies)
    {
        $skin = isset($plugin_cookies->skin) ? $plugin_cookies->skin : 'default';
        
        $skins = array
        (
            'default' =>  array
                            (
                                ViewItemParams::icon_scale_factor =>1,
                                ViewItemParams::icon_sel_scale_factor =>1,
                                ViewItemParams::icon_path => 'gui_skin://small_icons/folder.aai',
                                ViewItemParams::item_layout => VALIGN_CENTER,
                                ViewItemParams::icon_valign => VALIGN_CENTER,
                                ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
								ViewItemParams::icon_dy => 200,                            //igo
								ViewItemParams::icon_sel_dy => 200,                         //igo
                            ),
           
        );
        
        return $skins[$skin];
    }

    public static function get_main_menu_folder_view($plugin_cookies)
    {
        $skin = isset($plugin_cookies->skin) ? $plugin_cookies->skin : 'default';
        
        $skins = array
        (
            
            'default' =>    array
                            (
                                ViewParams::num_cols => 6,
                                ViewParams::num_rows => 1,
								ViewParams::background_path=> 'plugin_file://skins/default/icons/bg.jpg',
                                ViewParams::icon_selection_box_dx => -100,
                                ViewParams::icon_selection_box_dy => -100,
                                ViewParams::icon_selection_box_width => 300,
                                ViewParams::icon_selection_box_height => 300,
                                ViewParams::paint_sandwich => false,
								ViewParams::paint_icon_selection_box => false,
                                ViewParams::sandwich_base => 'gui_skin://special_icons/sandwich_base.aai',
                                ViewParams::sandwich_mask => 'cut_icon://{name=sandwich_mask}',
                                ViewParams::sandwich_cover => 'cut_icon://{name=sandwich_cover}',
                                ViewParams::sandwich_width => 257,
                                ViewParams::sandwich_height => 300,
                                ViewParams::sandwich_icon_upscale_enabled => false,
                                ViewParams::paint_details => false
                            ),
        );
        
        return $skins[$skin];
    }

    public function __construct()
    {
        $this->fav_build_in_progess = false;
    }

    private static function get_preview_url($v)
    {
        if (!HD::has_attribute($v, 'season_image'))
            return '';

        return $v->season_image;
    }

    private function get_quality_array($cfg)
    {
        // 240, 360, 480, 720 Рё 1080
        $vquality = array();
        if ($cfg === true)
            $vquality[0] = '240 (РњРёРЅРёРјР°Р»СЊРЅРѕ РІРѕР·РјРѕР¶РЅРѕРµ)';
        else
            $vquality[0] = '240';
        $vquality[1] = '360';
        $vquality[2] = '480';
        $vquality[3] = '720';
        if ($cfg === true)
            $vquality[4] = '1080 (РњР°РєСЃРёРјР°Р»СЊРЅРѕ РІРѕР·РјРѕР¶РЅРѕРµ)';
        else
            $vquality[4] = '1080';

        return $vquality;        
    }
    ///////////////////////////////////////////////////////////////////////
    public function get_play_action($media_url, &$plugin_cookies)
    {
        $add_play_action = UserInputHandlerRegistry::create_action(
                        $this, 'play_item');
        $add_play_action['caption'] = (
                is_interface_language_russian_or_similar() ? 'Р’РѕСЃРїСЂРѕРёР·РІРµСЃС‚Рё' : "Play"
                );

        return $add_play_action;
    }

    ///////////////////////////////////////////////////////////////////////
    public function get_item_action_map($media_url, &$plugin_cookies)
    {
        $add_fav_action = UserInputHandlerRegistry::create_action(
                        $this, 'add_favorite');
        $add_fav_action['caption'] = (
                is_interface_language_russian_or_similar() ? 'Р”РѕР±Р°РІРёС‚СЊ РІ Р�Р·Р±СЂР°РЅРЅРѕРµ' : "Add Favorite"
                );

        $switch_view = UserInputHandlerRegistry::create_action(
                        $this, 'switch_view');
        $switch_view['caption'] = (
                is_interface_language_russian_or_similar() ? "Р’РёРґ" : 'View mode'
                );
        $view_info = UserInputHandlerRegistry::create_action(
                        $this, 'ex_view_info');
        $view_info['caption'] = (
                is_interface_language_russian_or_similar() ? "Р�РЅС„Рѕ" : 'Info'
                );
		$search_view = UserInputHandlerRegistry::create_action(
                        $this, 'do_search_menu');
        $search_view['caption'] = (is_interface_language_russian_or_similar() ? "РџРѕРёСЃРє" : 'Search');
		
        $skip_details = isset($plugin_cookies->skip_details) ? $plugin_cookies->skip_details : 'no';

        if ($skip_details === 'yes')
        {
            return array
                (
                GUI_EVENT_KEY_ENTER => UserInputHandlerRegistry::create_action($this, 'view_series'),
                GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                GUI_EVENT_KEY_A_RED => $switch_view,
                GUI_EVENT_KEY_INFO => $view_info,
				GUI_EVENT_KEY_B_GREEN => $search_view,
				GUI_EVENT_KEY_D_BLUE => $view_info,
                GUI_EVENT_KEY_C_YELLOW => $add_fav_action
            );
            
        }
        else
        {
            return array
                (
                GUI_EVENT_KEY_ENTER => $view_info,
                GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                GUI_EVENT_KEY_A_RED => $switch_view,
				GUI_EVENT_KEY_B_GREEN => $search_view,
                GUI_EVENT_KEY_C_YELLOW => $add_fav_action
            );
            
        }
    }

    ///////////////////////////////////////////////////////////////////////
    public function get_search_action_map($media_url, &$plugin_cookies)
    {
        $perform_search_action = UserInputHandlerRegistry::create_action(
                        $this, 'perform_search');
        $perform_search_action['caption'] = (
                is_interface_language_russian_or_similar() ? 'Р’С‹РїРѕР»РЅРёС‚СЊ РїРѕРёСЃРє' : "Perform Search"
                );
        $add_search_action = UserInputHandlerRegistry::create_action(
                        $this, 'add_search');
        $add_search_action['caption'] = (
                is_interface_language_russian_or_similar() ? 'Р”РѕР±Р°РІРёС‚СЊ РїРѕРёСЃРє' : "Add Search"
                );
        $remove_search_action = UserInputHandlerRegistry::create_action(
                        $this, 'remove_search');
        $remove_search_action['caption'] = (
                is_interface_language_russian_or_similar() ? "РЈРґР°Р»РёС‚СЊ" : 'Remove'
                );
        $move_search_top_action = UserInputHandlerRegistry::create_action(
                        $this, 'move_search_top');
        $move_search_top_action['caption'] = (
                is_interface_language_russian_or_similar() ? "РџРµСЂРµРјРµСЃС‚РёС‚СЊ РІРІРµСЂС…" : 'Move to top'
                );
        $menu_items[] = array(
            GuiMenuItemDef::caption => $move_search_top_action['caption'],
           GuiMenuItemDef::action => $move_search_top_action
        );
        $menu_items[] = array(
            GuiMenuItemDef::caption => $remove_search_action['caption'],
            GuiMenuItemDef::action => $remove_search_action
        );
        $popup_search_action = ActionFactory::show_popup_menu($menu_items);
        $do_search_action = UserInputHandlerRegistry::create_action($this, 'do_search');
        return array
            (
            GUI_EVENT_KEY_ENTER => $do_search_action,
            GUI_EVENT_KEY_POPUP_MENU => $popup_search_action
        );
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_fav_action_map($media_url, &$plugin_cookies)
    {
        $remove_fav_action = UserInputHandlerRegistry::create_action(
                        $this, 'remove_fav');
        $remove_fav_action['caption'] = (
                is_interface_language_russian_or_similar() ? "РЈРґР°Р»РёС‚СЊ" : 'Remove'
                );
        $move_fav_top_action = UserInputHandlerRegistry::create_action(
                        $this, 'move_fav_top');
        $move_fav_top_action['caption'] = (
                is_interface_language_russian_or_similar() ? "РџРµСЂРµРјРµСЃС‚РёС‚СЊ РІРІРµСЂС…" : 'Move to top'
                );
        $switch_view = UserInputHandlerRegistry::create_action(
                        $this, 'switch_view');
        $switch_view['caption'] = (
                is_interface_language_russian_or_similar() ? "Р’РёРґ" : 'View mode'
                );
        $view_info = UserInputHandlerRegistry::create_action(
                        $this, 'ex_view_info');
        $view_info['caption'] = (
                is_interface_language_russian_or_similar() ? "Р�РЅС„Рѕ" : 'Info'
                );
       $menu_items[] = array(
            GuiMenuItemDef::caption => $move_fav_top_action['caption'],
            GuiMenuItemDef::action => $move_fav_top_action
        );
        $menu_items[] = array(
            GuiMenuItemDef::caption => $remove_fav_action['caption'],
            GuiMenuItemDef::action => $remove_fav_action
        );
        $popup_search_action = ActionFactory::show_popup_menu($menu_items);

        $skip_details = isset($plugin_cookies->skip_details) ? $plugin_cookies->skip_details : 'no';

        if ($skip_details === 'yes')
        {
            return array
                (
                GUI_EVENT_KEY_ENTER => UserInputHandlerRegistry::create_action($this, 'view_series'),
                GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                GUI_EVENT_KEY_A_RED => $switch_view,
                GUI_EVENT_KEY_INFO => $view_info,
                GUI_EVENT_KEY_POPUP_MENU => $popup_search_action
            );
        }
        else
        {
            return array
                (
                GUI_EVENT_KEY_ENTER => $view_info,
                GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                GUI_EVENT_KEY_A_RED => $switch_view,
                GUI_EVENT_KEY_POPUP_MENU => $popup_search_action
            );
        }
    }

    ///////////////////////////////////////////////////////////////////////
    public function get_video_action_map($media_url, &$plugin_cookies) 
    {
        return array
            (
            GUI_EVENT_KEY_ENTER => self::$open_folder_action        );
    }

    ///////////////////////////////////////////////////////////////////////
    public function get_main_menu_action_map($media_url, &$plugin_cookies)
    {
		$ver = file_get_contents(dirname(__FILE__).'/VERSION');
		$ver = str_replace("п»їversion=", "", $ver);
		$ver = str_replace('date=', "[", $ver);
		$ver = str_replace(" 00:00", "]", $ver);
		$ver = str_replace("\n", " ", $ver);
		
		$setup_view = UserInputHandlerRegistry::create_action(
                        $this, 'setup');
        $setup_view['caption'] = (is_interface_language_russian_or_similar() ? "РќР°СЃС‚СЂРѕР№РєРё" : 'Settings');
		
		$info_release = UserInputHandlerRegistry::create_action(
                        $this, 'info_release');
        $info_release['caption'] = (is_interface_language_russian_or_similar() ? "Р�Р·РјРµРЅРµРЅРёСЏ РІ v$ver" : 'Info Release');

        return array
            (
            GUI_EVENT_KEY_ENTER => self::$open_folder_action,
			GUI_EVENT_KEY_B_GREEN => $setup_view,
            GUI_EVENT_KEY_C_YELLOW => $info_release,
        );
    }

    ///////////////////////////////////////////////////////////////////////
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //////////hd_print('--> Entry handler: handle_user_input:');
        foreach ($user_input as $key => $value)
            //////////hd_print("  $key => $value");
        update_interface_language();

        $do_search_action = UserInputHandlerRegistry::create_action($this, 'do_search');
        $do_new_search_action = UserInputHandlerRegistry::create_action($this, 'do_new_search');
        $perform_new_search_action = UserInputHandlerRegistry::create_action($this, 'perform_new_search');
        $do_add_search_action = UserInputHandlerRegistry::create_action($this, 'do_add_search');
        $do_mark_viewed = UserInputHandlerRegistry::create_action($this, 'mark_viewed');
        $add_fav_complite = UserInputHandlerRegistry::create_action($this, 'add_fav_complite');
        $del_fav_complite = UserInputHandlerRegistry::create_action($this, 'del_fav_complite');
        $just_play = UserInputHandlerRegistry::create_action($this, 'just_play');

        if (isset($user_input->control_id))
        {
            $control_id = $user_input->control_id;
            switch ($control_id)
            {
                case 'search':
                    $new_value = $user_input->{$control_id};
                    // last entered search
                    $plugin_cookies->search = $new_value;
                    $defs = $this->do_get_search_defs($plugin_cookies);
                    return  ActionFactory::reset_controls
                            (
                                $defs,
                                null,
                                1
                            );
//                    return null;
                case 'perform_new_search':
                    $new_value = $plugin_cookies->search;
                    // perform search
                    $search_text = urlencode($new_value);
                    ////////hd_print("Search: performing search of $search_text");
                    $search_url = HD::encode_user_data(array('ser_feed' => $search_text));
                    return array
                    (
                        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
                        GuiAction::data =>
                        array
                        (
                            'media_url' => $search_url
                        )
                    );
                case 'do_new_search':
                    $new_value = $plugin_cookies->search;
                    // search history
                    $search_items = isset($plugin_cookies->search_items) ? $plugin_cookies->search_items : '';
                    $plugin_cookies->search_items = ListUtil::add_item($search_items,$new_value);
                    return ActionFactory::invalidate_folders(array($user_input->parent_media_url), $perform_new_search_action);
                case 'do_search':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);
                    if ($user_data->ser_feed != '')
                        break;

                    $defs = $this->do_get_search_defs($plugin_cookies);

                    return ActionFactory::show_dialog(
                            (is_interface_language_russian_or_similar() ? 'РџРѕРёСЃРє' : 'Search'),
                            $defs);
                    
                    break;
                case 'skin':
                    $new_value = $user_input->{$control_id};
                    //////////hd_print("Setup: changing $control_id value to $new_value");
                    $plugin_cookies->skin = $new_value;
                    shell_exec("cp -f /persistfs/plugins/anidub/skins/$new_value/icons/logo.png /persistfs/plugins/anidub.ws/skins/logo.png");
					shell_exec("cp -f /persistfs/plugins/anidub/skins/$new_value/icons/no_cover.png /persistfs/plugins/anidub.ws/skins/no_cover.png");
                    break;
                case 'restart':
                    shell_exec('killall shell');
                    break;
				case 'info_release':
                   
					$post_action = null;
					
						$doc = HD::http_get_document('http://dl.dropboxusercontent.com/u/41185196/Dune/Update/anidub/info.txt');
						$tmp = explode('>>>', $doc);
						$text = $tmp[0];
						$defs = array();
						$texts = explode("\n", $text);
						$texts = array_values($texts);
						foreach($texts as $text)
						{
						$text = str_remove_spec($text);
						ControlFactory::add_label($defs, "", $text);
						}
						ControlFactory::add_custom_close_dialog_and_apply_buffon($defs, 'setup', 'Ok', 150,  $post_action);

						return ActionFactory::show_dialog('Р�РЅС„РѕСЂРјР°С†РёСЏ РѕР± РёР·РјРµРЅРµРЅРёСЏС….', $defs);
                    break;
				case 'setup':
                    return ActionFactory::open_folder('setup');
					break;
                case 'show_main_icon':
                    $new_value = $user_input->{$control_id};
                    //////////hd_print("Setup: changing $control_id value to $new_value");
                    $plugin_cookies->main_icon = $new_value;
		            if ($new_value == 'yes')
		            {
			            $plugin_cookies->show_channels_v4 = 'no';
			            $plugin_cookies->show_search_v4 = 'no';
			            $plugin_cookies->show_favorites = 'no';
		            }
		            else
		            {
			            $plugin_cookies->show_channels_v4 = 'yes';
			            $plugin_cookies->show_search_v4 = 'yes';
			            $plugin_cookies->show_favorites = 'yes';
		            }
                    return ActionFactory::reset_controls(
                            $this->do_get_control_defs($plugin_cookies)
                    );
                    break;
                case 'video_quality':
                    $new_value = $user_input->{$control_id};
                    //////////hd_print("Setup: changing $control_id value to $new_value");
                    $plugin_cookies->video_quality = $new_value;
                    return ActionFactory::reset_controls(
                            $this->do_get_control_defs($plugin_cookies)
                    );
                    break;
                case 'use_osk':
                    $new_value = $user_input->{$control_id};
                    //////////hd_print("Setup: changing $control_id value to $new_value");
                    $plugin_cookies->use_osk = $new_value;
                    return ActionFactory::reset_controls(
                            $this->do_get_control_defs($plugin_cookies)
                    );
                    break;
                case 'skip_details':
                    $new_value = $user_input->{$control_id};
                    //////////hd_print("Setup: changing $control_id value to $new_value");
                    $plugin_cookies->skip_details = $new_value;
                    return ActionFactory::reset_controls(
                            $this->do_get_control_defs($plugin_cookies)
                    );
                    break;
                case 'buf_time':
                    $new_value = $user_input->{$control_id};
                    //////////hd_print("Setup: changing $control_id value to $new_value");
                    $plugin_cookies->buf_time = $new_value;
                    return  ActionFactory::reset_controls
                            (
                                $this->do_get_control_defs($plugin_cookies)
                            );
                    break;
                case 'ex_view_info':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);
                    
                    if (!HD::has_attribute($user_data, 'video_id'))
                        return null;
                        
                    return array
                        (
                             GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
                             GuiAction::data => array(
                                    'media_url' => HD::encode_user_data(
                                                        array(
                                                            'info' => '1',
                                                            'video_id' => $user_data->video_id,
                                                            'season_ref' => $user_data->video_id,
                                                            //'season_title' => $user_data->season_title,
                                                        )
                                                    )
                             )
                         );
                    break;
                    
                case 'add_fav_complite':
                    return ActionFactory::show_title_dialog(
                        (
                            is_interface_language_russian_or_similar() ? 'Р’РёРґРµРѕ РґРѕР±Р°РІР»РµРЅРѕ РІ РёР·Р±СЂР°РЅРЅРѕРµ' : "Video has been added to favorites"
                        )
                    );
                    break;
                 case 'del_fav_complite':
                    return ActionFactory::show_title_dialog(
                        (
                            is_interface_language_russian_or_similar() ? 'Р’РёРґРµРѕ Р±С‹Р»Рѕ СѓРґР°Р»РµРЅРѕ РёР· РёР·Р±СЂР°РЅРЅРѕРіРѕ' : "Video has been deleted from favorites"
                        )
                    );
                    break;
                   
                case 'add_favorite':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    if ($user_input->selected_media_url === 'right_button')
                        HD::decode_user_data($user_input->parent_media_url, $media_str, $user_data);
                    else
                        HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);
		            
                    if (HD::has_attribute($user_data, 'video_id'))
                    	$video_id = $user_data->video_id;
                    else
                        return null;

                    $fav_items = isset($plugin_cookies->fav_items) ? $plugin_cookies->fav_items : '';

                    if (!ListUtil::is_in_list($fav_items, $video_id))
                    {
		                $plugin_cookies->fav_items = ListUtil::add_item($fav_items,$video_id);
                        $plugin_cookies->$video_id = "";
          
                        $items = $this->parse_season($video_id, $plugin_cookies);

                        foreach($items as $v)
                        {
                            HD::decode_user_data($v, $media_str, $udata);
                            
                            $s = $udata->episode_ref;

                            if (strlen($s) == 0)
                                continue;
                                
                            $i = $plugin_cookies->$video_id;
                            
                            $plugin_cookies->$video_id = ListUtil::add_item($i, $s);
                        }
                        
                        if ($user_input->selected_media_url === 'right_button')
                        {
                            return ActionFactory::invalidate_folders(array($user_input->parent_media_url), $add_fav_complite);
                        }
                        else
                        {
                            return ActionFactory::show_title_dialog('Р’РёРґРµРѕ РґРѕР±Р°РІР»РµРЅРѕ РІ РёР·Р±СЂР°РЅРЅРѕРµ');
                        }
                    }
                    else
                    {
                        return ActionFactory::show_title_dialog('Р’РёРґРµРѕ СѓР¶Рµ РІ РёР·Р±СЂР°РЅРЅРѕРј');
                    }
                    break;
                case 'remove_fav':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    if ($this->fav_build_in_progess)
                        return ActionFactory::show_title_dialog('РџРѕРґРѕР¶РґРёС‚Рµ РѕРєРѕРЅС‡Р°РЅРёСЏ РїРѕСЃС‚СЂРѕРµРЅРёСЏ СЃРїРёСЃРєР°!');
                        
                    if ($user_input->selected_media_url === 'right_button')
                        HD::decode_user_data($user_input->parent_media_url, $media_str, $user_data);
                    else
                        HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);

                    if (HD::has_attribute($user_data, 'video_id'))
                        $video_id = $user_data->video_id;
                    else if (HD::has_attribute($user_data, 'fav'))
                        $video_id = $user_data->fav;
                    else
                        return null;

                    $fav_items = isset($plugin_cookies->fav_items) ? ListUtil::del_item($plugin_cookies->fav_items, $video_id) : '';
                    $plugin_cookies->fav_items = $fav_items;
                    if (isset($plugin_cookies->$video_id))
                    {
                        unset($plugin_cookies->$video_id);
                    }
                    //{ Viewed items
                    $s = $video_id . ".viewed";
                    if (isset($plugin_cookies->$s))
                    {
                        unset($plugin_cookies->$s);
                    }
                    //} Viewed items
                    
                    $folder = FolderCache::get('main_menu:fav');
                    $folder->set_expired();
                    if ($user_input->selected_media_url === 'right_button')
                    {
                        return ActionFactory::invalidate_folders(array($user_input->parent_media_url), $del_fav_complite);
                    }
                    else
                    {
                        return ActionFactory::invalidate_folders(array($user_input->parent_media_url), null);
                    }
                    break;
                case 'move_fav_top':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    if ($this->fav_build_in_progess)
                        return ActionFactory::show_title_dialog('РџРѕРґРѕР¶РґРёС‚Рµ РѕРєРѕРЅС‡Р°РЅРёСЏ РїРѕСЃС‚СЂРѕРµРЅРёСЏ СЃРїРёСЃРєР°!');
                    HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);
                    $video_id = $user_data->video_id;
                    $fav_items = isset($plugin_cookies->fav_items) ? $plugin_cookies->fav_items : '';
                    $plugin_cookies->fav_items = ListUtil::add_item($fav_items,$video_id);
                    return ActionFactory::invalidate_folders(array($user_input->parent_media_url), null);
                    break;
                case 'just_play':
                    return array(
                            GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
                            GuiAction::data =>
                                array(
                                    'media_url' => $user_input->selected_media_url
                                )
                    );
                    break;
                case 'play_item':
                    if (!isset($user_input->selected_media_url))
                        return null;
                  
                    return ActionFactory::invalidate_folders(array($user_input->selected_media_url, $user_input->parent_media_url), $just_play);

                    break;
		        case 'view_series':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    
                    HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);

		            $n_series = 0;
		            if (HD::has_attribute($user_data, 'n_series'))
			            $n_series = $user_data->n_series;
	
		            $ndx_series = -1;
		            if (HD::has_attribute($user_data, 'ndx_series'))
			            $ndx_series = $user_data->ndx_series;

		            if ($n_series < 2 || $ndx_series >= 0)
		            {
	                    return array(
        	                GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
                	        GuiAction::data =>
                        	array(
	                            'media_url' => $user_input->selected_media_url
        	                )
			            );
		            }
		            else
		            {
	                    return array(
        	                GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
                	        GuiAction::data =>
                        	array(
	                            'media_url' => $user_input->selected_media_url
        	                )
			            );
                   }
		           break;
				 
				case 'remove_search':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);
                    $ser_feed = urldecode($user_data->ser_feed);
                    ////////hd_print("removing search: $ser_feed");
                    $search_items = isset($plugin_cookies->search_items) ? ListUtil::del_item($plugin_cookies->search_items, $ser_feed) : '';
                    $plugin_cookies->search_items = $search_items;
                    ////////hd_print("-- search list: $search_items");
                    return ActionFactory::update_regular_folder(
                                    $this->get_regular_folder_items('main_menu:search', 0, $plugin_cookies), true);
                    break;
                case 'move_search_top':
                    if (!isset($user_input->selected_media_url))
                        return null;
                    HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);
                    $ser_feed = urldecode($user_data->ser_feed);
                    $search_items = isset($plugin_cookies->search_items) ? $plugin_cookies->search_items : '';
                    $plugin_cookies->search_items = ListUtil::add_item($search_items,$ser_feed);
                    return ActionFactory::update_regular_folder(
                                    $this->get_regular_folder_items('main_menu:search', 0, $plugin_cookies), true);
                    break;
                case 'mark_viewed':
                    //'url_series'
                    if (isset($user_input->selected_media_url))
                    {
                        HD::decode_user_data($user_input->selected_media_url, $media_str, $user_data);
                        if (HD::has_attribute($user_data, 'video_id'))
                            $video_id = $user_data->video_id;
                        else if (HD::has_attribute($user_data, 'season_ref'))
                            $video_id = $user_data->season_ref;
                        if (ListUtil::is_in_list($plugin_cookies->fav_items, $video_id))
                        {
                            if (isset($plugin_cookies->$video_id))
                            {
                                if (HD::has_attribute($user_data, 'url_series'))
                                    $url = $user_data->url_series;
                                else if (HD::has_attribute($user_data, 'episode_ref'))
                                    $url = $user_data->episode_ref;
                                if (!ListUtil::is_in_list($plugin_cookies->$video_id, $url))
                                {
                                    $viewed = $plugin_cookies->$video_id;
                                    $plugin_cookies->$video_id = ListUtil::add_item($viewed,$url);
                                }
                            }
                            //{ Viewed items
                            else
                            {
                                $plugin_cookies->$video_id = $user_data->url_series;
                            }
                            $s = $video_id . ".viewed";
                            if (!isset($plugin_cookies->$s))
                                $plugin_cookies->$s = "";
                            if (HD::has_attribute($user_data, 'url_series'))
                                $url = $user_data->url_series;
                            else if (HD::has_attribute($user_data, 'episode_ref'))
                                $url = $user_data->episode_ref;
                            if (!ListUtil::is_in_list($plugin_cookies->$s, $url))
                            {
                                $i = $plugin_cookies->$s;       
                                $plugin_cookies->$s = ListUtil::add_item($i, $url);
                            }
                            //} Viewed items
                        }
                    }
                    return ActionFactory::update_regular_folder(
                                    $this->get_regular_folder_items($user_input->parent_media_url, 0, $plugin_cookies), true);
                    break;
            }
        }
        // ActionFactory::open_folder($user_input->selected_media_url);
        return array
            (
            GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
            GuiAction::data => array(
                'media_url' => $user_input->selected_media_url
                    )
             );
    }
    ///////////////////////////////////////////////////////////////////////


    protected function add_text_field(&$defs, $name, $title, $initial_value, $numeric, $password, $has_osk, $always_active, $width, $need_confirm = false, $need_apply = false) {
        ControlFactory::add_text_field($defs, $this, null,
                        $name, $title, $initial_value,
                        $numeric, $password, $has_osk, $always_active, $width,
                        $need_confirm, $need_apply);
    }

    ///////////////////////////////////////////////////////////////////////
    protected function add_combobox(&$defs, $name, $title, $initial_value, $value_caption_pairs, $width, $need_confirm = false, $need_apply = false)
    {
        ControlFactory::add_combobox($defs, $this, null,
                        $name, $title, $initial_value, $value_caption_pairs, $width,
                        $need_confirm, $need_apply);
    }

    protected function add_button(&$defs,$name, $title, $caption, $width)
    {
        ControlFactory::add_button($defs, $this, null,
            $name, $title, $caption, $width);
    }

    ///////////////////////////////////////////////////////////////////////
    public function do_get_control_defs(&$plugin_cookies)
    {
        $defs = array();

        $main_icon = isset($plugin_cookies->main_icon) ? $plugin_cookies->main_icon : 'yes';
        $use_osk = isset($plugin_cookies->use_osk) ? $plugin_cookies->use_osk : 'yes';
        $skip_details = isset($plugin_cookies->skip_details) ? $plugin_cookies->skip_details : 'no';
        $video_quality = isset($plugin_cookies->video_quality) ? $plugin_cookies->video_quality : 1;
        $buf_time = isset($plugin_cookies->buf_time) ? $plugin_cookies->buf_time : 0;

        $vquality = $this->get_quality_array(true);
        
		$ver = file_get_contents(dirname(__FILE__).'/VERSION');
		$ver = str_replace("п»їversion=", "", $ver);
		$ver = str_replace('date=', "[", $ver);
		$ver = str_replace(" 00:00", "]", $ver);
		$ver = str_replace("\n", " ", $ver);
		
        $show_ops = array();
        if (is_interface_language_russian_or_similar ()) {
            $show_ops['yes'] = 'Р”Р°';
            $show_ops['no'] = 'РќРµС‚';
        } else {
            $show_ops['yes'] = 'Yes';
            $show_ops['no'] = 'No';
        }
        
        $this->add_button
        (
            $defs,
            'info_release',
            "Р’РµСЂСЃРёСЏ: $ver",
            'Р�РЅС„РѕСЂРјР°С†РёСЏ РѕР± РёР·РјРµРЅРµРЅРёСЏС…',
            0
        );
        
        $this->add_combobox($defs,
                'show_main_icon',
                'РџРѕРєР°Р·С‹РІР°С‚СЊ РёРєРѕРЅРєСѓ РІ РіР»Р°РІРЅРѕРј РјРµРЅСЋ:',
                $main_icon, $show_ops, 0, true
        );

        $this->add_combobox($defs,
                'video_quality',
                'РљР°С‡РµСЃС‚РІРѕ РІРёРґРµРѕ:',
                $video_quality, $vquality, 0, true
        );
        
	    $this->add_combobox($defs,
        	       'use_osk',
                'Р�СЃРїРѕР»СЊР·РѕРІР°С‚СЊ СЌРєСЂР°РЅРЅСѓСЋ РєР»Р°РІРёР°С‚СѓСЂСѓ РІ РїРѕРёСЃРєРµ:',
                $use_osk, $show_ops, 0, true
            );
		$this->add_combobox($defs,
                'skip_details',
                'РџСЂРѕРїСѓСЃРєР°С‚СЊ РѕРїРёСЃР°РЅРёРµ С„РёР»СЊРјР°:',
                $skip_details, $show_ops, 0, true
        );
        $show_buf_time_ops = array();
        
        $show_buf_time_ops[0] = 'РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ';
        $show_buf_time_ops[500] = '0.5 СЃ';
        $show_buf_time_ops[1000] = '1 СЃ';
        $show_buf_time_ops[2000] = '2 СЃ';
        $show_buf_time_ops[3000] = '3 СЃ';
        $show_buf_time_ops[5000] = '5 СЃ';
        $show_buf_time_ops[10000] = '10 СЃ';

        $this->add_combobox
        (
            $defs,
            'buf_time',
            'Р’СЂРµРјСЏ Р±СѓС„РµСЂРёР·Р°С†РёРё:',
            $buf_time, $show_buf_time_ops, 0, true
        );
 /*       $skin_ops = array();

        $skin_ops['default'] = 'РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ';
        $skin_ops['juniperus'] = 'РћС‚ Juniperus (РџРёСЂР°С‚СЃРєР°СЏ С‚РµРјР°)';

        $skin = isset($plugin_cookies->skin) ? $plugin_cookies->skin : 'default';
        $this->add_combobox
        (
            $defs,
            'skin',
            'РћС„РѕСЂРјР»РµРЅРёРµ (С‚СЂРµР±СѓРµС‚СЃСЏ РїРµСЂРµР·Р°РіСЂСѓР·РєР°):',
            $skin, $skin_ops, 0, true
        );
*/
        $this->add_button
        (
            $defs,
            'restart',
            '',
            'РџРµСЂРµР·Р°РіСЂСѓР·РёС‚СЊ РїР»РµРµСЂ',
            0
        );

       return $defs;
    }

    ///////////////////////////////////////////////////////////////////////
    public function do_get_search_defs(&$plugin_cookies)
    {
        $defs = array();
        $search_text = isset($plugin_cookies->search) ? $plugin_cookies->search : '';
        $use_osk = isset($plugin_cookies->use_osk) ? $plugin_cookies->use_osk : 'yes';
        $this->add_text_field($defs,
                'search', "",
                $search_text, 0, 0,
                ($use_osk === 'yes' ? 1 : 0),
                true, 1300, 0, true
        );
        if ($use_osk === 'yes')
            ControlFactory::add_vgap($defs, 500);
        else
            ControlFactory::add_vgap($defs, 50);

        $do_new_search_action = UserInputHandlerRegistry::create_action($this, 'do_new_search');

        ControlFactory::add_custom_close_dialog_and_apply_buffon($defs,'apply_subscription',
                (is_interface_language_russian_or_similar() ? 'Р�СЃРєР°С‚СЊ' : 'Search'),
                300, $do_new_search_action);
        ControlFactory::add_close_dialog_button($defs,
                'РћС‚РјРµРЅР°', 300);

        return $defs;
    }

    ///////////////////////////////////////////////////////////////////////
    public function get_handler_id()
    {
        return self::ID;
    }

    ///////////////////////////////////////////////////////////////////////
    public function get_next_folder_view($media_url, &$plugin_cookies)
    {
        //////////hd_print('get_next_folder_view');
        $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;
        if ($view_type == 3)
            $view_type = 1;
        else
            ++$view_type;
            
        $plugin_cookies->view_type = $view_type;
        
        return $this->get_folder_view($media_url, $plugin_cookies); 
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_folder_view($media_url, &$plugin_cookies)
    {
	    //////////hd_print('--->>> get_folder_view --->>>' . $media_url);

	    if ($media_url === 'main_menu')
	    {
            return  array
                    (
                        PluginFolderView::view_kind                 => PLUGIN_FOLDER_VIEW_REGULAR,
                        PluginFolderView::multiple_views_supported  => false,
                        PluginFolderView::data                      => array
                        (
                            PluginRegularFolderView::async_icon_loading => false,
                            PluginRegularFolderView::initial_range =>
                                $this->get_main_menu_items($media_url, $plugin_cookies),
                            PluginRegularFolderView::view_params => self::get_main_menu_folder_view($plugin_cookies),
                            PluginRegularFolderView::base_view_item_params => self::get_main_vip($plugin_cookies),
                            PluginRegularFolderView::not_loaded_view_item_params => array(),
                            PluginRegularFolderView::actions => $this->get_main_menu_action_map($media_url, $plugin_cookies)
                        )
                    );
	    }
        else if 
	    (
		    $media_url === 'main_menu:videos' ||
			$media_url === 'main_menu:year' ||
			$media_url === 'main_menu:studii' ||
		    $media_url === 'main_menu:search'
	    )
        {
		    switch ($media_url) 
		    {
                    case 'main_menu:search':
                        $actions = $this->get_search_action_map($media_url, $plugin_cookies);
                        break;
                    case 'main_menu:videos':
                        $actions = $this->get_video_action_map($media_url, $plugin_cookies);
                        break;
					case 'main_menu:year':
                        $actions = $this->get_video_action_map($media_url, $plugin_cookies);
                        break;
					case 'main_menu:studii':
                        $actions = $this->get_video_action_map($media_url, $plugin_cookies);
                        break;
		    }

            return  array
                    (
                        PluginFolderView::view_kind                 => PLUGIN_FOLDER_VIEW_REGULAR,
                        PluginFolderView::multiple_views_supported  => false,
                        PluginFolderView::data                      => array
                        (
                            PluginRegularFolderView::async_icon_loading => false,
                            PluginRegularFolderView::initial_range =>
                                $this->get_main_menu_items($media_url, $plugin_cookies),
                            PluginRegularFolderView::view_params => array
                            (
                                ViewParams::num_cols => 1,
                                ViewParams::num_rows => 12,
								ViewParams::background_path => 'plugin_file://skins/default/icons/bg1.jpg',
								ViewParams::background_order => 0
                            ),
                            PluginRegularFolderView::base_view_item_params => self::$catalog_vip,
                            PluginRegularFolderView::not_loaded_view_item_params => array(),
                            PluginRegularFolderView::actions => $actions
                        )
                    );
	    }
	    elseif ($media_url === 'setup')
	    {
                $defs = $this->do_get_control_defs($plugin_cookies);
                $folder_view = array
                    (
                	    PluginControlsFolderView::defs => $defs,
                	    PluginControlsFolderView::initial_sel_ndx => -1,
            	    );
                return array
                    (
                	    PluginFolderView::multiple_views_supported => false,
	                    PluginFolderView::archive => null,
        	            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
                	    PluginFolderView::data => $folder_view,
            	    );
            }

         $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;
         HD::decode_user_data($media_url, $media_str, $user_data);

         if
         (
//            $media_url === 'main_menu:fav' ||
            HD::has_attribute($user_data, 'category_ref')// ||
//            HD::has_attribute($user_data, 'ser_feed')
         )
         {
             if (HD::has_attribute($user_data, 'ser_feed'))
             {
                $actions = $this->get_item_action_map($media_url, $plugin_cookies);
                $not_loaded_item = self::$catalog_vip;
             }
             else if ($media_url === 'main_menu:fav')
             {
                $actions = $this->get_fav_action_map($media_url, $plugin_cookies);
                $not_loaded_item = self::$catalog_vip;
             }
             else
             {
                $actions = $this->get_video_action_map($media_url, $plugin_cookies);
                $not_loaded_item = array();
             }

            return  array
                    (
                        PluginFolderView::view_kind                 => PLUGIN_FOLDER_VIEW_REGULAR,
                        PluginFolderView::multiple_views_supported  => false,
                        PluginFolderView::data                      => array
                        (
                            PluginRegularFolderView::async_icon_loading => false,
                            PluginRegularFolderView::initial_range =>
                                $this->get_regular_folder_items($media_url, 0, $plugin_cookies),
                            PluginRegularFolderView::view_params => array
                            (
                                ViewParams::num_cols => 1,
                                ViewParams::num_rows => 12,
								ViewParams::background_path => 'plugin_file://skins/default/icons/bg1.jpg',
								ViewParams::background_order => 0
                            ),
                            PluginRegularFolderView::base_view_item_params => self::$catalog_vip,
                            PluginRegularFolderView::not_loaded_view_item_params => $not_loaded_item,
                            PluginRegularFolderView::actions => $actions
                        )
                    );
         }
         else if 
         (
            $media_url === 'main_menu:fav' ||
            HD::has_attribute($user_data, 'subcategory_ref') ||
            HD::has_attribute($user_data, 'ser_feed')
         )
         {
//            $actions = $this->get_video_action_map($media_url, $plugin_cookies);
             if ($media_url === 'main_menu:fav')
             {
                $actions = $this->get_fav_action_map($media_url, $plugin_cookies);
             }
             else
             {
                $actions = $this->get_item_action_map($media_url, $plugin_cookies);
             }

            return array(
                PluginFolderView::view_kind                 => PLUGIN_FOLDER_VIEW_REGULAR,
                PluginFolderView::multiple_views_supported  => true,
                PluginFolderView::data                      => array(
                    PluginRegularFolderView::async_icon_loading => true,
                    PluginRegularFolderView::initial_range =>
                        $this->get_loading_items($plugin_cookies),

                    PluginRegularFolderView::view_params => array(
                        ViewParams::num_cols => ($view_type == 1 ? 2 : 1),
                        ViewParams::num_rows => ($view_type == 1 ? 6 : ($view_type == 2 ? 3 : ($view_type == 4 ? 1 : 10))),
						ViewParams::background_path => 'plugin_file://skins/default/icons/bg1.jpg',
						ViewParams::background_order => 0,
                        ViewParams::paint_details => ($view_type == 4 ? false : true),
                        ViewParams::zoom_detailed_icon => true,
                        ViewParams::paint_item_info_in_details => true,
                        ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,// FONT_SIZE_SMALL,
                        
                        ViewParams::paint_sandwich => (($view_type == 1 || $view_type == 4) ? true : false),
                        ViewParams::sandwich_base => 'gui_skin://special_icons/sandwich_base.aai',
                        ViewParams::sandwich_mask => 'cut_icon://{name=sandwich_mask}',
                        ViewParams::sandwich_cover => 'cut_icon://{name=sandwich_cover}',
                        ViewParams::sandwich_width => 190,
                        ViewParams::sandwich_height => 290,
                        ViewParams::sandwich_icon_upscale_enabled => true,
                        ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ),

                    PluginRegularFolderView::base_view_item_params => array(
                        ViewItemParams::item_paint_icon => true,
                        ViewItemParams::icon_sel_scale_factor =>1.2,
                        ViewItemParams::icon_path => 'gui_skin://small_icons/movie.aai',
                        ViewItemParams::item_layout => HALIGN_LEFT,
                        ViewItemParams::icon_valign => VALIGN_CENTER,
                        ViewItemParams::icon_dx => 10,
                        ViewItemParams::icon_dy => -5,
                        ViewItemParams::icon_width => ($view_type == 1 ? 500 : ($view_type == 2 ? 150 : ($view_type == 4 ? 250 : 50))),
                        ViewItemParams::icon_height => ($view_type == 1 ? 90 : ($view_type == 2 ? 200 : ($view_type == 4 ? 350 : 50))),
                        ViewItemParams::icon_sel_margin_top => 0,
                        ViewItemParams::item_paint_caption => ($view_type == 1 ? false : true),
                        ViewItemParams::item_caption_width => ($view_type == 2 ? 950 : 1100)
                    ),

                    PluginRegularFolderView::not_loaded_view_item_params => array(
                        ViewItemParams::item_paint_icon => true,
                        ViewItemParams::icon_width => ($view_type == 1 ? 90 : ($view_type == 2 ? 150 : 50)),
                        ViewItemParams::icon_height => ($view_type == 1 ? 90 : ($view_type == 2 ? 200 : 50)),
                        ViewItemParams::icon_path => ($view_type != 3 ? 'gui_skin://large_icons/movie.aai' : 'gui_skin://small_icons/movie.aai')
                    ),

                    PluginRegularFolderView::actions => $actions
                )
            );
         }
         else if
         (
            HD::has_attribute($user_data, 'season_ref') &&
            !HD::has_attribute($user_data, 'info')
         )
         {
             if (isset($plugin_cookies->fav_items))
                $isfav = ListUtil::is_in_list($plugin_cookies->fav_items, $user_data->season_ref);
            
            if ($isfav)
            {
                    $add_action = UserInputHandlerRegistry::create_action(
                                $this, 'mark_viewed');
                    $add_action['caption'] = (
                                is_interface_language_russian_or_similar() ? 'РџСЂРѕСЃРјРѕС‚СЂРµРЅРѕ' : "Mark as viewed"
                            );
                $actions = array
                    (
                        GUI_EVENT_KEY_ENTER => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                        GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                        GUI_EVENT_KEY_B_GREEN => $add_action
                    );
            }
            else
            {
                $actions = array
                (
                        GUI_EVENT_KEY_ENTER => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                        GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action
                );
            }

            return array
            (
                PluginFolderView::view_kind                 => PLUGIN_FOLDER_VIEW_REGULAR,
                PluginFolderView::multiple_views_supported  => false,
                PluginFolderView::data                      => array(
                    PluginRegularFolderView::async_icon_loading => true,
                    PluginRegularFolderView::initial_range =>
                        $this->get_regular_folder_items($media_url, 0, $plugin_cookies),

                    PluginRegularFolderView::view_params => array(
                        ViewParams::num_cols => 1,
                        ViewParams::num_rows => 10,
						ViewParams::background_path => 'plugin_file://skins/default/icons/bg1.jpg',
						ViewParams::background_order => 0,
                        ViewParams::paint_details => false,
                    ),

                    PluginRegularFolderView::base_view_item_params => array(
                        ViewItemParams::item_paint_icon => true,
                        ViewItemParams::icon_path => 'gui_skin://small_icons/movie.aai',
                    ViewItemParams::item_layout => HALIGN_LEFT,
                       ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1550
                    ),

                    PluginRegularFolderView::not_loaded_view_item_params => array(),

                    PluginRegularFolderView::actions => $actions
                )
            );
         }
         
         if (HD::has_attribute($user_data, 'info'))
         {
            $c = $this->parse_season_info($user_data->video_id);
            HD::decode_user_data($c, $media_str, $user_data);

            $movie = array(
                PluginMovie::name => $user_data->title,
                PluginMovie::name_original => $user_data->name_original,
                PluginMovie::description => $user_data->description,
                PluginMovie::poster_url => $user_data->video_image_url,
                PluginMovie::length_min => $user_data->duration_min,
                PluginMovie::year => $user_data->year,
                PluginMovie::directors_str => $user_data->director,
                PluginMovie::scenarios_str => '',
                PluginMovie::actors_str => $user_data->actors,
                PluginMovie::genres_str => $user_data->genre,
                PluginMovie::rate_imdb => '',
                PluginMovie::rate_kinopoisk => '',
                PluginMovie::rate_mpaa => '',
                PluginMovie::country => $user_data->country,
                PluginMovie::budget => ''
                );
                

            $from_fav = false;
            $fav_items = isset($plugin_cookies->fav_items) ? $plugin_cookies->fav_items : '';
            if (ListUtil::is_in_list($fav_items, $user_data->video_id))
                $from_fav = true;
                
            if ($from_fav)
            {
                $right_button_action = UserInputHandlerRegistry::create_action(
                            $this, 'remove_fav');
                $right_button_action['caption'] = (
                            is_interface_language_russian_or_similar() ? "РЈРґР°Р»РёС‚СЊ РёР· Р�Р·Р±СЂР°РЅРЅРѕРіРѕ" : 'Remove from Favorites'
                        );
            }
            else
            {
                $right_button_action = UserInputHandlerRegistry::create_action(
                            $this, 'add_favorite');
                $right_button_action['caption'] = (
                            is_interface_language_russian_or_similar() ? 'Р”РѕР±Р°РІРёС‚СЊ РІ Р�Р·Р±СЂР°РЅРЅРѕРµ' : "Add Favorite"
                        );
            }


            $right_button_caption = $right_button_action['caption'];
            
            $movie_folder_view = array
            (
                PluginMovieFolderView::movie => $movie,
                PluginMovieFolderView::has_right_button => true,
                PluginMovieFolderView::right_button_caption => $right_button_caption,
                PluginMovieFolderView::right_button_action => $right_button_action,
                PluginMovieFolderView::has_multiple_series => ($user_data->n_series > 1),
                PluginMovieFolderView::series_media_url => HD::encode_user_data(
                                                                array(
                                                                    'season_ref' => $user_data->video_id,
                                                                    'season_title' => $user_data->title,
                                                                    'n_series' => $user_data->n_series,
                                                                )
                                                           ),
				PluginMovieFolderView::params => array
				(			               
				PluginFolderViewParams::paint_path_box =>true,
                PluginFolderViewParams::paint_content_box_background => true,
               	PluginFolderViewParams::background_url => 'plugin_file://skins/default/icons/bg2.jpg',
				)
            );

             return array
                (
                    PluginFolderView::multiple_views_supported => false,
                    PluginFolderView::archive => null,
                    PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_MOVIE,
                    PluginFolderView::data => $movie_folder_view
                );
         }
         else if (HD::has_attribute($user_data, 'video_id'))
         {
	        $isfav = ListUtil::is_in_list($plugin_cookies->fav_items, $user_data->video_id);
            
            if ($isfav)
            {
                    $add_action = UserInputHandlerRegistry::create_action(
                                $this, 'mark_viewed');
                    $add_action['caption'] = (
                                is_interface_language_russian_or_similar() ? 'РџСЂРѕСЃРјРѕС‚СЂРµРЅРѕ' : "Mark as viewed"
                            );
                $actions = array
                    (
                        GUI_EVENT_KEY_ENTER => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                        GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                        GUI_EVENT_KEY_B_GREEN => $add_action
                    );
            }
            else
            {
                $actions = array
                    (
                        GUI_EVENT_KEY_ENTER => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action,
                        GUI_EVENT_KEY_PLAY => $this->get_play_action($media_url, $plugin_cookies),//self::$vod_play_action
                    );
            }
            
            return array(
                PluginFolderView::view_kind                 => PLUGIN_FOLDER_VIEW_REGULAR,
                PluginFolderView::multiple_views_supported  => false,
                PluginFolderView::data                      => array(
                    PluginRegularFolderView::async_icon_loading => true,
                    PluginRegularFolderView::initial_range =>
                        $this->get_regular_folder_items($media_url, 0, $plugin_cookies),

                    PluginRegularFolderView::view_params => array(
                        ViewParams::num_cols => 1,
                        ViewParams::num_rows => 10,
						ViewParams::background_path => 'plugin_file://skins/default/icons/bg1.jpg',
						ViewParams::background_order => 0,
                        ViewParams::paint_details => false,

                    ),

                    PluginRegularFolderView::base_view_item_params => array(
                        ViewItemParams::item_paint_icon => true,
                        ViewItemParams::icon_path => 'gui_skin://small_icons/movie.aai',
	                ViewItemParams::item_layout => HALIGN_LEFT,
       		        ViewItemParams::icon_valign => VALIGN_CENTER,
        	        ViewItemParams::icon_dx => 20,
        	        ViewItemParams::icon_dy => -5,
        	        ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
        	        ViewItemParams::item_caption_width => 1550
                    ),

                    PluginRegularFolderView::not_loaded_view_item_params => array(),

                    PluginRegularFolderView::actions => $actions
                )
            );
         }

	    if ($media_url === 'main_menu:fav')
            $actions = $this->get_fav_action_map($media_url, $plugin_cookies);
	    else
	        $actions = $this->get_item_action_map($media_url, $plugin_cookies);

        return array(
            PluginFolderView::view_kind                 => PLUGIN_FOLDER_VIEW_REGULAR,
            PluginFolderView::multiple_views_supported  => true,
            PluginFolderView::data                      => array(
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::initial_range =>
                    $this->get_loading_items($plugin_cookies),

                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => ($view_type == 1 ? 5 : 1),
                    ViewParams::num_rows => ($view_type == 1 ? 2 : ($view_type == 2 ? 3 : 10)),
					ViewParams::background_path => 'plugin_file://skins/default/icons/bg1.jpg',
					ViewParams::background_order => 0,
                    ViewParams::paint_details => true,
                    ViewParams::zoom_detailed_icon => true,
                    ViewParams::paint_item_info_in_details => true,
//                    ViewParams::detailed_icon_scale_factor => 0.5,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,// FONT_SIZE_SMALL,
                    
                    ViewParams::paint_sandwich => ($view_type == 1 ? true : false),
                    ViewParams::sandwich_base => 'gui_skin://special_icons/sandwich_base.aai',
                    ViewParams::sandwich_mask => 'cut_icon://{name=sandwich_mask}',
                    ViewParams::sandwich_cover => 'cut_icon://{name=sandwich_cover}',
                    ViewParams::sandwich_width => 190,
                    ViewParams::sandwich_height => 290,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,

//                    ViewParams::item_detailed_info_rel_y => 560
                ),

                PluginRegularFolderView::base_view_item_params => array(
                    ViewItemParams::item_paint_icon => true,
//                    ViewItemParams::icon_scale_factor =>0.75,
                    ViewItemParams::icon_sel_scale_factor =>1,
                    ViewItemParams::icon_path => 'gui_skin://small_icons/movie.aai',
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => ($view_type == 1 ? 190 : ($view_type == 2 ? 150 : 50)),
                    ViewItemParams::icon_height => ($view_type == 1 ? 290 : ($view_type == 2 ? 200 : 50)),
                    ViewItemParams::icon_sel_margin_top => 0,
                    ViewItemParams::item_paint_caption => ($view_type == 1 ? false : true),
                    ViewItemParams::item_caption_width => ($view_type == 2 ? 950 : 1100)
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_width => ($view_type == 1 ? 190 : ($view_type == 2 ? 150 : 50)),
                    ViewItemParams::icon_height => ($view_type == 1 ? 290 : ($view_type == 2 ? 200 : 50)),
                    ViewItemParams::icon_path => ($view_type != 3 ? 'gui_skin://large_icons/movie.aai' : 'gui_skin://small_icons/movie.aai')
                ),

                PluginRegularFolderView::actions => $actions
            )
        );

        throw new Exception('Unknown media url');
    }

    ///////////////////////////////////////////////////////////////////////
    private function parse_season_info($season_ref)
    {
        //////////hd_print("--> parse season info: started...");

        $api = HOST_API_URL;
        
        $items = array();

        $url = $season_ref;
        
        $doc = HD::http_get_document($url);
		////////hd_print("doc: --->>>$doc");
		$tmp = strstr($doc, "<div id='dle-content'>");
        $info_block = strstr($tmp, '<div class="related">', true);
		$info_block = str_replace('&laquo;', 'В«', $info_block);
		$info_block = str_replace('&ndash;', '-', $info_block);
		$info_block = str_replace("vkontakte.ru", "vk.com", $info_block);
        //////hd_print("info_block: --->>>$info_block");
		
		
        // title
        $tmp = explode('<h1 class="titlfull" itemprop="name">', $info_block);
        $video_title = str_remove_spec(strstr($tmp[1], '</h1>', true));
        $video_title = strip_tags($video_title);
		
		// image
        $tmp = explode('<link rel="image_src" href="', $info_block);
        $video_image = str_remove_spec(strstr($tmp[1], '"', true));

		
		
        // description
        $description = '';
        $tmp = explode('РћРїРёСЃР°РЅРёРµ</b>:', $info_block);
        if (count($tmp) > 1)
		{
            $tmp = strstr($tmp[1], '</div>', true);
            $description = str_remove_spec($tmp);
			$description = strip_tags( $description);
        }
        $tmp = "";
        // year
        $year = '';
        $tmp = explode('Р“РѕРґ:', $info_block);
        if (count($tmp) > 1)
            $year = str_remove_spec(strstr($tmp[1], '</span>', true));
			$year = strip_tags($year);
			//$year = str_replace(" ", "", $year);
        // hd_print("--->>> $year");
        
		//name_original 
		$name_original = '';
        $tmp = explode('РџРµСЂРµРІРѕРґ:', $info_block);
        if (count($tmp) > 1)
            $name_original = str_remove_spec(strstr($tmp[1], '</span>', true));
			$name_original = str_replace(' /', '', $name_original);
			$name_original = 'РџРµСЂРµРІРѕРґ:' . (strip_tags($name_original));
        //// hd_print("name_original--->>> $name_original"); 		
			
        // country
        $country = '';
        $tmp = explode('РЎС‚СЂР°РЅР°:', $info_block);
        if (count($tmp) > 1)
            $country = str_remove_spec(strstr($tmp[1], '</span>', true));
			$country = strip_tags($country);
        // hd_print("--->>> $country");
			 
		// genre
        $genre = '';
        $tmp = explode("Р–Р°РЅСЂ:", $info_block);
        if (count($tmp) > 1)
            $genre = strstr($tmp[1], '</span>', true);
			$genre = str_remove_spec($genre);
			$genre = strip_tags($genre);
			//$genre = str_replace("РїСЂРµРјСЊРµСЂР°", " РџСЂРµРјСЊРµСЂР°:", $genre);
        // hd_print("--->>> $genre");
				
		
        // duration
        $duration_min = 0;
        $duration = '';
        // director
        $director = '';
        $tmp = explode('Р РµР¶РёСЃСЃРµСЂ:', $info_block);
        if (count($tmp) > 1)
            $director = str_remove_spec(strstr($tmp[1], '</span>', true));
			$director = strip_tags($director);
        // hd_print("--->>> $director");    
        // actors
        $actors = '';
        // number of series
		$videos = explode('<option  value="', $info_block);
        unset($videos[0]);
        $videos = array_values($videos);
        $n_series = count($videos);

        return  HD::encode_user_data
                (
                    array
                    (
                        'video_id' => $season_ref,
                        'n_series' => $n_series,
                        'page_ref' => $season_ref,
                        'video_image_url' => $video_image,
                        'title' => $video_title,
                        'genre' => str_remove_spec($genre),
                        'country' => str_remove_spec($country),
                        'year' => str_remove_spec($year),
                        'duration' => str_remove_spec($duration),
                        'duration_min' => $duration_min,
                        'director' => str_remove_spec($director),
                        'actors' => str_remove_spec($actors),
                        'description' => str_remove_spec($description),
						'name_original' => $name_original,
					//	'vyp' => $vyp,
                    )
                );
    }
    ///////////////////////////////////////////////////////////////////////

    private function parse_season($season_ref, &$plugin_cookies)
    {
        
		$api = HOST_API_URL;
        
        $items = array();

        $url = $season_ref;
        
        $doc = HD::http_get_document($url);
		$doc = str_replace('<option  value="http://cdn', '', $doc);
        $tmp = strstr($doc, "<div id='dle-content'>");
        $info_block = strstr($tmp, '<div class="related">', true);
		$info_block = str_replace('&laquo;', 'В«', $info_block);
		$info_block = str_replace('&ndash;', '-', $info_block);
		$info_block = str_replace("vkontakte.ru", "vk.com", $info_block);
		
		$info_block = str_replace('<option selected="selected" value=\'', '<option  value="', $info_block);
		$info_block = str_replace('<iframe name=\'film_main\' id=\'film_main\' src=\'', '<option  value="', $info_block);
		$info_block = str_replace('\' width=', '" width=', $info_block);
		$info_block = str_replace('\'>', '">', $info_block);
		$info_block = str_replace('|', '"', $info_block);
        //////hd_print("info_block: --->>>$info_block");
		
		
        // title
        $tmp = explode('<h1 class="titlfull" itemprop="name">', $info_block);
        $video_title = str_remove_spec(strstr($tmp[1], '</h1>', true));
        $video_title = strip_tags($video_title);
		
		// image
        $tmp = explode('<link rel="image_src" href="', $info_block);
        $video_image = str_remove_spec(strstr($tmp[1], '"', true));

		
		
        // description
        $description = '';
        $tmp = explode('РћРїРёСЃР°РЅРёРµ</b>:', $info_block);
        if (count($tmp) > 1)
		{
            $tmp = strstr($tmp[1], '</div>', true);
            $description = str_remove_spec($tmp);
			$description = strip_tags( $description);
        }
		
        $is_new = false;
        $videos = explode('<option  value="', $info_block);
            unset($videos[0]);
            $videos = array_values($videos);
			
            $episode_title = "";
            
            $n = 0;
            $ndx_series = 0;
            foreach($videos as $video)
            {	
                $episode_ref = strstr($video, '"', true);
				//////hd_print("--->>> episode_ref: $episode_ref");
				$tmp = explode('">', $video);
                $episode_title = strstr($tmp[1], '</', true);
				$episode_title = strip_tags($episode_title);
				
                
                if (isset($plugin_cookies->$season_ref))
                    if (!ListUtil::is_in_list($plugin_cookies->$season_ref, $episode_ref))
                        $is_new = true;

                array_push
                (
                    $items,
                    HD::encode_user_data
                    (
                        array
                        (
                            'episode_title' => str_remove_spec($episode_title),
                            'episode_ref' => str_remove_spec($episode_ref),
							'season_title' => $video_title,//str_remove_spec($season_title),
                            'season_ref' => str_remove_spec($season_ref),
                            'ndx_series' => $ndx_series,
                            'poster_url' => str_remove_spec($video_image),
                            'description' => str_remove_spec($description),
                        )
                    )
                );
                ++$this->m_items_count;
                ++$ndx_series;
			}
        return $items;
    }
    ///////////////////////////////////////////////////////////////////////
    private function get_next_fav($ref, &$plugin_cookies)
    {
        //////////hd_print("--> get favorites: started... $ref");
        
        if ($ref == "")
            return false;

        $api = HOST_API_URL;
        
        $items = array();

        //$url = $api . $ref;
        
         $tmp = explode('&', $ref);
        $ref = $tmp[0];
		$season_image = $tmp[1];
		
		$doc = HD::http_get_document($ref);
       	
		////////hd_print("doc: --->>>$doc");
		$tmp = strstr($doc, '<div id="ctrl_vt_short">');
        $info_block = strstr($tmp, '<div id="social_tags">', true);
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
                
                //////////hd_print("--->>> episode_ref: $episode_ref");

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

    public function get_regular_folder_items($media_url, $from_ndx, &$plugin_cookies)
    {
	    //////////hd_print("--> get_regular_folder_items(): starting." . $media_url);
        if 
	    (
		    $media_url === 'main_menu' ||
		    $media_url === 'main_menu:videos' ||
			$media_url === 'main_menu:year' ||
			$media_url === 'main_menu:studii' ||
		    $media_url === 'main_menu:audio' ||
		    $media_url === 'main_menu:search' ||
		    $media_url === 'main_menu:fav' && isset($plugin_cookies->dynamic_fav_off)
	    )
        {
            return $this->get_main_menu_items($media_url, $plugin_cookies);
        }

        if (intval($from_ndx) > 0)
            --$from_ndx; // replace aux "loading" item
            
	    $videos = false;
        $folder_items = array();
        $total_num = 0;

        HD::decode_user_data($media_url, $media_str, $user_data);

        if (HD::has_attribute($user_data, 'season_ref'))
        {
            if (HD::has_attribute($user_data, 'season_ref'))
                $season_ref = $user_data->season_ref;
            else if (HD::has_attribute($user_data, 'fav'))
                $season_ref = $user_data->fav;
            $videos = $this->parse_season($season_ref, $plugin_cookies);
            $total_num = 0;
                
            if ($videos === false)
            {
                //////////hd_print("--> get_regular_folder_items(): no more available.");
                $caption = (is_interface_language_russian_or_similar() ? 'Р‘РѕР»СЊС€Рµ РЅРёС‡РµРіРѕ РЅРµ РЅР°Р№РґРµРЅРѕ' : 'No more items found');
                $m_url = 'dummy';
                if (HD::has_attribute($user_data, 'fav'))
                {
                    $caption = (is_interface_language_russian_or_similar() ? 'HРµ РЅР°Р№РґРµРЅРѕ' : 'Not found');
                    $m_url = $media_url;
                }
                $more_items_available = 0;
            

                $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;
                array_push
                (
                    $folder_items,
                    array
                    (
                                    PluginRegularFolderItem::media_url => $m_url,
                                    PluginRegularFolderItem::caption => $caption,
                                    PluginRegularFolderItem::view_item_params => array
                                            (
                                                ViewItemParams::icon_width => ($view_type == 1 ? 90 : ($view_type == 2 ? 150 : 50)),
                                                ViewItemParams::icon_height => ($view_type == 1 ? 90 : ($view_type == 2 ? 200 : 50)),
                                                ViewItemParams::icon_path => ($view_type != 3 ? 'gui_skin://large_icons/movie.aai' : 'gui_skin://small_icons/movie.aai'),
                                                ViewItemParams::item_detailed_icon_path => 'missing://'
                                            )
                    )
                );
                ++$total_num;
            }
            else
            {
                //////////hd_print("--> get_regular_folder_items(): items count: " . count($videos));
                $more_items_available = 0;

                foreach ($videos as $v)
                {
                    HD::decode_user_data($v, $media_str, $user_data);

                    array_push
                    (
                        $folder_items, 
                        array
                        (
                            PluginRegularFolderItem::media_url => $v,
                            PluginRegularFolderItem::caption => $user_data->episode_title,
                            PluginRegularFolderItem::view_item_params => array(
                                        ViewItemParams::item_paint_icon => true,
                                        ViewItemParams::icon_path => 'gui_skin://small_icons/movie.aai',
                                        ViewItemParams::item_layout => HALIGN_LEFT,
                                        ViewItemParams::icon_valign => VALIGN_CENTER,
                                        ViewItemParams::icon_dx => 20,
                                        ViewItemParams::icon_dy => -5,
                                        ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                                        ViewItemParams::item_caption_width => 1550
                            ),
                        )
                     );
            
                    ++$total_num;
                }
            }
            return array(
                PluginRegularFolderRange::total => $total_num,
                PluginRegularFolderRange::count => count($folder_items),
                PluginRegularFolderRange::more_items_available => $more_items_available,
                PluginRegularFolderRange::from_ndx => $from_ndx,
                PluginRegularFolderRange::items => $folder_items
            );
        }
        else if (HD::has_attribute($user_data, 'fav'))
        {
            $v = $this->get_next_fav($user_data->fav, $plugin_cookies);
            
            HD::decode_user_data($v[0], $media_str, $user_data);

            $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;
            $video_id = '1';
            $n_series = 1;
            $ndx_series = 0;
            $is_has_new = false;
            $title = "";

            if (HD::has_attribute($user_data, 'video_id'))
                $video_id = $user_data->video_id;

            if (HD::has_attribute($user_data, 'is_has_new'))
                $is_has_new = $user_data->is_has_new;
                
            if (HD::has_attribute($user_data, 'season_title'))
                $title = $user_data->season_title;
                
                    if ($view_type == 1)
                    {
                            array_push(
                                $folder_items, 
                                array(
                                    PluginRegularFolderItem::media_url =>
                                        HD::encode_user_data(
                                            array(
                                                'video_id' => $video_id,
                                                'n_series' => $n_series,
                                                'season_title' => $title
                                            )
                                    ),
                                    PluginRegularFolderItem::caption => ($is_has_new ? '[NEW] ' . $title : $title),
                                    PluginRegularFolderItem::view_item_params => array(
                                        ViewItemParams::icon_path => $this->get_preview_url($user_data),
                                        ViewItemParams::item_paint_caption => false,
                                        ViewItemParams::item_caption_wrap_enabled => TRUE,
            //                            ViewItemParams::item_detailed_info => $info
                                    )
                                ));
                    }
                    else if ($view_type == 2)
                    {
                            array_push(
                                $folder_items, 
                                array(
                                    PluginRegularFolderItem::media_url =>
                                        HD::encode_user_data(
                                            array(
                                                'video_id' => $video_id,
                                                'n_series' => $n_series,
                                            )
                                    ),
                                    PluginRegularFolderItem::caption => ($is_has_new ? '[NEW] ' . $title : $title),
                                    PluginRegularFolderItem::view_item_params => array(
                                        ViewItemParams::icon_path => $this->get_preview_url($user_data),
//                                        ViewItemParams::item_detailed_info => $info
                                    )
                                ));
                    }
                    else
                    {
                        array_push(
                            $folder_items, 
                            array(
                                PluginRegularFolderItem::media_url =>
                                    HD::encode_user_data(
                                        array(
                                            'video_id' => $video_id,
                                            'n_series' => $n_series
                                        )
                                ),
                                PluginRegularFolderItem::caption => ($is_has_new ? '[NEW] ' . $title : $title),
                                PluginRegularFolderItem::view_item_params => array(
                                    ViewItemParams::item_detailed_icon_path => $this->get_preview_url($user_data),
  //                                  ViewItemParams::item_detailed_info => $info
                                )
                            ));
                    }

             $more_items_available = 0;
             $total_num = 1;
             
            return array(
                PluginRegularFolderRange::total => $total_num,
                PluginRegularFolderRange::count => count($folder_items),
                PluginRegularFolderRange::more_items_available => $more_items_available,
                PluginRegularFolderRange::from_ndx => $from_ndx,
                PluginRegularFolderRange::items => $folder_items
            );
        }
        
        $folder = FolderCache::get($media_url);

        if (HD::has_attribute($user_data, 'category_ref'))
        {
            $videos = $folder->get_elements($from_ndx, $plugin_cookies);
            $total_num = 0;
                
            if ($videos === false)
            {
                //////////hd_print("--> get_regular_folder_items(): no more available.");
                $caption = (is_interface_language_russian_or_similar() ? 'Р‘РѕР»СЊС€Рµ РЅРёС‡РµРіРѕ РЅРµ РЅР°Р№РґРµРЅРѕ' : 'No more items found');
                $m_url = 'dummy';
                if (HD::has_attribute($user_data, 'fav'))
                {
                    $caption = (is_interface_language_russian_or_similar() ? 'HРµ РЅР°Р№РґРµРЅРѕ' : 'Not found');
                    $m_url = $media_url;
                }
                $more_items_available = 0;
            

                $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;
                array_push
                (
                                $folder_items,
                                array
                    (
                                    PluginRegularFolderItem::media_url => $m_url,
                                    PluginRegularFolderItem::caption => $caption,
                                    PluginRegularFolderItem::view_item_params => array
                                            (
                                                ViewItemParams::icon_width => ($view_type == 1 ? 90 : ($view_type == 2 ? 150 : 50)),
                                                ViewItemParams::icon_height => ($view_type == 1 ? 90 : ($view_type == 2 ? 200 : 50)),
                                                ViewItemParams::icon_path => ($view_type != 3 ? 'gui_skin://large_icons/movie.aai' : 'gui_skin://small_icons/movie.aai'),
                                                ViewItemParams::item_detailed_icon_path => 'missing://'
                                            )
                    )
                );
                ++$total_num;
            }
            else
            {
                //////////hd_print("--> get_regular_folder_items(): items count: " . count($videos));
                $more_items_available = 1;

                foreach ($videos as $v)
                {
                    HD::decode_user_data($v, $media_str, $user_data);

                    array_push
                    (
                        $folder_items, 
                        array
                        (
                            PluginRegularFolderItem::media_url => $v,
                            PluginRegularFolderItem::caption => $user_data->subcategory_title,
                            PluginRegularFolderItem::view_item_params => self::$catalog_vip
                        )
                     );
            
                    ++$total_num;
                }
            }
            return array(
                PluginRegularFolderRange::total => $total_num,
                PluginRegularFolderRange::count => count($folder_items),
                PluginRegularFolderRange::more_items_available => $more_items_available,
                PluginRegularFolderRange::from_ndx => $from_ndx,
                PluginRegularFolderRange::items => $folder_items
            );
        }
        else if
        (
            HD::has_attribute($user_data, 'subcategory_ref') ||
            HD::has_attribute($user_data, 'ser_feed') ||
            $media_url === 'main_menu:fav'
        )
        {
       	    $videos = $folder->get_elements($from_ndx, $plugin_cookies);
            $total_num = $folder->get_num_elements();
	            
            if ($videos === false)
            {
		        //////////hd_print("--> get_regular_folder_items(): no more available.");
                $caption = (is_interface_language_russian_or_similar() ? 'Р‘РѕР»СЊС€Рµ РЅРёС‡РµРіРѕ РЅРµ РЅР°Р№РґРµРЅРѕ' : 'No more items found');
                $m_url = 'dummy';
                if (HD::has_attribute($user_data, 'fav'))
                {
                    $caption = (is_interface_language_russian_or_similar() ? 'HРµ РЅР°Р№РґРµРЅРѕ' : 'Not found');
                    $m_url = $media_url;
                }
		        $more_items_available = 0;
            
                if ($media_url === 'main_menu:fav')
                {
                    $folder->set_expired();
                }


                $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;
		        array_push
		        (
                    $folder_items,
                    array
			        (
                        	        PluginRegularFolderItem::media_url => $m_url,
	                                PluginRegularFolderItem::caption => $caption,
                	                PluginRegularFolderItem::view_item_params => array
                        	    	        (
                                                ViewItemParams::icon_width => ($view_type == 1 ? 90 : ($view_type == 2 ? 150 : 50)),
                                                ViewItemParams::icon_height => ($view_type == 1 ? 90 : ($view_type == 2 ? 200 : 50)),
                                                ViewItemParams::icon_path => ($view_type != 3 ? 'gui_skin://large_icons/movie.aai' : 'gui_skin://small_icons/movie.aai'),
                		                        ViewItemParams::item_detailed_icon_path => 'missing://'
                        		            )
			        )
		        );
                ++$total_num;
                $this->fav_build_in_progess = false;
            }
            else
            {
	            //////////hd_print("--> get_regular_folder_items(): subcategory_ref: items count: " . count($videos));
                if ($media_url === 'main_menu:fav')
                    $this->fav_build_in_progess = true;
                else
                    $this->fav_build_in_progess = false;

                $more_items_available = 1;

                foreach ($videos as $v)
                {
	                HD::decode_user_data($v, $media_str, $user_data);
		    
		            $genre = ' ';
		            if (HD::has_attribute($user_data, 'genre'))
			            $genre = $user_data->genre;

                    $duration = ' ';
		            if (HD::has_attribute($user_data, 'duration'))
			            $duration = $user_data->duration;

                    if (is_interface_language_russian_or_similar())
                    {
		                $genre_caption = 'Р–Р°РЅСЂ';
                        $duration_caption = 'РџСЂРѕРґРѕР»Р¶РёС‚РµР»СЊРЅРѕСЃС‚СЊ';
                    }
                    else
                    {
		                $genre_caption = 'Genre';
                        $duration_caption = 'Duration';
                    }

                    $info = '';
                    $title = ' ';
                    // Workaround for a bug in Shell.
                    if (HD::has_attribute($user_data, 'season_title'))
                        $title = str_replace('^', '', $user_data->season_title);
                                
                    $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;

                    //$info =
		            //    $genre_caption . ': | ' . $genre . ' || ' .
	                //        $duration_caption . ': | ' . $duration . ' || ';

		            $video_id = '1';
		            $n_series = 1;
		            $ndx_series = 0;
                    $is_has_new = false;

		            if (HD::has_attribute($user_data, 'video_id'))
	                    $video_id = $user_data->video_id;

 		            if (HD::has_attribute($user_data, 'n_series'))
	                    $n_series = $user_data->n_series;

                    if (HD::has_attribute($user_data, 'is_has_new'))
                        $is_has_new = $user_data->is_has_new;

                    if ($view_type == 1)
                    {
                            array_push(
                                $folder_items, 
                                array(
                                    PluginRegularFolderItem::media_url =>
                                        HD::encode_user_data(
                                            array(
				                	            'video_id' => $video_id,
                                                'n_series' => $n_series,
                                                'season_title' => $title
				                            )
			                        ),
                                    PluginRegularFolderItem::caption => ($is_has_new ? '[NEW] ' . $title : $title),
                                    PluginRegularFolderItem::view_item_params => array(
                                        ViewItemParams::icon_path => $this->get_preview_url($user_data),
                                        ViewItemParams::item_paint_caption => false,
                                        ViewItemParams::item_caption_wrap_enabled => TRUE,
            //                            ViewItemParams::item_detailed_info => $info
                                    )
                                ));
                    }
                    else if ($view_type == 2)
                    {
                            array_push(
                                $folder_items, 
                                array(
                                    PluginRegularFolderItem::media_url =>
                                        HD::encode_user_data(
                                            array(
                                                'video_id' => $video_id,
                                                'n_series' => $n_series,
                                            )
                                    ),
                                    PluginRegularFolderItem::caption => ($is_has_new ? '[NEW] ' . $title : $title),
                                    PluginRegularFolderItem::view_item_params => array(
                                        ViewItemParams::icon_path => $this->get_preview_url($user_data),
                                        ViewItemParams::item_detailed_info => $info
                                    )
                                ));
                    }
                    else
                    {
                        array_push(
                            $folder_items, 
                            array(
                                PluginRegularFolderItem::media_url =>
                                    HD::encode_user_data(
                                        array(
                                            'video_id' => $video_id,
                                            'n_series' => $n_series
                                        )
                                ),
                                PluginRegularFolderItem::caption => ($is_has_new ? '[NEW] ' . $title : $title),
                                PluginRegularFolderItem::view_item_params => array(
                                    ViewItemParams::item_detailed_icon_path => $this->get_preview_url($user_data),
                                    ViewItemParams::item_detailed_info => $info
                                )
                            ));
                    }
                }

                array_push(
                    $folder_items, 
                    array(
                        PluginRegularFolderItem::media_url => 'dummy',
                        PluginRegularFolderItem::caption =>
                            (is_interface_language_russian_or_similar() ? 'Р—Р°РіСЂСѓР·РєР°...' : 'Loading...'),
                        PluginRegularFolderItem::view_item_params => array(
                            ViewItemParams::icon_width => ($view_type == 1 ? 190 : ($view_type == 2 ? 150 : 50)),
                            ViewItemParams::icon_height => ($view_type == 1 ? 290 : ($view_type == 2 ? 150 : 50)),
                            ViewItemParams::icon_path => ($view_type != 3 ? 'gui_skin://large_icons/movie.aai' : 'gui_skin://small_icons/movie.aai'),
                            ViewItemParams::item_detailed_icon_path => 'missing://'
                        )
                    ));

                ++$total_num;
            }
        }

        else if (HD::has_attribute($user_data, 'video_id'))
        {
	        $more_items_available = 0;
            $c = VideoCache::get($user_data->video_id);
            HD::decode_user_data($c, $media_str, $user_data);
	        $vod_info = $this->get_vod_info($media_url, &$plugin_cookies);
	    
	        $series = $vod_info[PluginVodInfo::series];

	        $i = 0;
	        foreach($series as $s)
	        {
		        $info = '';
                $isnew = false;
                $video_id = $user_data->video_id;
                if (isset($plugin_cookies->$video_id))
                {
                    $isnew = !ListUtil::is_in_list($plugin_cookies->$video_id, $s[PluginVodSeriesInfo::playback_url]);
                }
                //{ Viewed items
                $is_viewed = false;
                if (!$isnew)
                {
                    $vs = $video_id . ".viewed";
                    if (isset($plugin_cookies->$vs))
                    {
                        $is_viewed = ListUtil::is_in_list($plugin_cookies->$vs, $s[PluginVodSeriesInfo::playback_url]);
                    }
                }
                //} Viewed items
                array_push
                (
                    $folder_items, 
                    array
                    (
                        PluginRegularFolderItem::media_url =>
                            HD::encode_user_data
                            (
                                array
                                (
					                'video_id' => $user_data->video_id,
					                'ndx_series' => $i,
                                    'url_series' => $s[PluginVodSeriesInfo::playback_url]
				                )
  			                ),
                            //{ Viewed items
                            PluginRegularFolderItem::caption => (
                                                                    $isnew ? "[NEW] " . $s[PluginVodSeriesInfo::name]  : ($is_viewed ? "[VIEWED] " . $s[PluginVodSeriesInfo::name] : $s[PluginVodSeriesInfo::name])
                                                                ),
                            //} Viewed items
                            PluginRegularFolderItem::view_item_params => array(
                                    ViewItemParams::icon_path => 'gui_skin://small_icons/movie.aai',
                                    ViewItemParams::item_detailed_info => $info
                            )
                    )
		        );
		        ++$i;
	        }

	        $total_num = $i;
        }
    

        return array(
            PluginRegularFolderRange::total => $total_num,
            PluginRegularFolderRange::count => count($folder_items),
            PluginRegularFolderRange::more_items_available => $more_items_available,
            PluginRegularFolderRange::from_ndx => $from_ndx,
            PluginRegularFolderRange::items => $folder_items
        );
    }

    ///////////////////////////////////////////////////////////////////////
    private function get_episode_url($episode_ref, $video_quality)
    {	////////////////////hd_print("--->>>scilko: $episode_ref");
        $url = $episode_ref;
		return($url);
    }
    
    public function get_vod_info($media_url, &$plugin_cookies)
    {
        //////////hd_print("--> get_vod_info: started...");
 
        if ($media_url === 'dummy')
            return array();

        HD::decode_user_data($media_url, $media_str, $user_data);
        foreach ($user_data as $key => $value)
            //////////hd_print("  $key => $value");

        $season_ref = "";
        if (!HD::has_attribute($user_data, 'season_ref'))
        {
            if (!HD::has_attribute($user_data, 'video_id'))
                throw new Exception('No season_ref or video_id');
            
            $season_ref = $user_data->video_id;
        }
        else
        {
            $season_ref = $user_data->season_ref;
        }

	    $ndx_series = 0;
        if (HD::has_attribute($user_data, 'ndx_series'))
		    $ndx_series = $user_data->ndx_series;
            
        $title = '';
        if (HD::has_attribute($user_data, 'season_title'))
            $title = $user_data->season_title;

        $descr = '';
        $icon_path = '';
            
	    $series = array();
	    
        $series_urls = $this->parse_season($season_ref, $plugin_cookies);
        
        $video_quality = isset($plugin_cookies->video_quality) ? $plugin_cookies->video_quality : 1;

        $i = 0;
	    foreach ($series_urls as $s)
	    {
	        HD::decode_user_data($s, $media_str, $user_data);
            if ($i === 0)
            {
                if (HD::has_attribute($user_data, 'season_title'))
                    $title = $user_data->season_title;

                if (HD::has_attribute($user_data, 'description'))
                    $descr = $user_data->description;
                if (HD::has_attribute($user_data, 'poster_url'))
                    $icon_path = $user_data->poster_url;
            }
            
            //$url = str_replace("http://", "http://mp4://", $user_data->episode_ref . "&season_ref=$season_ref");
		     $url = $user_data->episode_ref . "&season_ref=$season_ref";
		    if (strlen($user_data->episode_ref) > 0)
		    {
			    array_push
			    (
				    $series,
		            array
        		    (
                		PluginVodSeriesInfo::name => $user_data->episode_title,
	                	PluginVodSeriesInfo::playback_url => $url,
		                PluginVodSeriesInfo::playback_url_is_stream_url => false
        		    )
			    );
                ++$i;
		    }
	    }
	
        $buf_time = isset($plugin_cookies->buf_time) ? $plugin_cookies->buf_time : 0;
        //////////hd_print('--->>> Buffer time: ' . $buf_time);

        return array
        (
            PluginVodInfo::name => $title,
            PluginVodInfo::description => $descr,
            PluginVodInfo::poster_url => $icon_path,
            PluginVodInfo::initial_series_ndx => $ndx_series,
            PluginVodInfo::buffering_ms => $buf_time,
            PluginVodInfo::series => $series
        );
    }
     public function get_vod_stream_url($media_url, &$plugin_cookies)
    {
        $video_quality = isset($plugin_cookies->video_quality) ? $plugin_cookies->video_quality : 1;
        $media_url = str_replace("mp4://", "", $media_url);
        
        $media_url = str_replace("&amp;", "&", $media_url);
		$media_url = str_replace("youtube.com/embed/", "youtube.com/watch?v=", $media_url);
		$media_url = str_replace("youtu.be/", "youtube.com/watch?v=", $media_url);
		$media_url = str_replace("youtube.com/watch?feature=player_embedded&v=", "youtube.com/watch?v=", $media_url);
		$media_url = str_replace("www.youtube-nocookie.com/v/", "youtube.com/watch?v=", $media_url);
		$media_url = str_replace("youtube.com/v/", "youtube.com/watch?v=", $media_url);
		$media_url = str_replace("vkontakte.ru", "vk.com", $media_url);
        $tmp = explode('&season_ref=', $media_url);
        $media_url = $tmp[0];
        //hd_print("--->>> media_url = $media_url");
		
		
		if (preg_match("/youtube.com/i",$media_url))
        {
		$tmp = explode("watch?v=", $media_url);
		$url = $tmp[1];
		$media_url = Youtube::retrieve_playback_url($url, $plugin_cookies);
		}
        elseif (preg_match("/vk.com/i",$media_url))
		{ 
			$doc = HD::http_get_document($media_url);
			$tmp = explode("video_no_flv = ", $doc);
			$is_not_flv = intval(strstr($tmp[1], "'", true));
			
			$tmp = explode("var video_host = '", $doc);
			$url = strstr($tmp[1], "'", true);
			
			if (strstr($url, "http://") == false)
				$url = "http://mp4://" . $url . "/";
			else
				$url = str_ireplace("http://", "http://mp4://", $url);
			
			if ($is_not_flv)
			{
				$tmp = explode("var video_uid = '", $doc);
				$url = $url . 'u' . strstr($tmp[1], "'", true) . '/videos/';

				$tmp = explode("var video_vtag = '", $doc);
				$url = $url . strstr($tmp[1], "'", true) . '.';

				$vquality = $this->get_quality_array(false);
				$tmp = explode("var video_max_hd = '", $doc);
				$vq_max = intval(strstr($tmp[1], "'", true));
				
				if ($video_quality > $vq_max)
					$video_quality = $vq_max;
					
				$media_url = $url . $vquality[$video_quality] . '.mp4';
			}
			else
			{
				$url = $url . 'assets/videos/';
				$tmp = explode("vtag=", $doc);
				$url = $url . strstr($tmp[1], "&", true);
				$tmp = explode("vkid=", $doc);
				$url = $url . strstr($tmp[1], "&", true);
				$url = $url . '.vk.flv';
				$media_url = str_ireplace("mp4://", "", $url);
			} 
			
		} 
		elseif (preg_match("/moonwalk.cc/i",$media_url))
			{ 
				preg_match_all('/video\/(.*?)\//', $media_url, $video_token);
				$post_data = 'video_token='. $video_token[1][0];
				$urls = 'http://moonwalk.cc/sessions/create';
				$doc = HD::http_post_document($urls, $post_data);
				preg_match_all('/"manifest_m3u8":"(.*?)"/', $doc, $manifest_m3u8);
				$media_url = $manifest_m3u8[1][0];
			}
		elseif (preg_match("/dailymotion/i",$media_url))
			{
			$media_url = str_replace('http://www.dailymotion.com/video/', 'http://www.dailymotion.com/embed/video/', $media_url);
			$media_url = str_replace('http://www.dailymotion.com/swf/video/', 'http://www.dailymotion.com/embed/video/', $media_url);
			$doc = HD::http_get_document($media_url);
			$tmp = explode('http:\/\/www.dailymotion.com\/cdn\/H264-512x384\/video\/', $doc);
			$media_url = str_remove_spec(strstr($tmp[1], '"', true));
			$media_url = 'http://mp4://www.dailymotion.com/cdn/H264-512x384/video/' . $media_url;
			}
		elseif (preg_match("/rutube/i",$media_url))
			{ 
			if (preg_match("/video\/embed/i",$media_url))
				{
				$media_url = str_replace('http://rutube.ru/tracks/', 'http://rutube.ru/video/embed/', $media_url);
				$doc = HD::http_get_document($media_url);
				preg_match_all('/<link rel="canonical" href="(.*?)"/', $doc, $tmp);
				$media_url = $tmp[1][0];
				}
			elseif (preg_match("/https:\/\/video.rutube.ru/i",$media_url))
				{
				$media_url = str_replace('http://rutube.ru/tracks/', 'http://rutube.ru/video/embed/', $media_url);
				$media_url = str_replace("https://video.rutube.ru/", "http://rutube.ru/video/embed/", $media_url);
				$doc = HD::http_get_document($media_url);
				preg_match_all('/<link rel="canonical" href="(.*?)"/', $doc, $tmp);
				$media_url = $tmp[1][0];
				}
			$url = (str_replace("video", 'api/play/trackinfo', $media_url)). '?format=xml';
			$doc = HD::http_get_document($url);
			preg_match_all('/<m3u8>(.*?)<\/m3u8>/', $doc, $tmp);
			$media_url = $tmp[1][0];
			}
		elseif (preg_match("/yandex/i",$media_url))
			{ 
			$media_url = str_replace("/iframe/", '/lite/', $media_url);
			$tmp = explode("lite/", $media_url);
			$part_url = $tmp[1];
			$token_url = 'http://static.video.yandex.net/get-token/' . $part_url;
			$doc = HD::http_get_document($token_url);
			preg_match_all('/<token>(.*?)<\/token>/', $doc, $tmp);
			$token = $tmp[1][0];
			$stream_url = 'http://streaming.video.yandex.ru/get-location/'.$part_url.'/m450x334.flv?token='. $token .'&ref=static.video.yandex.net';
			$doc = HD::http_get_document($stream_url);
			preg_match_all('/<video-location>(.*?)<\/video-location>/', $doc, $tmp);
			$media_url = str_remove_spec($tmp[1][0]);
			}
		elseif (preg_match("/player.rutv.ru/i",$media_url))
				{ 
				$doc = HD::http_get_document($media_url);
				preg_match_all('/"video":"(.*?)"/', $doc, $tmp);
				$media_url = $tmp[1][0];
				$media_url = str_replace("\\", '', $media_url);
				}
		elseif (preg_match("/play.md/i",$media_url))
		{

			$doc = HD::http_get_document($media_url);
			
			$base_url = '';
			$tmp = explode('base_url: "', $info_block);
			if (count($tmp) > 1)
			$base_url = strstr($tmp[1], '"', true);
			
			$file_name = '';
			$tmp = explode('file_name: "', $info_block);
			if (count($tmp) > 1)
			$file_name = strstr($tmp[1], '"', true);
			
			$resolutions = '';
			$tmp = explode('resolutions: "', $info_block);
			if (count($tmp) > 1)
			$resolutions = strstr($tmp[1], '"', true);
						
			$media_url = $base_url . '/' . $resolutions . '/' . $file_name . '?start=0';
		}
		elseif (preg_match("/UNKNOWN/i",$media_url))
			{ 
			hd_print("--->>> un_media_url = $media_url");
			$url = $media_url;
			$doc = HD::http_get_document($url);
			preg_match_all('/download_url="(.*?)"/', $doc, $tmp);
			$media_url = $tmp[1][0];
			}
		else
			{ 
			$media_url = "http://igores.ru/playlist/images/net1.jpg";		//	$media_url = "http://groo.pp.ua/1.mp4";
			}
			$media_url = str_replace("http:///assets/videos/.vk.flv", "http://igores.ru/playlist/images/net1.jpg", $media_url);	//	$media_url = str_replace("http:///assets/videos/.vk.flv", "http://groo.pp.ua/1.mp4", $media_url);
		if (preg_match("/flv/i",$media_url))
        {
			$media_url = 'http://ts://127.0.0.1:81/cgi-bin/flv.sh?'. $media_url;
		}			
		//hd_print("--->>> media_url = $media_url");
		return $this->get_episode_url($media_url, $video_quality);    
    }

    ///////////////////////////////////////////////////////////////////////

    private function get_loading_items(&$plugin_cookies)
    {
        $view_type = isset($plugin_cookies->view_type) ? $plugin_cookies->view_type : 2;
        $items = array(
                    array
                    (
                        PluginRegularFolderItem::caption =>
                            (is_interface_language_russian_or_similar() ? 'Р—Р°РіСЂСѓР·РєР°...' : 'Loading...'),
                        PluginRegularFolderItem::view_item_params => array
                        (
                            ViewItemParams::item_layout => HALIGN_LEFT,
                            ViewItemParams::item_paint_icon => true,
                            ViewItemParams::item_caption_width => 950,
                            ViewItemParams::item_detailed_icon_path => 'missing://',
                    
                            ViewItemParams::icon_valign => VALIGN_CENTER,
                            ViewItemParams::icon_dx => 10,
                            ViewItemParams::icon_width => ($view_type == 1 ? 90 : ($view_type == 2 ? 150 : 50)),
                            ViewItemParams::icon_height => ($view_type == 1 ? 90 : ($view_type == 2 ? 150 : 50)),
                            ViewItemParams::icon_path => ($view_type != 3 ? 'gui_skin://large_icons/movie.aai' : 'gui_skin://small_icons/movie.aai')
                         ),
                         PluginRegularFolderItem::media_url => 'dummy'
                    )
                );

        return array(
            PluginRegularFolderRange::total => count($items),
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::more_items_available => 1,
            PluginRegularFolderRange::from_ndx => 0,
            PluginRegularFolderRange::items => $items
        );
    }

    ///////////////////////////////////////////////////////////////////////
    private function anidub_main($media_url, &$plugin_cookies)
    {
        set_time_limit(0);
        $api = HOST_API_URL;
        
        // download main video page
        $doc = HD::http_get_document($api);
		
        // get list of categories
		$doc = str_replace('http://anidub.su', '', $doc);
        $tmp = explode('РђРЅРёРјРµ РїРѕ Р¶Р°РЅСЂР°Рј</span></b>', $doc);
        $main_menu_block = strstr($tmp[1], '</ul>', true);
		
        ////hd_print("--->>> main_menu_block: $main_menu_block");
        
        $videos = explode('" href="', $main_menu_block);
        // exclude before first <li
        unset($videos[0]);
		unset($videos[30]);
        $videos = array_values($videos);

        $items = array();
    
         // push 'РџРѕСЃР»РµРґРЅРёРµ РїРѕСЃС‚СѓРїР»РµРЅРёСЏ' category in to categories list
        array_push
        (
            $items,
            array
            (
                PluginRegularFolderItem::caption => 'Р’СЃРµ Р°РЅРёРјРµ РїРѕ РґРѕР±Р°РІР»РµРЅРёСЋ',
                PluginRegularFolderItem::view_item_params => self::$catalog_vip,
                PluginRegularFolderItem::media_url =>
                        HD::encode_user_data
                        (
                            array
                            (
                                'subcategory_ref' => '',
                            )
                        )
            )
        ); 

        //////////hd_print('Before parsing: Categories count: ' . count($videos) + 1);

        // iterate through categories
        foreach($videos as $video)
        {
            // get category view reference
            $category_ref = strstr($video, '"', true);
            ////////hd_print("--->>> category_ref: $category_ref");

            // get category title 
            $tmp = explode('">', $video);
            $category_name = str_remove_spec(strstr($tmp[1], '</a>', true));
			$category_name = str_replace('-- ', '', $category_name);
			$category_name = str_replace('- ', '', $category_name);
			
			////////hd_print("--->>> category_name: $category_name");
        
            // push category in to categories list
            array_push
            (
                $items,
                array
                (
                    PluginRegularFolderItem::caption => $category_name,
                    PluginRegularFolderItem::view_item_params => self::$catalog_vip,
                    PluginRegularFolderItem::media_url =>
                            HD::encode_user_data
                            (
                                array
                                (
                                    'subcategory_ref' => $category_ref,
                                )
                            )
                )
            );
        }

        //////////hd_print('After parsing: Categories count: ' . count($items));
        
        return $items;
    }
    ///////////////////////////////////////////////////////////////////////
	private function anidub_main_year($media_url, &$plugin_cookies)
    {
        set_time_limit(0);
        $api = HOST_API_URL;
        
        // download main video page
        $doc = HD::http_get_document($api);
		
        // get list of categories
		$doc = str_replace('http://anidub.su', '', $doc);
        $tmp = explode('РђРЅРёРјРµ РїРѕ РіРѕРґР°Рј</span></b>', $doc);
        $main_menu_block = strstr($tmp[1], '</ul>', true);
		
        ////hd_print("--->>> main_menu_block: $main_menu_block");
        
        $videos = explode('" href="', $main_menu_block);
        // exclude before first <li
        unset($videos[0]);
        $videos = array_values($videos);

        $items = array();
    
         // push 'РџРѕСЃР»РµРґРЅРёРµ РїРѕСЃС‚СѓРїР»РµРЅРёСЏ' category in to categories list

        // iterate through categories
        foreach($videos as $video)
        {
            // get category view reference
            $category_ref = strstr($video, '"', true);
            ////////hd_print("--->>> category_ref: $category_ref");

            // get category title 
            $tmp = explode('">', $video);
            $category_name = str_remove_spec(strstr($tmp[1], '</a>', true));
			$category_name = str_replace('-- ', '', $category_name);
			$category_name = str_replace('- ', '', $category_name);
			
			////////hd_print("--->>> category_name: $category_name");
        
            // push category in to categories list
            array_push
            (
                $items,
                array
                (
                    PluginRegularFolderItem::caption => $category_name,
                    PluginRegularFolderItem::view_item_params => self::$catalog_vip,
                    PluginRegularFolderItem::media_url =>
                            HD::encode_user_data
                            (
                                array
                                (
                                    'subcategory_ref' => $category_ref,
                                )
                            )
                )
            );
        }

        //////////hd_print('After parsing: Categories count: ' . count($items));
        
        return $items;
    }
	///////////////////////////////////////////////////////////////////////
		private function anidub_main_studii($media_url, &$plugin_cookies)
    {
        set_time_limit(0);
        $api = HOST_API_URL;
        
        // download main video page
        $doc = HD::http_get_document($api);
		;
        // get list of categories
		$doc = str_replace('http://anidub.su', '', $doc);
        $tmp = explode('РђРЅРёРјРµ РїРѕ РґР°Р±РµСЂР°Рј</span></b>', $doc);
        $main_menu_block = strstr($tmp[1], '</ul>', true);
		
        ////hd_print("--->>> main_menu_block: $main_menu_block");
        
        $videos = explode('" href="', $main_menu_block);
        // exclude before first <li
        unset($videos[0]);
		unset($videos[3]);
		unset($videos[7]);
        $videos = array_values($videos);

        $items = array();
    
         // push 'РџРѕСЃР»РµРґРЅРёРµ РїРѕСЃС‚СѓРїР»РµРЅРёСЏ' category in to categories list

        // iterate through categories
        foreach($videos as $video)
        {
            // get category view reference
            $category_ref = strstr($video, '"', true);
            ////////hd_print("--->>> category_ref: $category_ref");

            // get category title 
            $tmp = explode('">', $video);
            $category_name = str_remove_spec(strstr($tmp[1], '</a>', true));
			$category_name = str_replace('-- ', '', $category_name);
			$category_name = str_replace('- ', '', $category_name);
			
			////////hd_print("--->>> category_name: $category_name");
        
            // push category in to categories list
            array_push
            (
                $items,
                array
                (
                    PluginRegularFolderItem::caption => $category_name,
                    PluginRegularFolderItem::view_item_params => self::$catalog_vip,
                    PluginRegularFolderItem::media_url =>
                            HD::encode_user_data
                            (
                                array
                                (
                                    'subcategory_ref' => $category_ref,
                                )
                            )
                )
            );
        }

        //////////hd_print('After parsing: Categories count: ' . count($items));
        
        return $items;
    }
	
	///////////////////////////////////////////////////////////////////////

    private function get_main_menu_items($media_url, &$plugin_cookies)
    {
        set_time_limit(0);

        HD::decode_user_data($media_url,$url,$user_data);
        if (update_interface_language())
            FolderCache::clear();

        $skin = isset($plugin_cookies->skin) ? $plugin_cookies->skin : 'default';
	    if ($media_url === 'main_menu')
	    {
		    $items = array
		    (
			    array
			    (
				    PluginRegularFolderItem::caption => 'РђРЅРёРјРµ РїРѕ Р¶Р°РЅСЂР°Рј',
				    PluginRegularFolderItem::view_item_params => array(
				            ViewItemParams::icon_path => 'plugin_file://skins/default/icons/categories.png',
							ViewItemParams::icon_sel_path => 'plugin_file://skins/default/icons/categories_sel.png',
				            ViewItemParams::item_layout => HALIGN_CENTER,
				            ViewItemParams::icon_valign => VALIGN_CENTER,
					        ViewItemParams::item_paint_caption_within_icon => false,
                            ViewItemParams::item_paint_caption => false,
				            ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
				    ),
				    PluginRegularFolderItem::media_url => 'main_menu:videos'
                ),
				array
			    (
				    PluginRegularFolderItem::caption => 'РђРЅРёРјРµ РїРѕ РіРѕРґР°Рј',
				    PluginRegularFolderItem::view_item_params => array(
				            ViewItemParams::icon_path => 'plugin_file://skins/default/icons/categories2.png',
							ViewItemParams::icon_sel_path => 'plugin_file://skins/default/icons/categories2_sel.png',
				            ViewItemParams::item_layout => HALIGN_CENTER,
				            ViewItemParams::icon_valign => VALIGN_CENTER,
					        ViewItemParams::item_paint_caption_within_icon => false,
                            ViewItemParams::item_paint_caption => false,
				            ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
				    ),
				    PluginRegularFolderItem::media_url => 'main_menu:year'
                ),
				array
			    (
				    PluginRegularFolderItem::caption => 'РђРЅРёРјРµ РїРѕ РґР°Р±РµСЂР°Рј',
				    PluginRegularFolderItem::view_item_params => array(
				            ViewItemParams::icon_path => 'plugin_file://skins/default/icons/categories1.png',
							ViewItemParams::icon_sel_path => 'plugin_file://skins/default/icons/categories1_sel.png',
				            ViewItemParams::item_layout => HALIGN_CENTER,
				            ViewItemParams::icon_valign => VALIGN_CENTER,
					        ViewItemParams::item_paint_caption_within_icon => false,
                            ViewItemParams::item_paint_caption => false,
				            ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
				    ),
				    PluginRegularFolderItem::media_url => 'main_menu:studii'
                ),
				
			    array
			    (
				    PluginRegularFolderItem::caption => 'РџРѕРёСЃРє',
				    PluginRegularFolderItem::view_item_params => array(
				            ViewItemParams::icon_path => 'plugin_file://skins/default/icons/search.png',
							ViewItemParams::icon_sel_path => 'plugin_file://skins/default/icons/search_sel.png',
				            ViewItemParams::item_layout => HALIGN_CENTER,
				            ViewItemParams::icon_valign => VALIGN_CENTER,
					        ViewItemParams::item_paint_caption_within_icon => false,
                            ViewItemParams::item_paint_caption => false,
				            ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
				    ),
				    PluginRegularFolderItem::media_url => 'main_menu:search'
                ),
			    array
			    (
				    PluginRegularFolderItem::caption => 'Р�Р·Р±СЂР°РЅРЅРѕРµ',
				    PluginRegularFolderItem::view_item_params => array(
				            ViewItemParams::icon_path => 'plugin_file://skins/default/icons/favorites.png',
							ViewItemParams::icon_sel_path => 'plugin_file://skins/default/icons/favorites_sel.png',
				            ViewItemParams::item_layout => HALIGN_CENTER,
				            ViewItemParams::icon_valign => VALIGN_CENTER,
					        ViewItemParams::item_paint_caption_within_icon => false,
                            ViewItemParams::item_paint_caption => false,
				            ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
				    ),
				    PluginRegularFolderItem::media_url => 'main_menu:fav'
                ),
				array
			    (
				    PluginRegularFolderItem::caption => 'РќР°СЃС‚СЂРѕР№РєРё',
				    PluginRegularFolderItem::view_item_params => array(
				            ViewItemParams::icon_path => 'plugin_file://skins/default/icons/setup.png',
							ViewItemParams::icon_sel_path => 'plugin_file://skins/default/icons/setup_sel.png',
				            ViewItemParams::item_layout => HALIGN_CENTER,
				            ViewItemParams::icon_valign => VALIGN_CENTER,
					        ViewItemParams::item_paint_caption_within_icon => false,
                            ViewItemParams::item_paint_caption => false,
				            ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
				    ),
				    PluginRegularFolderItem::media_url => 'setup'
                ),	
		    );
	    }
        else if ($media_url === 'main_menu:search')
	    {
		    $items = array();
            array_push
            (
                $items,
                array
                (
                    PluginRegularFolderItem::caption => '[РќРѕРІС‹Р№ Р·Р°РїСЂРѕСЃ]',
                    PluginRegularFolderItem::view_item_params => self::$catalog_vip,
                    PluginRegularFolderItem::media_url =>
                        HD::encode_user_data
                        (
                            array('ser_feed' => '')
                        )
                )
            );

		    $search_items = isset($plugin_cookies->search_items) ? $plugin_cookies->search_items : '';
		    foreach (ListUtil::string_to_list($search_items) as $item)
		    {
			    if ($item !== "")
			    {
				    array_push
				    (
					    $items,
					    array
					    (
						    PluginRegularFolderItem::caption => (is_interface_language_russian_or_similar() ? "РїРѕРёСЃРє: $item" : "search: $item"),
						    PluginRegularFolderItem::view_item_params => self::$catalog_vip,
						    PluginRegularFolderItem::media_url =>
							    HD::encode_user_data
							    (
								 array('ser_feed' => urlencode($item))
							    )
                        )
                    );
                }
            }
        }
        else if ($media_url === 'main_menu:fav')
	    {
		    $items = array();
		    $fav_items = isset($plugin_cookies->fav_items) ? $plugin_cookies->fav_items : '';
		    foreach (ListUtil::string_to_list($fav_items) as $item)
		    {
			    if ($item !== "")
			    {
				    $folderRange = $this->get_regular_folder_items(
							    HD::encode_user_data(array('fav' => $item)),
							    0,
							    $plugin_cookies
					   	    );

				    if (!$folderRange)
					    continue;
				    if ($folderRange[PluginRegularFolderRange::count] < 1)
					    continue;

				    array_push
				    (
					    $items,
					    $folderRange[PluginRegularFolderRange::items][0]
                    );
                }
            }
        }
	    elseif ($media_url === 'main_menu:videos')
	    {
            $items = $this->anidub_main($media_url, $plugin_cookies);
	    }
		elseif ($media_url === 'main_menu:year')
	    {
            $items = $this->anidub_main_year($media_url, $plugin_cookies);
	    }
		elseif ($media_url === 'main_menu:studii')
	    {
            $items = $this->anidub_main_studii($media_url, $plugin_cookies);
	    }
		
	    return 	array
	    (
		        PluginRegularFolderRange::total => count($items),
		        PluginRegularFolderRange::count => count($items),
	            PluginRegularFolderRange::more_items_available => false,
		        PluginRegularFolderRange::from_ndx => 0,
		        PluginRegularFolderRange::items => $items
	    );
	}
}

///////////////////////////////////////////////////////////////////////////

DefaultDunePluginFw::$plugin_class_name = 'anidubPlugin';

///////////////////////////////////////////////////////////////////////////
?>