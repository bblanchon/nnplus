<?php

require_once("config.php");
require_once(WWW_DIR."/lib/adminpage.php");
require_once(WWW_DIR."/lib/users.php");

$page = new AdminPage();
$users = new Users();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

//get the user roles
$userroles = $users->getRoles();
$roles = array();
foreach ($userroles as $r) {
	$roles[$r['ID']] = $r['name'];
}

switch($action) 
{
    case 'add':
    			$page->title = "User Roles Add";
				$role = array();
				$role["name"] = '';
				$role["apirequests"] = '';
				$role["downloadrequests"] = '';
				$role["defaultinvites"] = '';
				$page->smarty->assign('role', $role);	

			break;
    case 'submit':
    
    	if ($_POST["id"] == "")
    	{
			$ret = $users->addRole($_POST['name'], $_POST['apirequests'], $_POST['downloadrequests'], $_POST['defaultinvites']);
			header("Location:".WWW_TOP."/role-list.php");
    	}
    	else
    	{
			$ret = $users->updateRole($_POST['id'], $_POST['name'], $_POST['apirequests'], $_POST['downloadrequests'], $_POST['defaultinvites'], $_POST['isdefault']);
			header("Location:".WWW_TOP."/role-list.php");
		}
        break;
    case 'view':
    default:

			if (isset($_GET["id"]))
			{
				$page->title = "User Roles Edit";
				$id = $_GET["id"];
				$role = $users->getRoleByID($id);

				$page->smarty->assign('role', $role);	
			}

      break;   
}

$page->smarty->assign('yesno_ids', array(1,0));
$page->smarty->assign('yesno_names', array( 'Yes', 'No'));

$page->content = $page->smarty->fetch('role-edit.tpl');
$page->render();

?>