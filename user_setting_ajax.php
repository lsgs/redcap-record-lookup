<?php
/**
 * REDCap External Module: Record Lookup
 * @author Luke Stevens lukestevens@hotmail.com https://github.com/lsgs/ 
 */
if (is_null($module) || !($module instanceof MCRI\RecordLookup\RecordLookup)) { exit(); }

try {
    if (isset($_POST['cb']) && isset($_POST['state'])) {
        $module->setUserSetting($_POST['cb'], !!$_POST['state']);
    } else {
        $rtn = 'cb and state not found in message';
    }
} catch (\Exception $ex) {
    $rtn = $ex->getMessage();
} 
header("Content-Type: application/json");
echo $rtn;