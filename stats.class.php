<?php
if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

require_once $AppUI->getSystemClass('dp');
/**
 * Hello Class
 */
class CSetupPStats extends CDpObject {

	var $stats_id = NULL;
	var $hstats_text = NULL;
	
	function CLink() {
		$this->CDpObject('stats', 'stats_id');
	}

	function check() {
	// ensure the integrity of some variables
		$this->stats_id = intval($this->stats_id);

		return NULL; // object is ok
	}

	function delete() {
		global $dPconfig;
		$this->_message = "deleted";

	// delete the main table reference
		$q = new DBQuery();
		$q->setDelete('stats');
		$q->addWhere('stats_id = ' . $this->hello_id);
		if (!$q->exec()) {
			return db_error();
		}
		return NULL;
	}
}
?>