<?php

/**
 * Edit snmp result
 ***************************/

/* functions */
require( dirname(__FILE__) . '/../../../functions/functions.php');

// no errors
error_reporting(E_ERROR);

# initialize user object
$Database 	= new Database_PDO;
$User 		= new User ($Database);
$Admin	 	= new Admin ($Database);
$Result 	= new Result ();

# verify that user is logged in
$User->check_user_session();

# validate csrf cookie
$_POST['csrf_cookie']==$_SESSION['csrf_cookie'] ? :             $Result->show("danger", _("Invalid CSRF cookie"), true, false, false, true);

# get modified details
$device = $Admin->strip_input_tags($_POST);

# ID, port snd community must be numeric
if(!is_numeric($_POST['device_id']))			              { $Result->show("danger", _("Invalid ID"), true, false, false, true); }
if(!is_numeric($_POST['snmp_version']))			              { $Result->show("danger", _("Invalid version"), true, false, false, true); }
if($_POST['snmp_version']!=0) {
if(!is_numeric($_POST['snmp_port']))			              { $Result->show("danger", _("Invalid port"), true, false, false, true); }
if(!is_numeric($_POST['snmp_timeout']))			              { $Result->show("danger", _("Invalid timeout"), true, false, false, true); }
}

# version can be 0, 1 or 2
if ($_POST['snmp_version']<0 || $_POST['snmp_version']>2)     { $Result->show("danger", _("Invalid version"), true, false, false, true); }

# validate device
$device = $Admin->fetch_object ("devices", "id", $_POST['device_id']);
if($device===false)                                           { $Result->show("danger", _("Invalid device"), true, false, false, true); }

# set new snmp variables
$device->snmp_community = $_POST['snmp_community'];
$device->snmp_version   = $_POST['snmp_version'];
$device->snmp_port      = $_POST['snmp_port'];
$device->snmp_timeout   = $_POST['snmp_timeout'];

# init snmp class
$Snmp = new phpipamSNMP ();


# set queries
foreach($_POST as $k=>$p) {
    if(strpos($k, "query-")!==false) {
        if($p=="on") {
            $queries[] = substr($k, 6);
        }
    }
}
# fake as device queries
$device->snmp_queries = implode(";", $queries);

# open connection
if (isset($queries)) {
    // set device
    $Snmp->set_snmp_device ($device);

    // loop
    foreach($queries as $query) {
        try {
            $Snmp->get_query ($query);
            // ok
            $debug[$query]['oid']    = $Snmp->snmp_queries[$query]->oid;
            $debug[$query]['result'] = $Snmp->last_result;

            $res[] = $Result->show("success", "<strong>$query</strong>: OK<br><span class='text-muted'>".$Snmp->snmp_queries[$query]->description."</span>", false, false, true);

        } catch ( Exception $e ) {
            // fail
            $res[] = $Result->show("danger", "<strong>$query</strong><br><span class='text-muted'>".$Snmp->snmp_queries[$query]->description."</span><hr> ".$e->getMessage(), false, false, true);
        }
    }

    // debug
    $res[] = "<hr>";
    $res[] = "<div class='text-right'>";
    $res[] = "  <a class='btn btn-sm btn-default pull-right' id='toggle_debug'>Toggle debug</a><br><br>";
    $res[] = "</div>";
    $res[] = " <pre id='debug' style='display:none;'>";
    $res[] = print_r($debug, true);
    $res[] = "</pre>";

    //print
    $Result->show("Query result", implode("", $res), false, true, false, true);
}
else {
   $Result->show("warning", _("No queries"), false, true, false, true);
}
?>


<script type="text/javascript">
$(document).ready(function(){
    $('#toggle_debug').click(function() { $('#debug').toggle() });
});