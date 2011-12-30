<?php @header("Content-Type: text/html; charset=utf-8");  /*force UTF-8 */ ?>

<a href="?page=AddTestQueue">AddTestQueue</a> | <a href="?page=showQueue">showQueue</a>  | <a href="?page=execQueue">execQueue</a>  | <a href="?page=ShowLastTests">ShowLastTests</a> | <a href="?page=show">showTraceBytestID</a> | <a href="?page=gridStats&host=192.168.217.1&port=8888">gridStats</a><br />

<?php
require_once('./classes/storage.php');


$storage = new StorageBase('main.db');

$page = $_GET['page'];

if ($page == 'AddTestQueue') {
   if (isset($_GET['testINI']))  {
       $queueId = $storage->addTestQueue ($_GET['testINI'],$_GET['startTime'],$_GET['browserName'],$_GET['browserVersion'],$_GET['platform']); 
       if ($queueId) echo 'Task Added with Id='.$queueId;
       else echo 'Task not added.';
   }
   else     {?>
   
   <form name="form" method="get" action="?page=AddTestQueue">
  <p>Имя конфига 
    <input name="testINI" type="text" id="testINI"> 
  (001.test)</p>
  <p>Время для запуска 
    <input name="startTime" type="text" id="startTime">
  (пусто, 11.12.2011 15:34:24)</p>
  <p>Браузер 
    <input name="browserName" type="text" id="browserName">
  (chrome)</p>
  <p>Версия браузера 
    <input name="browserVersion" type="text" id="browserVersion">
  (пусто)</p>
  <p>Платформа 
    <input name="platform" type="text" id="platform">
  (пусто, WINDOWS)</p>
    <input name="page" type="hidden" id="page" value="AddTestQueue">
  <p>
    <input type="submit" name="Submit" value="Подтвердить">
  </p>
</form>

   
    <?}
}

function printTable($tableArray) {
    $resultTable = '<table border="1"><tr bgcolor="#CCCCCC"> ';
    foreach ($tableArray[0] as $header => $value) {
            $resultTable.= '<td>'.$header.'</td>';     
    }
    $resultTable.='</tr>';
    foreach ($tableArray as $currLine) {
        $currLine    = (array) $currLine ;
        if (isset ($currLine['result']) && ($currLine['result'] === 'FALSE' || $currLine['result'] == FALSE ))  $lineColor = '#FF0000'; 
        elseif (isset ($currLine['opFlag']) && $currLine['opFlag'] == FALSE)    $lineColor = '#FF0000'; 
        else $lineColor = '#00CC66';
        
        $resultTable.=   '<tr bgcolor="'.$lineColor.'">';
        foreach ($currLine as $header => $value) {
            if ($header=='testID') $value='<a href="?page=show&tid='.$value.'">'.$value.'</a>';
            if (is_numeric(strpos($value,'.png'))) {
               $value = explode (' ',$value);
               $value[count($value)-1]='<a href="/model/tests/scripts/failshots/'.$value[count($value)-1].'">'.$value[count($value)-1].'</a>';
               $value = implode(' ',$value);
            }
            $resultTable.= '<td>'.$value.'</td>';
        }
        $resultTable.=' </tr>';
    }
    $resultTable.='</table>';
    
    return $resultTable;
}

if ($page == 'show' ) {
    if (isset($_GET['tid'])) 
    
    echo printTable($storage->showTraceBytestID($_GET['tid'], FALSE ));  
    else echo '?page=show&tid=X, X - testID';
}

if ($page == 'ShowLastTests') { 
     echo printTable($storage->showLastTests()); 
}

if ($page == 'gridStats') {
   require_once('./classes/grid.php'); 
   $grid = new GridBase($_GET['host'],$_GET['port']);
   echo $grid->showInfo();
 //  $grid->InstancesDetail();
}

if ($page == 'showQueue') {
   echo printTable($storage->showQueue()); 
}

if ($page == 'execQueue') {
    if (isset($_GET['qID'])) {
        
        require_once ('./classes/postprocessor.php');
        $processor = new Processor();
        $result = $processor->execTest($_GET['qID']);
        echo 'Task with qID='.$_GET['qID'].' exac';
        exit;
    }
    else {?>
            <script language="javascript" type="text/javascript">
<!--
function popitup(url) {
    newwindow=window.open(url,'name','height=200,width=300');
    if (window.focus) {newwindow.focus()}
    return false;
}

// -->
</script>

    <form name="form" method="get" action="#">
  Exec queue task with qID=
  <input name="qID" type="text" id="qID">
  <input type="button" name="Submit" value="Подтвердить" onclick="return popitup('?page=execQueue&qID=' + document.form.qID.value)">
</form>

<?}
}
?>
