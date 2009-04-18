<?php
/** 
*
* @package acp
* @version $Id: acp_inactive.php,v 1.1 2006/09/23 12:27:50 grahamje Exp $
* @copyright (c) 2006 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* @package module_install
*/
class acp_inactive_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_inactive',
			'title'		=> 'ACP_INACTIVE_USERS',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'list'		=> array('title' => 'ACP_INACTIVE_USERS', 'auth' => 'acl_a_user', 'cat' => array('ACP_CAT_USERS')),
			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>