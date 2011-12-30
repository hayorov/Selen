<?php

/*
  Copyright Reg.ru LLC, Alexander Hayorov

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.
 */
 
ini_set('include_path',getenv('DOCUMENT_ROOT'));

class StorageBase {
    var $db_file, // имя БД sqlite
        $db_pointer,
        $db_error; // db handle
    
    function __construct ($sqlite_base_file){
         $this->db_file='model/sqlite/'.$sqlite_base_file;
         if(!file_exists($this->db_file)) throw new Exception('NOT_FOUND_SQLITE_DB');
         $this->db_pointer=sqlite_open($this->db_file,0666,$this->db_error); 
         if(!isset($this->db_pointer)) throw new Exception('NO_SQLITE_CONNECTION');
        }

    public function addStepTrace($opCode,$opDetail,$opFlag,$testID){
        $opFlag = (bool) $opFlag;
        $sql="INSERT INTO TestTraces (testID, opCode, opDetail, opFlag, timestamp) 
        VALUES ('$testID', '$opCode', '".str_replace("'","",$opDetail)."', '$opFlag', datetime('now', 'localtime'));";
        if(!sqlite_query($sql,$this->db_pointer)) throw new Exception('TEST_STEP_TRACE_NOT_ADDED'); 
    }

    public function showTraceBytestID($testID,$is_printible=false){
        $sql = "SELECT * FROM TestTraces WHERE testID='$testID';";
        $allTraces=sqlite_fetch_all(self::doSQL($sql));
        if (!$is_printible) 
            return $allTraces;
        else {
            foreach ($allTraces as $someTrace){
                echo '['.$someTrace['timestamp'].'] '.$someTrace['opCode'].' '.$someTrace['opDetail'].' ['.(integer)$someTrace['opFlag'].']<br />';
            }
        }   
    }

    public function showTraceBySec($secs,$is_printible=false){
        $startTime=time()-$secs;
        $sql = "SELECT * FROM TestTraces WHERE timestamp>datetime('$startTime', 'unixepoch', 'localtime') ORDER BY timestamp ASC;";
        $allTraces=sqlite_fetch_all(sqlite_query($sql,$this->db_pointer),SQLITE_ASSOC);
        if (!$is_printible) 
            return $allTraces;
        else {
            foreach ($allTraces as $someTrace){
                echo '['.$someTrace['timestamp'].'] '.$someTrace['opCode'].' '.$someTrace['opDetail'].' ['.(integer)$someTrace['opFlag'].']<br />';
            }
        }
    }
    // возвращает массив своств задания
    public function showQueueData($qID){
        $sql = "SELECT * FROM queue WHERE qID='$qID';";
        $queueData=sqlite_fetch_array(sqlite_query($sql,$this->db_pointer),SQLITE_ASSOC);
        if(!isset($queueData)) $queueData = FALSE;
        return $queueData;
    }

    // создание теста
    public function createTest($qID){
        $sql = "INSERT INTO MyTest ('qID','startTime') VALUES ('$qID', datetime('now', 'localtime'))";   
        self::doSQL($sql);
        return sqlite_last_insert_rowid($this->db_pointer);
    }

    // возвращает последний трейс по ID теста
    public function showLastTrace($testID) {
        $sql = "SELECT * FROM TestTraces WHERE testID='$testID' ORDER BY timestamp DESC LIMIT 0,1;";
        self::doSQL($sql);
    }

    // закрывает (заканчивает) тест
    public function endTest($testID,$testResult,$testEcho = NULL){
        $sql = "UPDATE MyTest SET result = '$testResult' , endTime = datetime('now', 'localtime'), testOutput = '$testEcho'  WHERE testID='$testID';";   
        self::doSQL($sql);
    }

        /**
         * Показатели пополненных шагов, не включает INFO шаги
         * @return array pass=> (int) успешные шаги,  fail = (int) провальные
         */
    public function returnTraceStats($testID){
        $sql="SELECT COUNT(id) FROM TestTraces WHERE testID='$testID' AND opCode != 'INFO' AND opFlag= 1;";
        $qresult=sqlite_fetch_array(self::doSQL($sql),SQLITE_ASSOC);
        $result['pass']= (int) $qresult['COUNT(id)'];
        $sql="SELECT COUNT(id) FROM TestTraces WHERE testID='$testID' AND opCode != 'INFO' AND opFlag!= 1;";
        $qresult=sqlite_fetch_array(self::doSQL($sql),SQLITE_ASSOC);
        $result['fail']= (int) $qresult['COUNT(id)'];
        return $result;
    }

    // выполняет SQL и возвращает результат
    private function doSQL($sql){
       return sqlite_query($sql,$this->db_pointer,SQLITE_ASSOC,$this->db_error);
    }

    // Изменяет свойства задания в очереди  
    public function editQueue($qID, $testID, $queueStatus, $is_end = FALSE, $is_start = FALSE){
        $sql="UPDATE queue SET status='$queueStatus', testID='$testID' WHERE qID='$qID';";
        if ($is_end) {
            $sql="UPDATE queue SET status='$queueStatus', testID='$testID', endTime=datetime('now', 'localtime') WHERE qID='$qID';";
            
        }
        if ($is_start) 
            $sql="UPDATE queue SET status='$queueStatus', startTime=datetime('now', 'localtime') WHERE qID='$qID';"; 
        self::doSQL($sql);
        if ($is_end) self::decQueueCounter($qID);
    }

    // возвращает атрибут статуса задания из очереди
    public function returnQueueStatus ($qID){
       $sql="SELECT status FROM queue WHERE qID='$qID' LIMIT 0,1;"; 
       $return=sqlite_fetch_array(self::doSQL($sql),SQLITE_ASSOC);
       if (isset($return['status'])) return  $return['status'];
       else return NULL;
    }

    // Добавление задания в очередь
    public function addTestQueue ($testINI,$startTime='',$browserName='',$browserVersion='',$platform='') {
        if ($browserName=='') $browserName = 'chrome';
        if ($startTime=='') $startTime = time();
        else $startTime = strtotime($startTime);
        $sql = "INSERT INTO queue ('testINI','startTime','browserName', 'browserVersion', 'platform','endTime','status') 
        VALUES ('$testINI', datetime('$startTime', 'unixepoch', 'localtime'), '$browserName', '$browserVersion', '$platform', datetime(0, 'unixepoch', 'localtime'), 'NEW');";
        self::doSQL($sql);
        return sqlite_last_insert_rowid($this->db_pointer);
    }

    public function showLastTests($testCount=15) {
        $sql = "SELECT * FROM MyTest ORDER BY testID DESC LIMIT 0, $testCount;";
        return sqlite_fetch_all(self::doSQL($sql)); 
    } 

    public function showQueue($queueCount=15) {
        $sql = "SELECT * FROM queue ORDER BY qID DESC LIMIT 0, $queueCount;";  
        return sqlite_fetch_all(self::doSQL($sql));  
    }

    public function getTaskFromQueue() {
        $sql= "SELECT qID FROM queue WHERE (queue.status != 'DONE' AND queue.status != 'HOLD' AND queue.status != 'FAIL') AND (queue.startTime <  datetime('now', 'localtime')) AND (queue.counter >0) ORDER BY queue.qID DESC LIMIT 0,1;";
        return sqlite_fetch_single(self::doSQL($sql));
    } 

    // уменьшение счетчика возможных попыток
    private function  decQueueCounter($qID) {
        $sql = "UPDATE queue SET counter = counter-1, status = (case counter when 1 then 'FAIL' else status end) WHERE qID = $qID;";
        self::doSQL($sql);
    }

    // записывает массив входных данных в БД (в json) 
    public function saveInputTestDataDB($testID,$testInputData){
        $testInputData = json_encode($testInputData);
        $sql = "UPDATE MyTest SET testInput = '$testInputData' WHERE testID='$testID';"; 
        self::doSQL($sql);
    }      
}
?>