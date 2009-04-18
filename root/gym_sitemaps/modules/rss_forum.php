<?php
/**
*
* @package phpBB SEO GYM Sitemaps
* @version $Id: rss_forum.php 2007/04/12 13:48:48 dcz Exp $
* @copyright (c) 2008 dcz - www.phpbb-seo.com
* @license http://opensource.org/osi3.0/licenses/lgpl-license.php GNU Lesser General Public License
*
*/
// First basic security
if ( !defined('IN_PHPBB') ) {
	exit;
}
/**
* google_forum Class
* www.phpBB-SEO.com
* @package phpBB SEO
*/
class rss_forum {
	var $url_settings = array();
	var $options = array();
	var $module_config = array();
	var $outputs = array();
	var $forum_cache = array();
	/**
	* constuctor
	*/
	function rss_forum(&$gym_master) {
		global $user;
		$this->gym_master = &$gym_master;
		$this->options = &$this->gym_master->actions;
		$this->outputs = &$this->gym_master->output_data;
		$this->module_config = &$this->gym_master->rss_config;
		$this->url_settings = &$this->gym_master->url_config;
		$this->module_config = array_merge(
			// Global
			$this->module_config,
			// Other stuff required here
			array(
				'rss_first' => ( $this->gym_master->gym_config['rss_forum_first'] ) ? TRUE : FALSE,
				'rss_last' => ( $this->gym_master->gym_config['rss_forum_last']  ) ? TRUE : FALSE,
				'rss_forum_rules' => ( $this->gym_master->gym_config['rss_forum_rules']  ) ? TRUE : FALSE,
				'rss_exclude_list' => trim($this->gym_master->gym_config['rss_forum_exclude'], ','),
			)
		);
		// Set up msg outpout
		if ($this->options['rss_news_list'] || $this->options['module_sub'] === 'announces' ) {
			$this->module_config['rss_first'] = true;
			$this->module_config['rss_last'] = false;
		} else {
			$this->module_config['rss_last'] = ($this->module_config['rss_first']) ? $this->module_config['rss_last'] : true;
		}
		// Build unauthed array
		$this->module_config['exclude_list'] = $this->gym_master->set_exclude_list($this->module_config['rss_exclude_list']);
		// Wee need to check auth here
		$this->module_config['exclude_list'] = $this->gym_master->check_forum_auth($this->module_config['exclude_list'], $this->module_config['rss_auth_guest']);
		$this->options['not_in_id_sql'] = $this->gym_master->set_not_in_list($this->module_config['exclude_list']);
		$this->gym_master->gym_output->setup_cache(); // Will exit if the cache is sent

		$this->init_url_settings();
	}
	/**
	* Initialize mod rewrite to handle multiple URL standards.
	* Only one 'if' is required after this in THE loop to properly switch 
	* between the four types (none, advanced, mixed and simple).
	* @access private
	*/
	function init_url_settings() {
		global $phpbb_seo, $phpEx;
		// vars will fell like rain in the code ;)
		$this->url_settings['forum_pre'] = "viewforum.$phpEx?f=";
		$this->url_settings['topic_pre'] = 'viewtopic.' . $phpEx . '?f=%1$d&amp;t=%2$d';
		$this->url_settings['forum_ext'] = '';
		$this->url_settings['topic_ext'] = '';
		$this->url_settings['start_delim'] = $this->url_settings['start_default'];
		$this->url_settings['rss_forum_pre'] = $this->url_settings['rss_default'] . '?forum=';
		$this->url_settings['rss_forum_default'] = $this->url_settings['rss_default'] . '?forum';
		$this->url_settings['rss_forum_news_default'] = $this->url_settings['rss_forum_default'] . '&amp;news';
		$this->url_settings['rss_forum_news'] = '&amp;news';
		$this->url_settings['rss_forum_channel_default'] = $this->url_settings['rss_forum_pre'] . 'channels';
		$this->url_settings['rss_forum_channel'] = '';
		$this->url_settings['rss_forum_announces_default'] = $this->url_settings['rss_forum_pre'] . 'announces';
		$this->url_settings['rss_forum_announces'] = '';
		$this->url_settings['rss_forum_file'] = '';
		// Mod rewrite type auto detection
		$this->url_settings['modrtype'] = ($phpbb_seo->modrtype >= 0) ? intval($phpbb_seo->modrtype) : intval($this->gym_master->gym_config['rss_modrtype']);
		// make sure virtual_folder uses the proper value
		$phpbb_seo->seo_opt['virtual_folder'] = $this->url_settings['modrtype'] > 0 ? $phpbb_seo->seo_opt['virtual_folder'] : false;
		$this->url_settings['rss_forum_delim'] = !empty($phpbb_seo->seo_delim['forum']) ? $phpbb_seo->seo_delim['forum'] : '-f';
		$this->url_settings['rss_forum_static'] = 'forum';
		$this->url_settings['modrewrite'] = $this->gym_master->gym_config['rss_modrewrite'];
		if ($this->module_config['rss_modrewrite']) { // Module links
			$this->url_settings['rss_forum_pre'] = ($this->url_settings['modrtype'] >= 2) ? '' : $this->url_settings['rss_forum_static'] . $this->url_settings['rss_forum_delim'];
			$this->url_settings['rss_forum_file'] = 'forum.xml' . $this->url_settings['gzip_ext_out'];
			$this->url_settings['rss_forum_default'] = '';
			$this->url_settings['rss_forum_announces_default'] = $this->url_settings['rss_forum_news_default'] = $this->url_settings['rss_forum_channel_default'] = '';
			$this->url_settings['rss_forum_news'] = 'news/';
			$this->url_settings['rss_forum_channel'] =  'channels/';
			$this->url_settings['rss_forum_announces'] = 'announces/';
		}
		if (!@isset($phpbb_seo->seo_opt['url_rewrite'])) {
			$phpbb_seo->seo_opt['url_rewrite'] = $this->url_settings['modrtype'] > 0 ? true : false;
		}
		if ($phpbb_seo->seo_opt['url_rewrite']) {
			$this->url_settings['forum_index'] = !empty($phpbb_seo->seo_static['index']) ? $phpbb_seo->seo_static['index'] . $phpbb_seo->seo_ext['index'] : '';
			if ($this->url_settings['modrtype'] >= 1) { // Simple mod rewrite, default is none (0)
				$this->url_settings['start_delim'] = $phpbb_seo->seo_delim['start'];
				$this->url_settings['forum_pre'] = $phpbb_seo->seo_static['forum'];
				$this->url_settings['topic_pre'] = $phpbb_seo->seo_static['topic'] . '%2$d';
				$this->url_settings['forum_ext'] = $phpbb_seo->seo_ext['forum'];
				$this->url_settings['topic_ext'] = $phpbb_seo->seo_ext['topic'];
			}
			if ($this->url_settings['modrtype'] >= 2) { // +Mixed
				$this->url_settings['forum_pre'] = '';
			} 
			if ($this->url_settings['modrtype'] >= 3) { // +Advanced
				$this->url_settings['topic_pre'] = '';
			}
		} else {
			$this->url_settings['forum_index'] = 'index.php';
			$phpbb_seo->seo_opt['virtual_folder'] = false;
		}
		return;
	}
	/**
	* rss_main() 
	* Add content to the main listing (channel list and rss feed)
	* @access private
	*/
	function rss_main() {
		global $config, $db, $phpbb_seo, $user, $phpEx;
		// It's global channel list call, add static channels
		// Reset the local counting, since we are cycling through modules
		$this->outputs['url_sofar'] = 0;
		if ( $this->options['rss_channel_list'] ) { // Channel lists
			// Add the forum channel
			$chan_source = $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_channel_default'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_channel'] . $this->url_settings['rss_forum_file'];
			$chan_link = $phpbb_seo->seo_path['phpbb_urlR'] . $this->url_settings['forum_index'];
			$item_tile = !empty($this->gym_master->gym_config['rss_forum_sitename']) ? $this->gym_master->gym_config['rss_forum_sitename'] : $config['sitename'];
			$item_desc = (!empty($this->gym_master->gym_config['rss_forum_site_desc']) ? $this->gym_master->gym_config['rss_forum_site_desc'] : $config['site_desc']) . "\n\n";
			$this->gym_master->parse_item($item_tile . ' - ' . $user->lang['RSS_CHAN_LIST_TITLE'], $item_desc . $user->lang['RSS_CHAN_LIST_DESC'], $chan_link, $chan_source, $item_tile .  ' - ' . $user->lang['RSS_CHAN_LIST_TITLE'], $this->outputs['last_mod_time']);
			// add the main news forum feed
			if ($this->module_config['rss_allow_news']) {
				$chan_source = $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_default'] . ($this->options['rss_news_list'] ? '' : $this->url_settings['rss_forum_news']) . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
				$this->gym_master->parse_item($item_tile . ' - ' . $user->lang['RSS_NEWS'], $user->lang['RSS_NEWS_DESC'] . ' ' . $item_tile . "\n\n" . $this->module_config['rss_site_desc'], $chan_link, $chan_source, $item_tile . ' - ' . $user->lang['RSS_NEWS'], $this->outputs['last_mod_time']);
			}
			// Add announces feed to the list
			$forum_announces_url =  $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_announces_default'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_announces'] . $this->url_settings['rss_forum_file'];
			$this->gym_master->parse_item(sprintf($user->lang['RSS_ANNOUNCES_TITLE'], $this->module_config['rss_sitename']), sprintf($user->lang['RSS_ANNOUCES_DESC'], $this->module_config['rss_sitename']) . "\n\n" . $this->module_config['rss_site_desc'], $chan_link, $forum_announces_url, '', $this->outputs['last_mod_time']);
			// add the main forum feed
			$chan_source = $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_default'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];

			// Forum stats
			$forum_stats = '<b>' . $user->lang['STATISTICS'] . '</b> : ' . sprintf($user->lang['TOTAL_USERS_OTHER'], $config['num_users']) . ' || ' . "\n";
			$forum_stats .= sprintf($user->lang['TOTAL_TOPICS_OTHER'], $config['num_topics']) . ' || ';
			$forum_stats .= sprintf($user->lang['TOTAL_POSTS_OTHER'], $config['num_posts']) . "\n";
			$forum_stats .= ($this->module_config['rss_allow_profile'] ? "\n" . sprintf($user->lang['NEWEST_USER'], $this->gym_master->username_string($config['newest_user_id'], $config['newest_username'], $config['newest_user_colour'], $this->module_config['rss_allow_profile_links']) ) : '') . "\n\n";
			$item_desc .= $forum_stats;
			$this->gym_master->parse_item($item_tile, $item_desc, $chan_link, $chan_source, $item_tile, $this->outputs['last_mod_time']);
			// Grabb the forum data
			$this->list_forums();
		} else { // Main feeds
			// Grabb forums info
			$forum_data = array();
			$sql = "SELECT * 
				FROM " . FORUMS_TABLE . "
					WHERE forum_id " . $this->options['not_in_id_sql'] . "
				ORDER BY forum_last_post_id " . $this->module_config['rss_sort'];
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result)) {
				$forum_data[$row['forum_id']] = $row;
			}
			$db->sql_freeresult($result);
			unset($row);
			// Build sql components
			$topic_forum_sql = '';
			if ($this->module_config['rss_limit_time'] > 0 ) {
				$time_limit = ($this->outputs['time'] - $this->module_config['rss_limit_time']);
				$time_limit_sql = "topic_last_post_time > $time_limit AND ";
			} else {
				$time_limit_sql = '';
			}
			// Count topics
			$sql = "SELECT COUNT(topic_id) AS topic
				FROM " . TOPICS_TABLE . "
				WHERE forum_id " . $this->options['not_in_id_sql'] . "
					AND $time_limit_sql
					topic_status <> " . ITEM_MOVED . "
					AND topic_type <> " . POST_GLOBAL . "
					AND topic_reported = 0
				ORDER BY topic_last_post_id " . $this->module_config['rss_sort'];
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$forum_data['topic_count'] = ( $row['topic'] ) ? $row['topic'] : 1;
			$db->sql_freeresult($result);
			unset($row);
			$forum_sql = !empty($this->options['not_in_id_sql']) ? 't.forum_id ' . $this->options['not_in_id_sql'] . ' AND ' : '';
			$this->list_topics($forum_sql, $forum_data);
		}
		// Add the local counting, since we are cycling through modules
		$this->outputs['url_sofar_total'] = $this->outputs['url_sofar_total'] + $this->outputs['url_sofar'];
		return;
	}
	/**
	* rss_module() will build the module's specific sub feeds,
	* @access private
	*/
	function rss_module() {
		global $user, $db, $phpbb_seo, $auth, $config;
		$forum_sql = '';
		$time_limit = $approve_sql = '';
		$forum_data = array('topic_count' => 0);
		if ($this->options['module_sub'] === 'channels') { // Module channel list
			//If so check for dupes and build channel header
			$chan_source = $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_channel_default'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_channel'] . $this->url_settings['rss_forum_file'];
			// Kill dupes
			$this->gym_master->seo_kill_dupes($chan_source);
			$chan_title = $this->module_config['rss_sitename'];
			$chan_link = $phpbb_seo->seo_path['phpbb_urlR'] . $this->url_settings['forum_index'];
			$chan_desc = sprintf($user->lang['RSS_CHAN_LIST_DESC_MODULE'], $this->module_config['rss_sitename']) . "\n\n" . $this->module_config['rss_site_desc'] . "\n\n";

			// Forum stats
			$site_stats = '<b>' . $user->lang['STATISTICS'] . '</b> : ' . sprintf($user->lang['TOTAL_USERS_OTHER'], $config['num_users']) . ' || ';
			$site_stats .= sprintf($user->lang['TOTAL_TOPICS_OTHER'], $config['num_topics']) . ' || ';
			$site_stats .= sprintf($user->lang['TOTAL_POSTS_OTHER'], $config['num_posts']);

			$site_stats .= ($this->module_config['rss_allow_profile'] ? "\n" . sprintf($user->lang['NEWEST_USER'], $this->gym_master->username_string($config['newest_user_id'], $config['newest_username'], $config['newest_user_colour'], $this->module_config['rss_allow_profile_links']) ) : '') . "\n";
			$chan_title_full = $chan_title . ' ' . $user->lang['RSS_CHAN_LIST_TITLE'];
			
			$this->gym_master->parse_channel($chan_title_full, $chan_desc, $chan_link,  $this->outputs['last_mod_time'], $this->module_config['rss_image_url'], $chan_source);
			// Add main forum feed to the list only when not requesting a news channel list
			if (!$this->options['rss_news_list']) {
				$forum_feed_url = $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_default'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
				$this->gym_master->parse_item($chan_title, $this->module_config['rss_site_desc'] . "\n\n" . $site_stats, $chan_link, $forum_feed_url, '', $this->outputs['last_mod_time']);
			}
			// add the main news forum feed
			if ($this->module_config['rss_allow_news']) {
				$news_chan = $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_default'] . ($this->options['rss_news_list'] ? '' : $this->url_settings['rss_forum_news']) . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
				$this->gym_master->parse_item($chan_title . ' - ' . $user->lang['RSS_NEWS'], $user->lang['RSS_NEWS_DESC'] . ' ' . $chan_title  . "\n\n" . $this->module_config['rss_site_desc'], $chan_link, $news_chan, $chan_title . ' - ' . $user->lang['RSS_NEWS'], $this->outputs['last_mod_time']);
			}
			// Add announces feed to the list
			$forum_announces_url =  $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_announces_default'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_announces'] . $this->url_settings['rss_forum_file'];
			$this->gym_master->parse_item(sprintf($user->lang['RSS_ANNOUNCES_TITLE'], $this->module_config['rss_sitename']), sprintf($user->lang['RSS_ANNOUCES_DESC'], $this->module_config['rss_sitename']) . "\n\n" . $this->module_config['rss_site_desc'], $chan_link, $forum_announces_url, '', $this->outputs['last_mod_time']);
			$this->list_forums();
			return;

		} elseif ($this->options['module_sub'] === 'announces') { // Global annnounces list

			// it's the announces sitemap
			$chan_source = $this->module_config['rss_url'] . $this->url_settings['rss_vpath'] . $this->url_settings['rss_forum_announces_default'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_announces'] . $this->url_settings['rss_forum_file'];
			// Kill dupes
			$this->gym_master->seo_kill_dupes($chan_source);
			$chan_title = sprintf($user->lang['RSS_ANNOUNCES_TITLE'], $this->module_config['rss_sitename']);
			$chan_link = $phpbb_seo->seo_path['phpbb_urlR'] . $this->url_settings['forum_index'];
			$chan_desc =  sprintf($user->lang['RSS_ANNOUCES_DESC'], $this->module_config['rss_sitename']) . "\n\n" . $this->module_config['rss_site_desc'];
			// Forum announces location
			$this->gym_master->parse_channel($chan_title . $this->module_config['extra_title'], $chan_desc . "\n", $chan_link,  $this->outputs['last_mod_time'], $this->module_config['rss_image_url'], $chan_source);
			// We want to list all the global announces from the forum
			$forum_sql = $this->gym_master->set_not_in_list($this->module_config['exclude_list'], '', 'AND');
			$forum_sql1 = !empty($forum_sql) ? 'forum_id ' . $forum_sql : '';
			$forum_sql2 = !empty($forum_sql) ? 't.forum_id ' . $forum_sql : '';
			// Count items
			$sql = "SELECT COUNT(topic_id) AS topic
				FROM " . TOPICS_TABLE . " AS t
				WHERE $forum_sql1
					topic_type = " . POST_GLOBAL . " 
					AND topic_status <> " . ITEM_MOVED . "
					AND topic_reported = 0
				ORDER BY t.topic_last_post_id " . $this->module_config['rss_sort'];
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			$forum_data['topic_count'] = ( $row['topic'] ) ? $row['topic'] : 1;
			unset($row);
			// Dirty but efficient workarround for annouces
			$this->forum_cache[0]['forum_url'] = $phpbb_seo->seo_path['phpbb_urlR'] . ($phpbb_seo->seo_opt['virtual_folder'] ? $phpbb_seo->seo_static['global_announce'] . $phpbb_seo->seo_ext['global_announce'] : '' );
			$this->forum_cache[0]['forum_url_full'] = $this->forum_cache[0]['forum_name'] = $chan_title;
			$this->forum_cache[0]['replies_key'] = 'topic_replies';
			$this->forum_cache[0]['approve'] = 0;
			$this->forum_cache[0]['forum_rss_url'] = $chan_source;
			$this->list_topics($forum_sql2, $forum_data, 0, '', POST_GLOBAL);

		} else { // Module feeds

			// Filter $this->options['module_sub'] var type
			$this->options['module_sub'] = intval($this->options['module_sub']);
			if ($this->options['module_sub'] > 0) { // Forum Feed
				$forum_sql = ' t.forum_id = ' . $this->options['module_sub'] . ' AND ';
				// Check forum auth and grab necessary infos			
				$sql = "SELECT * 
					FROM ". FORUMS_TABLE ." AS f
					WHERE forum_id = " . $this->options['module_sub'];
					$result = $db->sql_query($sql);
					$forum_data = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);
					if ( empty($forum_data) ) {
						$this->gym_master->gym_error(404, '', __FILE__, __LINE__, $sql);
					}
					$forum_id = (int) $forum_data['forum_id'];
					if ( $forum_data['forum_type'] !=  FORUM_POST || in_array($forum_id, $this->module_config['exclude_list']) ) {
						$this->gym_master->gym_error(401, '', __FILE__, __LINE__);
					}
					// This forum is allowed, so let's start
					$this->forum_cache[$forum_id]['forum_name_ok'] =  $phpbb_seo->format_url($forum_data['forum_name'], $phpbb_seo->seo_static['forum']);
					// Build the forum's RSS feed's URL
					// Do it in two steps to allow yahoo Notifications
					$forum_rss_url =   $this->module_config['rss_url'] .  ( !empty($this->url_settings['rss_forum_pre']) ? $this->url_settings['rss_forum_pre'] . $forum_id : $this->gym_master->forum_rss_url( $forum_data['forum_name'] , $forum_id, 'rss_forum_delim') . '/');
					// Build Yahoo notify URL
					// If the URL is not rewritten, we cannot use "&", get rid of options in such cases.
					if ($this->module_config['rss_yahoo_notify']) {
						if ( $this->url_settings['modrewrite'] ) {
							$this->module_config['rss_yahoo_notify_url'] = $forum_rss_url . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
						} else {
							$this->module_config['rss_yahoo_notify_url'] = $forum_rss_url . $this->url_settings['rss_forum_file'];
						}
					}
					$forum_rss_url .= $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
					// Kill dupes
					$this->gym_master->seo_kill_dupes($forum_rss_url);

					// Properly set the limits
					$this->forum_cache[$forum_id]['approve'] = $auth->acl_get('m_approve', $forum_id);
					if ($this->forum_cache[$forum_id]['approve']) {
						$forum_data['topic_count'] =  $forum_data['forum_topics_real'];
						$this->forum_cache[$forum_id]['replies_key'] = 'topic_replies_real';
						$approve_sql = ' AND topic_approved = 1';
					} else {
						$forum_data['topic_count'] =  $forum_data['forum_topics'];
						$this->forum_cache[$forum_id]['replies_key'] = 'topic_replies';
						$approve_sql = '';
					}
					// In case the forum called for a feed is really big, apply time limit
					if ( $this->module_config['rss_limit_time'] > 0 && $forum_data['topic_count'] > 500) {
						$time_limit = ($this->outputs['time'] - $this->module_config['rss_limit_time']);
						// So let's count topic in this forum
						$sql = "SELECT COUNT(topic_id) AS forum_topics
							FROM " . TOPICS_TABLE . "
							WHERE forum_id = $forum_id
							AND topic_last_post_time > $time_limit
								AND topic_status <> " . ITEM_MOVED . " 
								AND topic_type <> " . POST_GLOBAL . " 
								$approve_sql
								AND topic_reported = 0
							ORDER BY topic_last_post_id " . $this->module_config['rss_sort'];
						$result = $db->sql_query($sql);
						$row = $db->sql_fetchrow($result);
						$forum_data['topic_count'] = ( $row['forum_topics'] ) ? $row['forum_topics'] : 1;
						$db->sql_freeresult($result);
						unset($row);
						// now check if we've got still enough topic to ouptut
						if ( $forum_data['topic_count'] <= $this->module_config['rss_url_limit'] ) {
							$time_limit = 0;
						}
					}
					$this->forum_cache[$forum_id]['forum_rss_url'] = $forum_rss_url;
					$chan_title = $this->forum_cache[$forum_id]['forum_name'] = $forum_data['forum_name'];
					$this->forum_cache[$forum_id]['forum_url'] = $this->gym_master->forum_url($forum_data['forum_name'], $forum_id) . $this->url_settings['forum_ext'];
					$this->forum_cache[$forum_id]['forum_url_full'] = $this->gym_master->parse_link($this->forum_cache[$forum_id]['forum_url'], $this->forum_cache[$forum_id]['forum_name'], 'h5');
					// Build Chan info
					// Forum stats
					$forum_stats = "\n" . '<b>' . $user->lang['STATISTICS'] . '</b> : ' . $forum_data['topic_count'] . ' ' . (($forum_data['forum_topics'] >= 0) ? $user->lang['TOPICS'] : $user->lang['TOPIC'] );
					$forum_stats .= ' || ' . $forum_data['forum_posts'] . ' ' . (($forum_data['forum_posts'] >= 0) ? $user->lang['POSTS'] : $user->lang['POST'] );
					// Forum rules ?
					$forum_rules = ($this->module_config['rss_forum_rules'] && $forum_data['forum_rules']) ? generate_text_for_display($forum_data['forum_rules'], $forum_data['forum_rules_uid'], $forum_data['forum_rules_bitfield'], $forum_data['forum_rules_options']) : '';
					$forum_desc = generate_text_for_display($forum_data['forum_desc'], $forum_data['forum_desc_uid'], $forum_data['forum_desc_bitfield'], $forum_data['forum_desc_options']);
					// Is this item public ?
					$this->module_config['rss_auth_msg'] = ($this->gym_master->is_forum_public($forum_id) ? '' : "\n\n" . $user->lang['RSS_AUTH_THIS'] . "\n" );
					// Profiles
					$lastposter = '';
					if ($this->module_config['rss_allow_profile'] ) { 
						$lastposter = "\n" . $user->lang['GYM_LAST_POST_BY'] . $this->gym_master->username_string($forum_data['forum_last_poster_id'], $forum_data['forum_last_poster_name'], $forum_data['forum_last_poster_colour'], $this->module_config['rss_allow_profile_links']);
					}
					$chan_desc = $forum_desc . $forum_rules . "\n" . $forum_stats . $lastposter;
					$chan_image = !empty($forum_data['forum_image']) ? $forum_data['forum_image'] : $this->module_config['rss_image_url'];
				//	nice_print($forum_data);
					$this->gym_master->parse_channel($chan_title . $this->module_config['extra_title'], $chan_desc, $this->forum_cache[$forum_id]['forum_url'], $forum_data['forum_last_post_time'], $chan_image, $forum_rss_url);

			} else { // module Rss

				$forum_sql = !empty($this->options['not_in_id_sql']) ? ' t.forum_id ' . $this->options['not_in_id_sql'] . ' AND ' : '';
				$chan_source = $this->module_config['rss_url'] . $this->url_settings['rss_forum_default'] . $this->url_settings['rss_vpath'] . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
				$this->gym_master->seo_kill_dupes($chan_source);
		
				// Forum stats
				$forum_stats = '<b>' . $user->lang['STATISTICS'] . '</b> : ' . sprintf($user->lang['TOTAL_USERS_OTHER'], $config['num_users']) . ' || ';
				$forum_stats .= sprintf($user->lang['TOTAL_TOPICS_OTHER'], $config['num_topics']) . ' || ';
				$forum_stats .= sprintf($user->lang['TOTAL_POSTS_OTHER'], $config['num_posts']);

				$forum_stats .= ($this->module_config['rss_allow_profile'] ? "\n" . sprintf($user->lang['NEWEST_USER'], $this->gym_master->username_string($config['newest_user_id'], $config['newest_username'], $config['newest_user_colour'])) : '')  . "\n";
				// Chan info
				$chan_title = $this->module_config['rss_sitename'];
				$chan_link = $phpbb_seo->seo_path['phpbb_urlR'] . $this->url_settings['forum_index'];
				$chan_desc = $this->module_config['rss_site_desc'] . "\n\n";
				$forum_image = sprintf($this->gym_master->style_config['rsschan_img_tpl'], $chan_title, $this->module_config['rss_image_url'], $chan_link);
				$chan_time = gmdate('D, d M Y H:i:s \G\M\T', $this->outputs['last_mod_time']);
				$chan_title_full = $chan_title . $this->module_config['extra_title'];
				$this->gym_master->parse_channel($chan_title_full, $chan_desc . $forum_stats, $chan_link,  $this->outputs['last_mod_time'], $this->module_config['rss_image_url'], $chan_source);
				// Grabb forums info
				$forum_data = array();
				$sql = "SELECT * 
					FROM " . FORUMS_TABLE . "
						WHERE forum_id " . $this->options['not_in_id_sql'] . "
					ORDER BY forum_last_post_id " . $this->module_config['rss_sort'];
				$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result)) {
					$forum_data[$row['forum_id']] = $row;
				}
				$db->sql_freeresult($result);
				unset($row);
				// Build sql components
				$time_limit_sql = '';
				if ($this->module_config['rss_limit_time'] > 0 ) {
					$time_limit = ($this->outputs['time'] - $this->module_config['rss_limit_time']);
					$time_limit_sql = "t.topic_last_post_time > $time_limit AND ";
				} else {
					$time_limit_sql = '';
				}
				$sql = "SELECT COUNT(topic_id) AS topic
					FROM " . TOPICS_TABLE . " AS t
					WHERE forum_id " . $this->options['not_in_id_sql'] . "
					AND $time_limit_sql
						topic_status <> " . ITEM_MOVED . "
						AND topic_type <> " . POST_GLOBAL . " 
						AND topic_reported = 0
					ORDER BY t.topic_last_post_id " . $this->module_config['rss_sort'];
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$forum_data['topic_count'] = ( $row['topic'] ) ? $row['topic'] : 1;
				$db->sql_freeresult($result);
				unset($row);
			}
			$this->list_topics($forum_sql, $forum_data, $time_limit, $approve_sql);
		}
		
	}
	/**
	* list_forums() builds the output for forum listing
	* From a forum and from all forums
	* @access private
	*/
	function list_forums() {
		global $db, $user, $phpbb_seo, $auth;
		$sql = "SELECT * 
			FROM " . FORUMS_TABLE . "
				WHERE forum_id " . $this->options['not_in_id_sql'] . "
			ORDER BY forum_last_post_id " . $this->module_config['rss_sort'];
		$result = $db->sql_query($sql);
		while( $forum_data = $db->sql_fetchrow($result) ) {
			$forum_id = (int) $forum_data['forum_id'];
			// Make sure that the forum is auth
			if (isset($this->module_config['exclude_list'][$forum_id])) {
				continue;
			}
			$topics_count = $auth->acl_get('m_approve', $forum_id) ? $forum_data['forum_topics_real'] : $forum_data['forum_topics'];
			// Build Chan info
			$forum_stats = '<b>' . $user->lang['STATISTICS'] . '</b> : ' . $topics_count . ' ' . (($forum_data['forum_topics'] >= 0) ? $user->lang['TOPICS'] : $user->lang['TOPIC'] );
			$forum_stats .= ' || ' . $forum_data['forum_posts'] . ' ' . (($forum_data['forum_posts'] >= 0) ? $user->lang['POSTS'] : $user->lang['POST'] );
			// Forum rules ?
			$forum_rules = ($this->module_config['rss_forum_rules'] && $forum_data['forum_rules']) ? generate_text_for_display($forum_data['forum_rules'], $forum_data['forum_rules_uid'], $forum_data['forum_rules_bitfield'], $forum_data['forum_rules_options']) . "\n" : '';
			$forum_desc = generate_text_for_display($forum_data['forum_desc'], $forum_data['forum_desc_uid'], $forum_data['forum_desc_bitfield'], $forum_data['forum_desc_options']) ;
			$forum_desc .=  !empty($forum_desc) ? "\n\n" : '';
			// Is this item public ?
			$this->module_config['rss_auth_msg'] = ($this->gym_master->is_forum_public($forum_id)) ? '' :  "\n" . $user->lang['RSS_AUTH_THIS'];
			$item_title = $forum_data['forum_name'];
			// Profiles
			$lastposter = '';
			if ($this->module_config['rss_allow_profile'] && !empty($forum_data['forum_last_poster_id'])) { 
				$lastposter =  "\n" . $user->lang['GYM_LAST_POST_BY'] . $this->gym_master->username_string($forum_data['forum_last_poster_id'], $forum_data['forum_last_poster_name'], $forum_data['forum_last_poster_colour'], $this->module_config['rss_allow_profile_links']) . "\n";
			}
			// Build URLs
			$forum_name_ok =  $phpbb_seo->format_url($forum_data['forum_name'], $phpbb_seo->seo_static['forum']);
			$forum_rss_url = $this->module_config['rss_url'] .  ( !empty($this->url_settings['rss_forum_pre']) ? $this->url_settings['rss_forum_pre'] . $forum_id : $this->gym_master->forum_rss_url( $forum_data['forum_name'] , $forum_id)  . '/' ) . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
			$forum_url = $this->gym_master->forum_url($forum_name_ok, $forum_id) . $this->url_settings['forum_ext'];
			$item_desc = $forum_desc . $forum_rules . $forum_stats . $lastposter;
			$this->gym_master->parse_item($item_title, $item_desc, $forum_url, $forum_rss_url, $item_title . $this->module_config['extra_title'], $forum_data['forum_last_post_time']);
		} // End forum list loop
		$db->sql_freeresult($result);
		unset ($forum_data);
	}
	/**
	* list_topics($forum_sql, $forum_data, $time_limit = 0, $approve_sql = '') builds the output for topic listing
	* From a forum and from all forums
	* @access private
	*/
	function list_topics($forum_sql, $forum_data, $time_limit = 0, $approve_sql = '', $topic_type = '') {
		global $config, $db, $phpbb_seo, $auth, $user;
		// Build sql components all remaining cases
		$msg_sql1 = $msg_sql2 = $msg_sql3 = '';
		// DBK if news list, use topic start time
		$time_key = $this->options['rss_news_list'] ? 't.topic_time' : 't.topic_last_post_time';      
		$time_limit = $time_limit > 0 ? "$time_key > $time_limit AND " : '';
		$topic_type_sql = !empty($topic_type) ? " t.topic_type = $topic_type AND ": ' t.topic_type <> ' . POST_GLOBAL . ' AND ';
		if ( $this->options['rss_content'] ) {
			if($this->module_config['rss_last'] || !$this->module_config['rss_first']) { // Go for last post content
				$msg_sql1 = ", p.post_id, p.post_approved, p.post_reported, p.enable_bbcode, p.enable_smilies, p.enable_magic_url, p.enable_sig, p.post_subject, p.post_text, p.post_attachment, p.bbcode_bitfield, p.bbcode_uid, p.post_edit_time";
				$msg_sql2 = ", " . POSTS_TABLE . " AS p ";
				$msg_sql3 = " AND p.post_id = t.topic_last_post_id AND p.post_approved = 1 AND p.post_reported = 0 ";
			}
			if($this->module_config['rss_first']) { // First post as well ?
				$msg_sql1 .= " , pF.post_id as post_idF, pF.post_approved as post_approvedF, pF.post_reported as post_reportedF, pF.enable_bbcode as enable_bbcodeF, pF.enable_smilies as enable_smiliesF, pF.enable_magic_url as enable_magic_urlF, pF.enable_sig as enable_sigF, pF.post_subject as post_subjectF, pF.post_text as post_textF, pF.post_attachment as post_attachmentF, pF.bbcode_bitfield as bbcode_bitfieldF, pF.bbcode_uid as bbcode_uidF, pF.post_edit_time as post_edit_timeF ";
				$msg_sql2 .= ", " . POSTS_TABLE . " as pF ";
				$msg_sql3 .= " AND pF.post_id = t.topic_first_post_id AND pF.post_approved = 1 AND pF.post_reported = 0 ";
			}
		}
		$sql_first = "SELECT t.* $msg_sql1
			FROM " . TOPICS_TABLE . " AS t $msg_sql2
			WHERE $forum_sql $time_limit $topic_type_sql
				t.topic_status <> " . ITEM_MOVED . " 
				$approve_sql
				AND topic_reported = 0
				$msg_sql3
				ORDER BY t.topic_last_post_id " . $this->module_config['rss_sort'];
		// Absolute limit 
		$topic_sofar = 0;
		$topics = array();
		$paginated = $config['posts_per_page'];
		// Do the loop
		while( ( $topic_sofar <  $forum_data['topic_count'] ) && ($this->outputs['url_sofar'] < $this->module_config['rss_url_limit']) ) {
			$sql = $sql_first . " LIMIT $topic_sofar," . $this->module_config['rss_sql_limit'];
			$result = $db->sql_query($sql);
			while ($topic = $db->sql_fetchrow($result)) {
				// In case we are looking for more than one forum
				$forum_id = (int) $topic['forum_id'];
				// Make sure that the forum is auth
				if (isset($this->module_config['exclude_list'][$forum_id])) {
					continue;
				}
				// In case we are going to output forum data many times, let's build this once
				if (empty($this->forum_cache[$forum_id])) {
					// Set mod rewrite & type
					$this->forum_cache[$forum_id]['forum_name_ok'] =  $phpbb_seo->format_url($forum_data[$forum_id]['forum_name'], $phpbb_seo->seo_static['forum']) ;
					$this->forum_cache[$forum_id]['forum_rss_url'] = $this->module_config['rss_url'] .  ( !empty($this->url_settings['rss_forum_pre']) ? $this->url_settings['rss_forum_pre'] . $forum_id : $this->gym_master->forum_rss_url( $forum_data[$forum_id]['forum_name'] , $forum_id) . '/') . $this->url_settings['extra_paramsE'] . $this->url_settings['rss_forum_file'];
					$this->forum_cache[$forum_id]['forum_url'] = $this->gym_master->forum_url($forum_data[$forum_id]['forum_name'], $forum_id)  . $this->url_settings['forum_ext'];
					$this->forum_cache[$forum_id]['forum_name'] = $forum_data[$forum_id]['forum_name'];
					$this->forum_cache[$forum_id]['approve'] = $auth->acl_get('m_approve', $forum_id);
					$this->forum_cache[$forum_id]['replies_key'] = ($this->forum_cache[$forum_id]['approve']) ? 'topic_replies_real' : 'topic_replies';
					$this->forum_cache[$forum_id]['forum_url_full'] = $this->gym_master->parse_link($this->forum_cache[$forum_id]['forum_url'], $this->forum_cache[$forum_id]['forum_name'], 'h5');
				}
				if ( $topic['topic_reported'] == 1 || ($this->forum_cache[$forum_id]['approve'] && $topic['topic_approved'] != 1) ) { // Skip for now if reported or unapproved
					continue;
				}
				$pages = ceil( ($topic[$this->forum_cache[$forum_id]['replies_key']] + 1) / $paginated);
				$topic['topic_title'] = censor_text($topic['topic_title']);
				$topic['topic_replies'] = $topic[$this->forum_cache[$forum_id]['replies_key']];
				$topic_stats = '<b>' . $user->lang['STATISTICS'] . '</b> : ' . ($topic['topic_replies'] + 1) . ' ' . (($topic['topic_replies'] > 1) ? $user->lang['REPLIES'] : $user->lang['POST'] );
				$topic_stats .= ' || ' . ($topic['topic_views'] + 1) . ' ' . $user->lang['VIEWS'];
				$topic['topic_url'] = $this->gym_master->topic_url($topic['topic_title'], $topic['topic_id'], $this->forum_cache[$forum_id]['forum_url'], $forum_id);
				$has_reply = ($topic['topic_last_post_id'] > $topic['topic_first_post_id']) ? TRUE : FALSE;
				
				// Is this item public ?
				$this->module_config['rss_auth_msg'] = ($this->gym_master->is_forum_public($forum_id) ? '' :  "\n\n" . $user->lang['RSS_AUTH_THIS'] ) . "\n\n";

				// Do we output the topic URL
				if( $has_reply && $this->module_config['rss_first']) {
					$topic['topic_urlF'] = $topic['topic_url'] . $this->url_settings['topic_ext'];
					$first_message = '';
					// With the msg content
					if ($this->options['rss_content'] && @$topic['post_idF'] ) {
						if ($topic['post_reportedF'] == 1 || ($this->forum_cache[$forum_id]['approve'] && $topic['post_approvedF'] != 1)) {
							$first_message = $user->lang['RSS_REPORTED_UNAPPROVED'];
						} else {
							$first_message = $this->gym_master->prepare_for_output( $topic, 'F' );
						}
					}
					// Profiles
					$lastposter = '';
					if ($this->module_config['rss_allow_profile']  && !empty($topic['topic_poster'])) { 
						$lastposter = "\n" . $user->lang['GYM_FIRST_POST_BY'] . $this->gym_master->username_string($topic['topic_poster'], $topic['topic_first_poster_name'], $topic['topic_first_poster_colour'], $this->module_config['rss_allow_profile_links']) . "\n\n";
					}
					$item_desc = $this->forum_cache[$forum_id]['forum_url_full'] . "\n\n" .  $first_message. "\n" . $topic_stats . $lastposter;
					// DBK use topic time if news
					$time_key = $this->options['rss_news_list'] ? 'topic_time' : 'topic_last_post_time';
					$this->gym_master->parse_item($topic['topic_title'], $item_desc, $topic['topic_urlF'],  $this->forum_cache[$forum_id]['forum_rss_url'], $this->forum_cache[$forum_id]['forum_name'] . $this->module_config['extra_title'], $topic[$time_key]);
				}
				// Do we output the last post URL
				if ( $this->module_config['rss_last'] || !$has_reply) {
					$start = ($pages > 1) ? $paginated * ($pages-1) : 0;
					$post_num = '';
					$item_title = $topic['topic_title'];
					$profile_key = 'first';
					$user_id_key = 'topic_poster';
					// For news and annoucements
					$first_last = ($this->module_config['rss_first'] && !$this->module_config['rss_last']) ? 'F'  : '';
					if ( $has_reply ) {
						$item_title = !empty($topic['post_subject']) ? $topic['post_subject'] : $topic['topic_title'];

						$post_num = '#p' . $topic['topic_last_post_id'];
						$profile_key = 'last';
						$user_id_key = 'topic_last_poster_id';
					}
					$topic['topic_url' . $first_last] = $topic['topic_url'];
					$topic['topic_url' . $first_last] .= $this->gym_master->set_start($start, $this->url_settings['topic_ext']) . $post_num;
					// With the msg content
					$last_message = '';
					if ($this->options['rss_content'] && @$topic['post_id' . $first_last]) {
						if ($topic['post_reported' . $first_last] == 1 || ($this->forum_cache[$forum_id]['approve'] && $topic['post_approved' . $first_last] != 1)) {
							$last_message = $user->lang['RSS_REPORTED_UNAPPROVED'];
						} else {
							$last_message = $this->gym_master->prepare_for_output( $topic, $first_last);
						}
					}
					// Profiles
					$lastposter = '';
					if ($this->module_config['rss_allow_profile'] && !empty($topic[$user_id_key]) ) { 
						$lastposter = "\n" . $user->lang['GYM_' . strtoupper($profile_key) . '_POST_BY'] . $this->gym_master->username_string($topic[$user_id_key], $topic['topic_' . $profile_key . '_poster_name'], $topic['topic_' . $profile_key . '_poster_colour'], $this->module_config['rss_allow_profile_links']);
					}
					$item_desc = $this->forum_cache[$forum_id]['forum_url_full'] .  $last_message . "\n" . $topic_stats .  $lastposter;
					// DBK use topic time if news
					$time_key = $this->options['rss_news_list'] ? 'topic_time' : 'topic_last_post_time';
					$this->gym_master->parse_item($item_title, $item_desc, $topic['topic_url' . $first_last],  $this->forum_cache[$forum_id]['forum_rss_url'], $this->forum_cache[$forum_id]['forum_name'] . $this->module_config['extra_title'], $topic[$time_key]);
				}
			}// End topic loop
			// Used to separate query
			$topic_sofar = $topic_sofar + $this->module_config['rss_sql_limit'];
			$db->sql_freeresult($result);
			unset($topic);
		}// End Query limit loop
		unset($forum_data, $this->forum_cache);
	}
}
?>