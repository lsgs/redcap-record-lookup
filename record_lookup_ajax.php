<?php
/**
 * REDCap External Module: Record Lookup
 * @author Luke Stevens lukestevens@hotmail.com https://github.com/lsgs/ 
 */
if (is_null($module) || !($module instanceof MCRI\RecordLookup\RecordLookup)) { exit(); }
header("Content-Type: application/json");
//echo json_encode(array('data' => $module->search()));
echo json_encode($module->search());