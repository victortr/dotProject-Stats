<head>
<style>
	#spreadsheet TABLE, #spreadsheet TH, #spreadsheet TD { 
	  border:1px solid #CCC;
	}
	
	#spreadsheet TABLE TR:not(:first-child):hover { 
	  background:#BAFECB !important;
	}
}

</style>
</head>


<?php
if (! defined ( 'DP_BASE_DIR' )) {
	die ( 'You should not access this file directly.' );
}
// Get the global var
global $dPconfig, $m;

// Check permissions for this module
if (! getPermission ( $m, 'view' ))
	$AppUI->redirect ( "m=public&a=access_denied" );

$AppUI->savePlace ();

// Include the necessary classes
require_once $AppUI->getModuleClass ( 'tasks' );
require_once $AppUI->getModuleClass ( 'projects' );
require_once $AppUI->getModuleClass ( 'companies' );
require_once $AppUI->getModuleClass ( 'departments' );
require_once $AppUI->getModuleClass ( 'contacts' );

$titleBlock = new CTitleBlock ( 'Project Status', 'helpdesk.png', $m, "$m.$a" );
$titleBlock->show ();
$AppUI->savePlace ();

$perms = & $AppUI->acl ();

// Config
$company_prefix = 'c_';

// Set today
$today = new CDate ();

// Get the params
$q = new DBQuery ();
$q->addTable ( 'users', 'u' );
$q->addQuery ( 'DISTINCT(user_contact)' );
$q->addWhere ( 'user_id = ' . $AppUI->user_id );

$contact = new CContact ();
$contact->load ( $q->loadResult () );
$company = dPgetParam ( $_POST, 'company_id', 0 ); // ($contact->contact_department != 0) ? $contact->contact_department : $company_prefix.$contact->contact_company ); // Company/Department filter Default: current user company

$currency = dPgetParam ( $_POST, 'currency', 0 ); // 0: *1, 1: *1k, 2: *1M Default: *1
$display = dPgetParam ( $_POST, 'display', 0 ); // 0: details, 1: subTotal, 2: total Default: details
$tax = dPgetParam ( $_POST, 'tax', 1 ); // 0: without, 1: with Default: without
$hideNull = dPgetParam ( $_POST, 'hideNull', 2 ); // 0:all, 1:Only valued projects and tasks 2:valued projects 3:Only projects with null budget
$project_status = dPgetParam ( $_POST, 'project_status', 0 ); //
$project_type = dPgetParam ( $_POST, 'project_type', 0 );

$default [0] = $today->getYear (); // Default array for $years
$years = dPgetParam ( $_POST, 'years', $default ); // (array)Years to display Default: this year
$toExpand = dPgetParam ( $_POST, 'expandedList', null ); // List of <tr> to re-expand Default: none(null)
                                                      
// Extract company_id and department_id from company param
$company_id = substr ( strrchr ( $company, $company_prefix ), strlen ( $company_prefix ) );
if ($company_id == '') {
	$company_id = 0;
	$department_id = $company;
	$department = '' . $department_id;
} else
	$department_id = 0;
	
	// Edit the values if necessary
if (dPgetParam ( $_POST, 'edit', 0 ))
	foreach ( $_POST as $vblname => $value )
		updateValue ( $vblname, $value, $tax ); // Check on $_POST value is made after

?>

<form action="?m=stats" method="post" name="filter">
	<table class="tbl" cellspacing="0" cellpadding="4" border="0"
		width="97%" align="center">
		<tbody>
			<tr>
				<td align="right" nowrap style="text-align: left;"><b>Data de hoje: <?php echo date("d/m/Y"); ?></b></td>

				<td align="right" nowrap><?php echo $AppUI->_('Company').'/'.$AppUI->_('Division').':'; ?></td>
				<td align="left" colspan="4" nowrap><?php
				$obj_company = new CCompany ();
				$companies = $obj_company->getAllowedRecords ( $AppUI->user_id, 'company_id,company_name', 'company_name' );
				if (count ( $companies ) == 0) {
					$companies = array (
							0 
					);
				}
				
				// get the list of permitted companies
				$companies = arrayMerge ( array (
						'0' => $AppUI->_ ( 'All' ) 
				), $companies );
				
				// get list of all departments, filtered by the list of permitted companies.
				$q = new DBQuery ();
				$q->addTable ( 'companies', 'c' );
				$q->addQuery ( 'c.company_id, c.company_name, dep.*' );
				$q->addJoin ( 'departments', 'dep', 'c.company_id = dep.dept_company' );
				$q->addOrder ( 'c.company_name, dep.dept_parent, dep.dept_name' );
				$obj_company->setAllowedSQL ( $AppUI->user_id, $q );
				$rows = $q->loadList ();
				
				// display the select list
				$cBuffer = '<select name="company_id" onChange="javascript:submitWithExpandList(this);" class="text">';
				$cBuffer .= ('<option value="0" style="font-weight:bold;">' . $AppUI->_ ( 'All' ) . '</option>' . "\n");
				$comp = '';
				foreach ( $rows as $row ) {
					if ($row ['dept_parent'] == 0) {
						if ($comp != $row ['company_id']) {
							$cBuffer .= ('<option value="' . $AppUI->___ ( $row ['company_id'] ) . '" style="font-weight:bold;"' . (($company . '' == $AppUI->___ ( $row ['company_id'] )) ? 'selected="selected"' : '') . '>' . $AppUI->___ ( $row ['company_name'] ) . '</option>' . "\n");
							$comp = $row ['company_id'];
						}
						
						if ($row ['dept_parent'] != null) {
							showchilddept ( $row );
							findchilddept ( $rows, $row ['dept_id'] );
						}
					}
				}
				$cBuffer .= '</select>';
				echo $cBuffer;
				?></td>
			<?php
			$ptypeTemp = dPgetSysVal ( 'ProjectType' );
			$pstatusTemp = dPgetSysVal ( 'ProjectStatus' );
			$ptype [0] = $AppUI->_ ( 'All' );
			$pstatus [0] = $AppUI->_ ( 'All' );
			$ptype = array_merge ( $ptype, $ptypeTemp );
			$pstatus = array_merge ( $pstatus, $pstatusTemp );
			?>

				<td align="right" colspan="2"><?php echo $AppUI->_('Project Status').' : ';?>
			</td>
				<?php if (isset($_REQUEST ['project_status'])) $status = $_REQUEST ['project_status']; else $status = 4; $_REQUEST ['project_status']--; ?>
				<td align="left" colspan="3"><?php echo arraySelect($pstatus, 'project_status', 'id="project_status" size="1" class="text" onChange="javascript:submitWithExpandList(this);"', $status, true); ?>
			</td>
				<td align="right" nowrap><?php echo $AppUI->_('Project').':'; ?></td>
				<td align="left" colspan="4" nowrap><?php
				
				// get list of all departments, filtered by the list of permitted companies.
				$select = "SELECT * FROM `dotp_projects` ORDER BY `project_company`,`project_name`";
				$exec = db_exec ( $select );
				
				// display the select list
				$pBuffer = '<select name="project" onChange="javascript:submitWithExpandList(this);" class="text">';
				$pBuffer .= ('<option value="0" style="font-weight:bold;">' . $AppUI->_ ( 'All' ) . '</option>' . "\n");
				$comp = '';
				$project = $_REQUEST ['project'];
				while ( $row = db_fetch_array ( $exec ) ) {
					if ($comp != $row ['project_id']) {
						$pBuffer .= ('<option value="' . $AppUI->___ ( $row ['project_id'] ) . '" style="font-weight:bold;"' . (($project . '' == $AppUI->___ ( $row ['project_id'] )) ? 'selected="selected"' : '') . '>' . $AppUI->___ ( $row ['project_company'] . " - " . $row ['project_name'] ) . '</option>' . "\n");
						$comp = $row ['project_id'];
					}
				}
				$pBuffer .= '</select>';
				echo $pBuffer;
				?></td>
				<td align="left" colspan="1"><?php
				$yearsGET = '';
				foreach ( $years as $y ) {
					$yearsGET .= $y . 'y';
				}
				?></td>
				<td align="right" nowrap><?php echo $AppUI->_('Project Owner').':'; ?></td>
				<td align="left" colspan="4" nowrap><?php
				
				// get list of all departments, filtered by the list of permitted companies.
				$select = "SELECT * FROM `dotp_contacts`";
				$exec = db_exec ( $select );
				
				// display the select list
				$pBuffer = '<select name="project_owner" onChange="javascript:submitWithExpandList(this);" class="text">';
				$pBuffer .= ('<option value="0" style="font-weight:bold;">' . $AppUI->_ ( 'All' ) . '</option>' . "\n");
				$comp = '';
				$owner = $_REQUEST ['project_owner'];
				while ( $row = db_fetch_array ( $exec ) ) {
					if ($comp != $row ['contact_id']) {
						$pBuffer .= ('<option value="' . $AppUI->___ ( $row ['contact_id'] ) . '" style="font-weight:bold;"' . (($owner . '' == $AppUI->___ ( $row ['contact_id'] )) ? 'selected="selected"' : '') . '>' . $AppUI->___ ( $row ['contact_first_name'] . " " . $row ['contact_last_name'] ) . '</option>' . "\n");
						$comp = $row ['contact_id'];
					}
				}
				$pBuffer .= '</select>';
				echo $pBuffer;
				?></td>
				<td align="right" colspan="2"><input type="submit" class="button"
					value="Filtrar" /></td>
			</tr>
		</tbody>
	</table>

	<table class="tbl" cellspacing="10" cellpadding="4" border="0"
		width="95%" align="center">
		<tbody></tbody>
	</table>

			<?php
			$not = 0;
			if (isset ( $_REQUEST ['company_id'] )) {
				$select = "SELECT * FROM `dotp_projects` WHERE ";
				if ($_REQUEST ['company_id'] > 0)
					$select = $select . "project_company = '" . $_REQUEST ['company_id'] . "' AND ";
				if ($_REQUEST ['project_status'] > 0)
					$select = $select . "project_status = '" . $_REQUEST ['project_status'] . "' AND ";
				if ($_REQUEST ['project'] > 0)
					$select = $select . "project_id = '" . $_REQUEST ['project'] . "' AND ";
				if ($_REQUEST ['project_owner'] > 0)
					$select = $select . "project_owner = '" . $_REQUEST ['project_owner'] . "' AND ";
				$select = substr ( $select, 0, - 5 );
			} else {
				$select = "SELECT * FROM `dotp_projects` WHERE project_id = 0";
				$not = 1;
			}
			$exec = db_exec ( $select );
			?>
			
<div id="spreadsheet">
<table cellspacing="0" cellpadding="4" width="97%" align="center"
		style="margin-left: 30px; margin-top: 20px; border-collapse: collapse; border-style: solid; border-color: #cccccc;"
		border="1">
		<colgroup>
			<col style="width: 24%" />
			<col style="width: 10%" />
			<col style="width: 10%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
		</colgroup>
		<tr bgcolor="#F0F8FF">
			<td colspan="1"><b>Projeto / Tarefa</b></td>
			<td><b>Final Planejado</b></td>
			<td><b>Ultima Atividade</b></td>
			<td><b>Horas Utilizadas</b></td>
			<td><b>Progresso</b></td>
			<td><b>EV*</b></td>
			<td><b>AC*</b></td>
			<td><b>PV*</b></td>
			<td><b>SPI*</b></td>
			<td><b>CPI*</b></td>
		<?php
		
		if (db_num_rows($exec) == 0){ 
			if ($not == 0){ ?> <tr><td colspan="10"><center><b>Nenhum projeto encontrado com estas especifica&ccedil;&otilde;es</b><center></td><tr>
			<?php } else { ?> <tr><td colspan="10"><center><b>Preencha corretamente os dados dos filtros para exibir os projetos</b><center></td><tr>
		
<?php } }

		while ( $row = db_fetch_array ( $exec ) ) {
			$select = "SELECT * FROM `dotp_tasks` WHERE task_project = '" . $row ['project_id'] . "'";
			$task = db_exec ( $select );
			$ultima_atividade = "0000-01-01 00:00:00";
			$ev = 0;
			$ac = 0;
			$pv = 0;
			$horas = 0;
			$percentage = $row ['project_percent_complete'] . "%";
			while ( $taskrow = db_fetch_array ( $task ) ) {
				$select = "SELECT * FROM `dotp_task_log` WHERE task_log_task = '" . $taskrow ['task_id'] . "'";
				$tasklogexec = db_exec ( $select );
				while ( $tasklog = db_fetch_array ( $tasklogexec ) ) {
					if ($tasklog ['task_log_date'] > $ultima_atividade)
						$ultima_atividade = $tasklog ['task_log_date'];
					$ac += $tasklog ['task_log_hours'];
					if ($taskrow ['task_dynamic'] == 0)
						$horas += $tasklog ['task_log_hours'];
				}
				$ev += $taskrow ['task_duration'] * $taskrow ['task_percent_complete'] / 100;
				$pv += $taskrow ['task_duration'];
			}
			if ($ultima_atividade == "0000-01-01 00:00:00")
				$ultima_atividade = "Nenhuma atividade";
			else
				$ultima_atividade = data ( $ultima_atividade );
			if ($pv != 0)
				$spi = round ( $ev / $pv, 2 );
			else
				$spi = "Sem horas planejadas";
			if ($ac != 0)
				$cpi = round ( $ev / $ac, 2 );
			else
				$cpi = "Sem horas utilizadas";
			?>
		
		<tr class="parent" data-level="0">
			<td colspan="1"><b><span></span>&nbsp;<?php echo $row['project_name']; ?></b></td>
			<td><?php echo data($row['project_end_date']); ?></td>
			<!--fim planejado-->
			<td><?php echo $ultima_atividade; ?></td>
			<!--ultima atividade-->
			<td><?php echo $horas."h"; ?></td>
			<!--horas utilizadas-->
			<td><?php echo $percentage; ?></td>
			<!--progresso-->
			<td><?php echo $ev; ?></td>
			<!--ev-->
			<td><?php echo $ac."h"; ?></td>
			<!--ac-->
			<td><?php echo $pv."h"; ?></td>
			<!--pv-->
			<td
				bgcolor=<?php if ($spi < 0.8) echo "#FF5555"; else { if ($spi > 1) echo "#00FF00"; else echo "#FFFF00"; } ?>><?php echo $spi; ?></td>
			<!--spi-->
			<td
				bgcolor=<?php if ($cpi < 0.8) echo "#FF5555"; else { if ($cpi > 1) echo "#00FF00"; else echo "#FFFF00"; } ?>><?php echo $cpi; ?></td>
			<!--cpi-->
		</tr>

		<?php
			$select2 = "SELECT * FROM `dotp_tasks` WHERE task_project = '" . $row ['project_id'] . "'  ORDER BY `task_parent`, `task_id`";
			$task2 = db_exec ( $select2 );
			
			$count = 1;
			$ref = 0;
			$ref2 = 0;
			while ( $row2 = db_fetch_array ( $task2 ) ) {
				
				if ($row2 ['task_id'] != $row2 ['task_parent']) {
					$count ++;
					if ($row2 ['task_parent'] == $ref2 && $ref != $ref2)
						$count --;
				} else
					$count = 1;
				$ref = $row2 ['task_id'];
				$ref2 = $row2 ['task_parent'];
				
				$ultima_atividade2 = "0000-01-01 00:00:00";
				$ev2 = 0;
				$ac2 = 0;
				$pv2 = 0;
				$horas2 = 0;
				$percentage2 = round ( $row2 ['task_percent_complete'], 2 ) . "%";
				$select2 = "SELECT * FROM `dotp_task_log` WHERE task_log_task = '" . $row2 ['task_id'] . "'";
				$tasklogexec2 = db_exec ( $select2 );
				while ( $tasklog2 = db_fetch_array ( $tasklogexec2 ) ) {
					if ($tasklog2 ['task_log_date'] > $ultima_atividade2)
						$ultima_atividade2 = $tasklog2 ['task_log_date'];
					$ac2 += $tasklog2 ['task_log_hours'];
					if ($row2 ['task_dynamic'] == 0)
						$horas2 += $tasklog2 ['task_log_hours'];
				}
				$ev2 += $row2 ['task_duration'] * $row2 ['task_percent_complete'] / 100;
				$pv2 += $row2 ['task_duration'];
				if ($ultima_atividade2 == "0000-01-01 00:00:00")
					$ultima_atividade2 = "Nenhuma atividade";
				else
					$ultima_atividade2 = data ( $ultima_atividade2 );
				if ($pv2 != 0)
					$spi2 = round ( $ev2 / $pv2, 2 );
				else
					$spi2 = "Sem horas planejadas";
				if ($ac2 != 0)
					$cpi2 = round ( $ev2 / $ac2, 2 );
				else
					$cpi2 = "Sem horas utilizadas";
				
				$select_check = "SELECT * FROM `dotp_tasks` WHERE `task_parent` = '".$row2['task_id']."'";
				$exec_check = db_exec ( $select_check );
				if (db_num_rows($exec_check) > 1) $sub = 1; else $sub = 0;
				
				?>
		<tr class="parent" data-level="<?php echo $count; ?>">
			<td colspan="1"><?php for ($i=0;$i<$count;$i++){ ?><img
				src="modules/stats/toggle-expand-dark.png" height="10" weight="10">&nbsp;<?php } if($sub==1){ ?><u><?php } echo $row2['task_name']; if($sub==1){ ?></u><?php } ?></td>
			<td><?php echo data($row2['task_end_date']); ?></td>
			<!--fim planejado-->
			<td><?php echo $ultima_atividade2; ?></td>
			<!--ultima atividade-->
			<td><?php echo $horas2."h"; ?></td>
			<!--horas utilizadas-->
			<td><?php echo $percentage2; ?></td>
			<!--progresso-->
			<td><?php echo $ev2; ?></td>
			<!--ev-->
			<td><?php echo $ac2."h"; ?></td>
			<!--ac-->
			<td><?php echo $pv2."h"; ?></td>
			<!--pv-->
			<td
				bgcolor=<?php if ($spi2 < 0.8) echo "#FF5555"; else { if ($spi2 > 1) echo "#00FF00"; else echo "#FFFF00"; } ?>><?php echo $spi2; ?></td>
			<!--spi-->
			<td
				bgcolor=<?php if ($cpi2 < 0.8) echo "#FF5555"; else { if ($cpi2 > 1) echo "#00FF00"; else echo "#FFFF00"; } ?>><?php echo $cpi2; ?></td>
			<!--cpi-->
		</tr>
		<?php } } ?>

</table>

</div>

	<center>
		<table
			style="margin-left: 30px; margin-top: 20px; border-collapse: collapse; border-style: solid; border-color: #cccccc"
			border="1">
			<tr>
				<td colspan="2" bgcolor="#F0F8FF"><b><center>L E G E N D A S</center></b></td>
			</tr>
			<tr>
				<td colspan="2"><b>EV = Valor agregado (unidade monet&aacute;ria)</b></td>
			</tr>
			<tr>
				<td colspan="2"><b>AC = Horas utilizadas</b></td>
			</tr>
			<tr>
				<td colspan="2"><b>PV = Horas planejadas</b></td>
			</tr>
			<tr>
				<td colspan="2"><b>SPI = Indice de desempenho de prazos</b></td>
			</tr>
			<tr>
				<td colspan="2"><b>CPI = Indice de desempenho de custos</b></td>
			</tr>
			<tr>
				<td bgcolor="#FF5555">&nbsp&nbsp&nbsp</td>
				<td><b>0.0 < Indice < 0.8</b></td>
			</tr>
			<tr>
				<td bgcolor="#FFFF00">&nbsp&nbsp&nbsp</td>
				<td><b>0.8 < Indice < 1.0</b></td>
			</tr>
			<tr>
				<td bgcolor="#00FF00">&nbsp&nbsp&nbsp</td>
				<td><b>1.0 < Indice</b></td>
			</tr>
			<tr>
				<td colspan="2"><b>* Tarefas sublinhadas possuem subtarefas *</b></td>
			</tr>
		</table>
	</center>

		<?php
		function data($string) {
			if ($string != null)
				return substr ( $string, 8, 2 ) . "/" . substr ( $string, 5, 2 ) . "/" . substr ( $string, 0, 4 );
			else
				return "data final indefinida";
		}
		function hora($string) {
			return substr ( $string, 11, 5 );
		}
		
		?><script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>
	<script> 

	$(document).ready(function() {

	    function getChildren($row) {
	        var children = [], level = $row.attr('data-level');
	        while($row.next().attr('data-level') > level) {
		        if (($row.next().attr('data-level') == (parseFloat(level)+1)) && ($row.next().is(":hidden"))) children.push($row.next());
		        else $row.next().hide();
	            $row = $row.next();
	        }            
	        return children;
	    }        

	    $('.parent').on('click', function() {
	    
	        var children = getChildren($(this));
	        $.each(children, function() {
	            $(this).toggle();
	        })
	    });          
	    
	})
	
$(function(){
	$row = $('.parent');
    while($row.attr('data-level') >= 0) {
		if ($row.attr('data-level') > 0)
   	 		$row.hide();
		else
			$row.show();
        $row = $row.next();
    }            
});
	
$(function(){
	$('.parent').find('span').text(function(_, value){return value=='+'?'-':'+'});
});
	
$('.parent').click(function(){
   $(this).find('span').text(function(_, value){return value=='-'?'+':'-'});
});
</script>