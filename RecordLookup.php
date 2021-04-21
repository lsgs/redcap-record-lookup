<?php
/**
 * REDCap External Module: Record Lookup
 * @author Luke Stevens lukestevens@hotmail.com https://github.com/lsgs/ 
 */

namespace MCRI\RecordLookup;

use ExternalModules\AbstractExternalModule;

class RecordLookup extends AbstractExternalModule {
    protected $searchFields;
    protected $searchPlaces;

	function redcap_add_edit_records_page($project_id) {
        global $lang;

        $lookupUrl = $this->getUrl('record_lookup_ajax.php');
        $importUrl = $this->getUrl('import_record_ajax.php');
        $usUrl = $this->getUrl('user_setting_ajax.php');
        $dschecked = $this->getUserSetting('RecordLookupViewDefault');
        $rlchecked = $this->getUserSetting('RecordLookupViewLookup');

        $dschecked = (is_null($dschecked) || $dschecked) ? 'checked="checked"' : '';
        $rlchecked = (is_null($rlchecked) || $rlchecked) ? 'checked="checked"' : '';

        $this->initializeJavascriptModuleObject();
        $jsObjectName = $this->framework->getJavascriptModuleObjectName();
        ?>
        <div id="RecordLookupView" class="container m-0 p-0">
            <div class="row">
                <div class="col-6"><label><input id="RecordLookupViewDefault" type="checkbox" <?=$dschecked?>> Display default <?=$lang['data_entry_138']?></label></div>
                <div class="col-6"><label><input id="RecordLookupViewLookup" type="checkbox" <?=$rlchecked?>> <i class="fas fa-cube m-1"></i>Display Record Lookup</label></div>
            </div>
        </div>
        <div id="RecordLookup" class="" style="display:none;">
            <div id="RecordLookupSearch" class="mb-4"><?=$this->makeSearchInputs()?></div>
            <div id="RecordLookupDisplay" style="display:none;"><?=$this->makeDisplayTable()?></div>
        </div>
        <style type="text/css">
            #RecordLookupView { max-width: 700px; }
            #RecordLookup { max-width: 1000px; }
            #RecordLookupSearch { max-width: 700px; }
            #RecordLookupTable { width: 100% }
            #RecordLookupTable table { width: 100% }
            #RecordLookupTable td { padding: 4px 5px }
            #RecordLookupTable a.btn { color: white; }
            .spinner { background: url("<?php echo APP_PATH_IMAGES;?>progress_circle.gif") center no-repeat; }
        </style>
        <script type="text/javascript">
            <?=$jsObjectName?>.searchTerms = [];
            <?=$jsObjectName?>.setPageBlockDisplay = function(block, displayBool) {
                if (block==='RecordLookupViewDefault') {
                    if (displayBool) {
                        $('table.form_border').eq(1).parent('div').show();
                    } else {
                        $('table.form_border').eq(1).parent('div').hide();
                    }
                } else if (block==='RecordLookupViewLookup') {
                    if (displayBool) {
                        $('#RecordLookup').show();
                    } else {
                        $('#RecordLookup').hide();
                    }
                }
            };

            <?=$jsObjectName?>.import = function(btn) {
                console.log(btn);
                $(btn).hide().parent('td').addClass('spinner');
                var recordData = [];
                $(btn).closest('tr').find('td:gt(0)').each(function(i, e){
                    recordData.push($(e).text());
                });
                $.ajax({
                    url: '<?=$importUrl?>',
                    type: 'POST',
                    dataType: 'json',
                    data: { import: recordData },
                    success: function(data) {
                        console.log(data);
                        $(btn).parent('td').removeClass('spinner').append(
                            '<a type="button" class="btn btn-xs btn-primaryrc" href="?pid='+pid+'&id='+encodeURIComponent(data)+'">Open<i class="ml-1 fas fa-chevron-circle-right"></i></a>'
                        );
                    },
                    error: function(data) {
                        console.log(data);
                        $(btn).show().parent('td').removeClass('spinner');
                        alert('An error occurred.');
                    }
                });

            };

            <?=$jsObjectName?>.search = function(terms) {
//                console.log(< ? =$jsObjectName?>.searchPlaces);
//                console.log(terms);

                terms.forEach(function(e){
                    <?=$jsObjectName?>.searchPlaces[e.field]['q'] = e.val;
                });
                
                $('#RecordLookupDisplay').hide();
                $('#btnRecordLookupSearch').prop('disabled', true);
                $('#RecordLookup_search').hide();
                $('#RecordLookup_search_progress').show();

                $.ajax({
                    url: '<?=$lookupUrl?>',
                    type: 'POST',
                    dataType: 'json',
                    data: { search: <?=$jsObjectName?>.searchPlaces },
                    success: function(data) {
                        //console.log(data);
                        
                        $('#RecordLookupTable').DataTable( {
                            destroy: true, 
                            data: data,
                            paging: false,
                            scrollX: 1000,
                            scrollY: 400,
                            columnDefs: [ {
                                targets: 0,
                                render: function ( celldata, type, row ) {
                                    var src = <?=$jsObjectName?>.projects[celldata];
                                    return '<span class="text-muted">'+src+'</span>';
                                }
                            }, {
                                targets: 1,
                                render: function ( celldata, type, row ) {
                                    var cellhtml;
                                    celldata = celldata.split('|');
                                    if (celldata[0]==pid) {
                                        // record in current project - link to record home
                                        cellhtml = '<a type="button" class="btn btn-xs btn-primaryrc nowrap" href="?pid='+pid+'&id='+encodeURIComponent(celldata[1])+'">Open<i class="ml-1 fas fa-chevron-circle-right"></i></a>';
                                    } else {
                                        // record in other project - import option
                                        cellhtml = '<button type="button" class="btn btn-xs btn-success nowrap" onclick="<?=$jsObjectName?>.import(this)"><i class="mr-1 fas fa-download"></i>Import</a>';
                                    }
                                    return cellhtml;
                                }
                            } ],
                            aaSorting: [[2,'asc']],
                            oLanguage: {
                                sSearch: "Filter results:"
                            },                        
                            initComplete: function(){
                                this.fnAdjustColumnSizing(true);
                                $('#RecordLookupDisplay').show();
                                $('#btnRecordLookupSearch').prop('disabled', false);
                                $('#RecordLookup_search').show();
                                $('#RecordLookup_search_progress').hide();
                            }
                        }).columns.adjust();
                    },
                    error: function(data) {
                        console.log(data);
                        alert('An error occurred.');
                        $('#RecordLookupDisplay').show();
                        $('#btnRecordLookupSearch').prop('disabled', false);
                        $('#RecordLookup_search').show();
                        $('#RecordLookup_search_progress').hide();
                    }
                });
            };

            <?=$jsObjectName?>.init = function() {
                var rlSearchButton = $('#btnRecordLookupSearch');
                $('#RecordLookupViewDefault, #RecordLookupViewLookup').on('change', function() {
                    $.post("<?=$usUrl?>", { cb: this.id, state: (this.checked)?1:0 }, function() { console.log(data); });
                    <?=$jsObjectName?>.setPageBlockDisplay(this.id, this.checked);
                });
                $('#RecordLookup').insertBefore('#south');
                $('<div class="clear"></div>').insertBefore('#south');
                ['RecordLookupViewDefault','RecordLookupViewLookup'].forEach(function(e){
                    <?=$jsObjectName?>.setPageBlockDisplay(e, $('#'+e).prop('checked'));
                });

                rlSearchButton.on('click', function() {
                    var terms = [];
                    var gotTerm = false;
                    $('input.RecordLookupSearch').each(function(i, e){
                        var fid = e.id.replace('RecordLookupSearch_', ''); // e.field is "RecordLookupSearch_x";
                        var fval = $(e).val().trim();
                        gotTerm = gotTerm || fval!='';
                        terms.push({ field: fid, val: fval });
                    });
                    if (gotTerm) {
                        <?=$jsObjectName?>.search(terms);
                    } else {
                        $('input.RecordLookupSearch').eq(0).focus();
                        alert('Enter search terms.');
                    }
                });
                    
                $('input.RecordLookupSearch').on('keydown', function(event) {
                    if(event.keyCode==13){rlSearchButton.trigger('click')};
                });
            }; // init()

            $(document).ready(function(){
                <?=$jsObjectName?>.init();
            });
        </script>
        <?php
	}

    protected function makeSearchInputs() {
        global $Proj, $lang;
        $jsObjectName = $this->framework->getJavascriptModuleObjectName();
        $settings = $this->getSubSettings('lookup-config');
        $this->searchFields = array();
        $this->searchPlaces = array();
        echo '<table role="presentation" class="form_border" width="100%"><tbody><tr><td class="header" colspan="2" style="font-weight:normal;padding:10px 5px;color:#800000;font-size:13px;"><i class="fas fa-cube mr-1"></i>Record Lookup</td></tr>';
        foreach($settings as $searchPlace) {
            $thisProj = ($searchPlace['lookup-project']==$Proj->project_id) ? $Proj : new \Project($searchPlace['lookup-project']);
            foreach ($searchPlace['lookup-fields'] as $lf) {
                $lfName = array_key_exists('lookup-alt-name',$lf) && $lf['lookup-alt-name']!=''
                        ? $lf['lookup-alt-name']
                        : $lf['lookup-field'];
                $this->searchFields[$lf['lookup-field']][] = array(
                    'project' => $thisProj,
                    'field-source' => $lf['lookup-field'],
                    'field-dest' => $lfName,
                );
                $this->searchPlaces[$lf['lookup-field']]['projects'][] = array('project_id'=>$thisProj->project_id,'field'=>$lfName);
            }
        }

        if (count($this->searchFields)) {
            $projects = array();
            foreach ($this->searchFields as $sfName => $sfSpec) {
                $label = substr(\filter_tags($Proj->metadata[$sfName]['element_label'], false), 0, 30);
                $fieldProjects = array();
                foreach ($sfSpec as $sp) {
                    $projects[$sp['project']->project_id] = $sp['project']->project['app_title'];
                    $fieldProjects[] = \filter_tags($sp['project']->project['app_title'], false)." (PID ".$sp['project']->project_id.")";
                }
                echo '<tr>';
                echo '<td class="labelrc" style="width:275px;padding:4px 8px;">'.$label.'<div class="font-weight-normal text-muted pl-2" style="font-size:75%">'.implode('<br>', $fieldProjects).'</div></td>';
                echo '<td class="data" style="padding:4px 8px;"><input id="RecordLookupSearch_'.$sfName.'" type="text" size="30" class="x-form-text x-form-field ui-autocomplete-input RecordLookupSearch" /></td>';
                echo '</tr>';
            }
            echo '<tr>';
            echo '<td class="labelrc">&nbsp;</td>';
            echo '<td class="data"><button id="btnRecordLookupSearch" type="button" class="btn btn-xs btn-primaryrc"><span id="RecordLookup_search_progress" style="display:none;"><img src="'.APP_PATH_IMAGES.'progress_circle.gif" style="vertical-align:middle;padding-right:1em;" alt="Searching...">Searching...</span><span id="RecordLookup_search"><i class="fas fa-search mr-1"></i>'.$lang['control_center_439'].'</span></td>';
            echo '</tr>';
        } else {
            echo '<tr><td class="labelrc" colspan="2">Configure lookup projects and fields via the External Modules page.</td></tr>';
        }
        echo '</tbody></table>';
        ?>
        <script type="text/javascript">
            <?=$jsObjectName?>.searchPlaces = JSON.parse('<?=\js_escape(\json_encode($this->searchPlaces))?>');
            <?=$jsObjectName?>.projects = JSON.parse('<?=\js_escape(\json_encode($projects))?>');
        </script>
        <?php
    }

    protected function makeDisplayTable() {
        global $lang, $Proj;
        ?>
        <div class="my-1">
            <table id="RecordLookupTable" width="100%" class="xdataTable cell-border table table-sm" style="">
                <thead>
                    <th>Source</th>
                    <th>Action</th>
        <?php
        foreach ($this->searchFields as $sfName => $sfSpec) {
            $label = substr(\filter_tags($Proj->metadata[$sfName]['element_label'], false), 0, 30);
            echo '<th class="font-weight-bold">'.$label.'</th>';
        }
        ?>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <?php
    }

    public function import() {
        global $Proj;

        $settings = $this->getSubSettings('lookup-config');
        $this->searchFields = array();
        foreach($settings as $searchPlace) {
            foreach ($searchPlace['lookup-fields'] as $lf) {
                $this->searchFields[$lf['lookup-field']] = null;
            }
        }

        $fields = array_keys($this->searchFields);
        $fieldData = array();
        for ($i=0; $i < count($fields); $i++) { 
            $fieldData[$fields[$i]] = $_POST['import'][$i]; // $_POST fields sent in sequence
        }
        $newRecordId = \REDCap::reserveNewRecordId($Proj->project_id);
        $newRecord = array($newRecordId=>array($Proj->firstEventId => $fieldData));

        $saveResult = \REDCap::saveData('array', $newRecord, 'overwrite');

        if (count($saveResult['errors'])>0) {
            $title = "Record Lookup module";
            $detail = "Record import failed record=$newRecordId (new) ";
            $detail .= " \n".print_r($_POST, true);
            $detail .= " \n".print_r($saveResult['errors'], true);
            \REDCap::logEvent($title, $detail, '');
        }
        return $newRecordId;
    }

    public function search() {

        // $_POST sent as array of field names and search text
        // field name keys have value as array of projects to search in and field name in that project
        // array('firstname'=>array('q'=>'abc','projects'=>array(0=>array('project_id'=>'220','field'=>'fname'))))

        // refactor by project
        $projectLookup = array();
        $projects = array();
        foreach ($_POST['search'] as $searchfield => $spec) {
            foreach ($spec['projects'] as $p) {
                $projects[$p['project_id']] = null;
            }
            foreach (array_keys($projects) as $p) {
                $projectLookup[$p][$searchfield] = array();
            }
        }

        foreach ($_POST['search'] as $searchfield => $spec) {
            $term = $spec['q'];
            
            foreach ($spec['projects'] as $project) {
                $projectLookup[$project['project_id']][$searchfield]['field'] = $project['field'];
                $projectLookup[$project['project_id']][$searchfield]['q'] = $term;
            }
        }

        $results = array();

        foreach ($projectLookup as $pid => $projectSearchSpec) {
            $projectResults = $this->searchProject($pid, $projectSearchSpec);
            // format results as per rqts
            $projectResultsFormatted = array();
            foreach ($projectResults as $r) {
                $r[0] = "$pid|".$r[0];
                $projectResultsFormatted[] = array_merge(array($pid), $r);
            }
            $results = array_merge($results, $projectResultsFormatted);
        }

        return $results;
    }

    protected function searchProject($pid, $projectSearchSpec) {
        return $this->searchProjectREDCapGetData($pid, $projectSearchSpec);
//        return $this->searchProjectQuery($pid, $projectSearchSpec);
    }

    protected function searchProjectREDCapGetData($pid, $projectSearchSpec) {
        $filter = '';
        $fields = array();
        
        foreach ($projectSearchSpec as $returnField => $search) {
            $fields[] = $search['field'];
            if (trim($search['q'])!=='') {
                $filter .= 'contains(['.$search['field'].'],"'.str_replace('"','',$search['q']).'") and ';
            }
        }

        $resultArray = \REDCap::getData(array(
            'project_id' => $pid,
            'return_format' => 'array',
            'fields' => $fields,
            'filterLogic' => trim($filter, 'and ')
        ));

        $results = array();
        foreach ($resultArray as $rec => $resultRec) {
            $recData = array($rec);
            $evt = key($resultRec);
            foreach ($projectSearchSpec as $returnField => $search) {
                $field = $search['field'];
                $recData[] = (array_key_exists($field, $resultRec[$evt])) ? $resultRec[$evt][$field] : '';
            }
            $results[] = $recData;
        }
        return $results;
    }

    protected function searchProjectQuery($pid, $projectSearchSpec) {
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
                or (field_name='lastname' and value like '%smit%')
            )
        ) 
        group by project_id, record, event_id
        having group_concat(if(field_name='firstname',value,null)) like '%alice%';
        and group_concat(if(field_name='lastname',value,null)) like '%smit%';
        */
        $select = '';
        $having = '';
        $wheresub = '';
        foreach ($projectSearchSpec as $returnField => $search) {
            if (array_key_exists('field', $search)) {
                $group_concat = "group_concat(if(field_name='".\db_escape($search['field'])."',value,null))";
                $select .= ", $group_concat as ".\db_escape($returnField);
                if ($search['q']!='') {
                    $wheresub .= " (field_name='".\db_escape($search['field'])."' and value like '%".\db_escape($search['q'])."%') or ";
                    $having .= "($group_concat like '%".\db_escape($search['q'])."%') and ";
                }
            } else {
                $select .= ", null as ".\db_escape($returnField); // field not in this search project
            }
        }

        $wheresub = trim($wheresub, ' or ');
        $having = trim($having, ' and ');
        $sql =  "select record $select from redcap_data where project_id=? and record in (select record from redcap_data where project_id=? and ( $wheresub ) ) group by project_id, event_id, record having $having";
        $q = $this->query($sql, [$pid, $pid]);

        $result = array();
        while ($row = $q->fetch_assoc()) {
            $result[] = array_values($row);
        }
        return $result;
    }
}