<?php
/**
 * test page
 * access at https://mcri-r0289.mcri.edu.au/redcap_v10.7.1/ExternalModules/?prefix=record_lookup&page=connection_test&pid=220
 */
error_reporting(0);

if (is_null($module) || !($module instanceof MCRI\RecordLookup\RecordLookup)) { throw new Exception('No module object'); }
        
$ss = $module->getSystemSettings();
$connections = array();
$connopts = array(-1=>'');
$thisconn = -1;

for ($i=0; $i<count($ss['external-connections']['system_value']); $i++) {
    if ($ss['external-connections']['system_value'][$i]!=='true') continue;
    $thisconn = (isset($_GET['c']) && $i==$_GET['c']) ? $i : $thisconn;
    $connopts[$i] = $ss['ext-conn-name']['system_value'][$i];
    $connections[$i]['name']   = $ss['ext-conn-name']['system_value'][$i];
    $connections[$i]['host']   = $ss['ext-conn-host']['system_value'][$i];
    $connections[$i]['port']   = $ss['ext-conn-port']['system_value'][$i];
    $connections[$i]['db']     = $ss['ext-conn-db']['system_value'][$i];
    $connections[$i]['user']   = $ss['ext-conn-user']['system_value'][$i];
    $connections[$i]['pw']     = $ss['ext-conn-pw']['system_value'][$i];
    $connections[$i]['sql']    = $ss['ext-conn-sql']['system_value'][$i];
    $connections[$i]['socket'] = null;
}

$page = new \HtmlPage();
$page->PrintHeader();
echo "<h1 style='font-size:20pt;margin-top:40px;'>Record Lookup External Database Connection Test Page</h1>";
if (count($connections)===0) {
    echo 'No external connections configured in Control Center external module settings.';
} else {
    ?>
    <script type="text/javascript">
        $(document).ready(function(){
            $('#conn').on('change', function(){
                var loc = window.location.href;
                var cval = "&c="+$(this).val();
                if (loc.includes('c=')) {
                    loc = loc.replace(/c=.+/, cval);
                } else {
                    loc = loc+cval;
                }
                window.location.href = loc;
            });
        });
    </script>
    <?php
    $select = \RCView::select(
        array('id'=>'conn'),
        $connopts,
        $thisconn
    );

    echo "<div class='my-4'>$select</div>";
    echo "<div class='mb-4'>";

    if ($thisconn > -1) {
        $connname = $connections[$thisconn]['name'];
        $hostname = $connections[$thisconn]['host'];
        $port = $connections[$thisconn]['port'];
        $db = $connections[$thisconn]['db'];
        $username = $connections[$thisconn]['user'];
        $password = $connections[$thisconn]['pw'];
        $db_socket = $connections[$thisconn]['socket'];

        $extconn = new \mysqli($hostname, $username, $password, $db, $port, $db_socket);

        if ($extconn->connect_error) {
            echo "Could not make '$connname' connection at $hostname:$port using user $username. Check the connection values in the Control Center module settings. ";
        } else {
            echo "Connection '$connname' successful at $hostname:$port using user $username. ";
        }
    }
    echo "</div>";
}
$page->PrintFooter();