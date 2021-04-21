<?php
/**
 * test page
 * access at https://mcri-r0289.mcri.edu.au/redcap_v10.7.1/ExternalModules/?prefix=record_lookup&page=performance_test&pid=220
 */
error_reporting(0);

if (is_null($module) || !($module instanceof MCRI\RecordLookup\RecordLookup)) { throw new Exception('No module object'); }
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ntimes = 10;
echo "<h5>Search Performance Testing</h5><p>$ntimes iterations each search</p>";

/* benchmark search using
1. REDCap::getData()
2. sql select with group_concat and having
3. sql select all data rows and php filter
4. sql select with group_concat and subquery
//5. sql select from view
*/
$methods = array(
    1 => 'REDCap::getData',
    2 => 'sql select with group_concat and having',
    3 => 'sql select all data rows and php filter',
    4 => 'sql select with group_concat and subquery',
    5 => 'sql select with group_concat and join'
);

$searches = array(
    array('firstname'=>'alice','lastname'=>'','dateofbirth'=>''),
    array('firstname'=>'cam','lastname'=>'','dateofbirth'=>''),
    array('firstname'=>'cam','lastname'=>'cam','dateofbirth'=>''),
    array('firstname'=>'','lastname'=>'','dateofbirth'=>'2011-07'),
    array('firstname'=>'isa','lastname'=>'','dateofbirth'=>'2014')
);

foreach ($methods as $mi => $m) {
    $tmtot = 0;
    echo "<div class='gray my-1'>Search Method #$mi: $m";
    foreach ($searches as $si => $s) {
        //echo "<pre>".print_r($s, true)."</pre>";
        $ts0 = \microtime(true);

        for ($i=0; $i<$ntimes; $i++) {
            $result = array();
            switch ($mi) {
                case 1: $result = getMethod1Result($s); break;
                case 2: $result = getMethod2Result($s); break;
                case 3: $result = getMethod3Result($s); break;
                case 4: $result = getMethod4Result($s); break;
                case 5: $result = getMethod5Result($s); break;
                default: $result = array(); break;
            }
        }

        $tmtot += $ts = round(microtime(true) - $ts0, 1);
        
        echo "<p>Search #$si found ".count($result)." results in $ts sec</p>";
    }
    echo "<p>Method #$mi total duration $tmtot sec</p></div>";
}


require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

function getMethod1Result($s) {
    $logic = '';
    foreach($s as $f => $v) {
        if ($v!='') $logic .= "contains([$f], '$v') and ";
    }
    return \REDCap::getData(array(
        'return_format'=>'array',
        'filterLogic'=>trim($logic, 'and ')
    ));
}
/**
 * Get db to make dataset with group_concat and filter using having
 */
function getMethod2Result($s) {
    global $module;
    $select = '';
    $having = '';
    foreach($s as $f => $v) {
        if ($v!='') {
            $select .= ", group_concat(if(field_name='$f',value,null)) as $f ";
            $having .= " group_concat(if(field_name='$f',value,null)) like '%$v%' and ";
        }
    }
    $having = trim($having, ' and ');
    $sql =  "select record $select from redcap_data where project_id=? group by project_id, event_id, record having $having";
    $q = $module->query($sql, [$_GET['pid']]);

    $result = array();
    while ($row = $q->fetch_assoc()) {
        $result[] = $row;
    }
    return $result;
}
/**
 * Read all data rows then use php to reshape and filter
 */
function getMethod3Result($s) {
    global $module;
    $where = implode("','", array_keys($s));
    $sql =  "select record, field_name, value from redcap_data where project_id=? and field_name in ( '$where' )";
    $q = $module->query($sql, [$_GET['pid']]);

    $datarows = array();
    while ($row = $q->fetch_assoc()) {
        $datarows[$row['record']][$row['field_name']] = $row['value'];
    }
    
    $result = array();
    foreach ($datarows as $recId => $rec) {
        $includeRec = true;
        foreach($s as $f => $v) {
            if ($v!='') {
                if (!array_key_exists($f, $rec)
                        || strpos(strtolower($rec[$f]), strtolower($v))===false) {
                    $includeRec = false;
                    break;
                }
            }
        }
        if ($includeRec) $result[$recId] = array();
    }
    foreach (array_keys($result) as $thisRecId) {
        $recValues = array();
        foreach(array_keys($s) as $f) {
            $recValues[$f] = (array_key_exists($f, $datarows[$thisRecId])) 
                ? $datarows[$thisRecId][$f]
                : '';
        }
        $result[$thisRecId] = $recValues;
    }
    return $result;
}
function getMethod4Result($s) {
    global $module;
/*select record
, group_concat(if(field_name='firstname',value,null)) as firstname
, group_concat(if(field_name='lastname',value,null)) as lastname
, group_concat(if(field_name='dateofbirth',value,null)) as dateofbirth
from redcap_data
where project_id=220
and record in (
	select record
    from redcap_data 
    where project_id=220
    and ( 
		(field_name='firstname' and value like '%alice%')
        #or (field_name='firstname' and value like '%alice%')
	)
) 
group by project_id, record, event_id;
*/
    $select = '';
    $having = '';
    foreach($s as $f => $v) {
        $select .= ", group_concat(if(field_name='$f',value,null)) as $f ";
        if ($v!='') {
            $wheresub .= " (field_name='$f' and value like '%$v%') or ";
        }
    }
    $wheresub = trim($wheresub, ' or ');
    $sql =  "select record $select from redcap_data where project_id=? and record in (select record from redcap_data where project_id=? and ( $wheresub ) ) group by project_id, event_id, record";
    $q = $module->query($sql, [$_GET['pid'], $_GET['pid']]);

    $result = array();
    while ($row = $q->fetch_assoc()) {
        $result[] = $row;
    }
    return $result;
}
function getMethod5Result($s) {
    global $module;
/*select redcap_data.record
, group_concat(if(field_name='firstname',value,null)) as firstname
, group_concat(if(field_name='lastname',value,null)) as lastname
, group_concat(if(field_name='dateofbirth',value,null)) as dateofbirth
from redcap_data
inner join (
	select record
    from redcap_data 
    where project_id=220
    and ( 
		(field_name='firstname' and value like '%alice%')
        #or (field_name='firstname' and value like '%alice%')
	)
) sub on redcap_data.record = sub.record
where project_id=220
group by project_id, redcap_data.record, event_id;
*/
    $select = '';
    $having = '';
    foreach($s as $f => $v) {
        $select .= ", group_concat(if(field_name='$f',value,null)) as $f ";
        if ($v!='') {
            $wheresub .= " (field_name='$f' and value like '%$v%') or ";
        }
    }
    $wheresub = trim($wheresub, ' or ');
    $sql =  "select redcap_data.record $select from redcap_data inner join (select record from redcap_data where project_id=? and ( $wheresub ) ) sub on redcap_data.record=sub.record where project_id=? group by project_id, event_id, redcap_data.record";
    $q = $module->query($sql, [$_GET['pid'], $_GET['pid']]);

    $result = array();
    while ($row = $q->fetch_assoc()) {
        $result[] = $row;
    }
    return $result;
}