<?php
if (!defined('DP_BASE_DIR')) {
  die('You should not access this file directly.');
}

/**
 *  Name: Hello World
 *  Directory: hello
 *  Version 1.0
 *  Type: user
 *  UI Name: Hello World
 *  UI Icon: ?
 */

$config = array();
$config['mod_name'] = 'Status'; // name the module
$config['mod_version'] = '0.1'; // add a version number
$config['mod_directory'] = 'stats'; // tell dotProject where to find this module
$config['mod_setup_class'] = 'CSetupStats'; // the name of the PHP setup class (used below)
$config['mod_type'] = 'user'; //'core' for standard dP modules, 'user' for additional modules from dotmods
$config['mod_ui_name'] = 'Status'; // the name that is shown in the main menu of the User Interface
$config['mod_ui_icon'] = 'helpdesk.png'; // name of a related icon
$config['mod_description'] = 'A project status indicator modules'; // some description of the module
$config['mod_config'] = false; // show 'configure' link in viewmods

if (@$a == 'setup') {
	echo dPshowModuleConfig( $config );
}

// TODO: To be completed later as needed.
class CSetupStats {

	function install() {
		$dbprefix = dPgetConfig('dbprefix', '');
		$success = 1;
		
		$bulk_sql[] = "
                  CREATE TABLE `{$dbprefix}stats_options` (
                    `pd_option_id` INT(11) NOT NULL auto_increment,
                    `pd_option_user` INT(11) NOT NULL default 0 UNIQUE,
                    `pd_option_view_project` INT(1) NOT NULL default 1,
                    `pd_option_view_gantt` INT(1) NOT NULL default 1,
                    `pd_option_view_tasks` INT(1) NOT NULL default 1,
                    `pd_option_view_actions` INT(1) NOT NULL default 1,
                    `pd_option_view_addtasks` INT(1) NOT NULL default 1,
                    `pd_option_view_files` INT(1) NOT NULL default 1,
                    PRIMARY KEY (`pd_option_id`) 
                  );";
            foreach ($bulk_sql as $s) {
                  db_exec($s);
                  
                  if (db_error()) {
                        $success = 0;
                  }
            }      
		return $success;
	}
	
	function remove() {
		$dbprefix = dPgetConfig('dbprefix', '');
		$success = 1;

		$bulk_sql[] = "DROP TABLE `{$dbprefix}stats_options`";
		foreach ($bulk_sql as $s) {
			db_exec($s);
			if (db_error())
				$success = 0;
		}
		return $success;
	}
	
	function upgrade() {
		return null;
	}
	
      function configure() {
            return true;
      }
	
}
?>