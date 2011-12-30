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
require_once('classes/grid.php'); 
require_once('classes/selen.php');
require_once('classes/storage.php');

class Processor {
    private     $gridGlobalConf,
                $grid,
                $storage;
    
    
// читаем глобальный конфиг и создаем экземпляр класса     
    public function __construct() {
    // создаем экземпляр класса Storage
    $this->storage = new StorageBase('main.db');
    self::getGlobalGridConf();
    $gridCurConfig = self::GridConnectInfo();
    $this->grid = new GridBase($gridCurConfig['gridHost'],$gridCurConfig['gridPort']);
    }

// получаем информацию о Gid из конфигурации
    private function getGlobalGridConf() {
    $gridGlobalConf=parse_ini_file('model/conf/grid_global.ini', TRUE);    
    if (isset($gridGlobalConf['Grid Global'])) {
        $this->gridGlobalConf = $gridGlobalConf;
        return true;
    }
    else {
        throw new Exception('NO_GLOBAL_CONF_GETTED');
        return false; 
        }
    }
    
//  заглушка метод возвращающий хост порт конкретного грида 
    public function GridConnectInfo() {
        $this->getGlobalGridConf();
        $tmpPointer='Grid1';
        return array(   'gridHost'=> $this->gridGlobalConf[$tmpPointer]['gridHost'],
                        'gridPort'=> $this->gridGlobalConf[$tmpPointer]['gridPort'],    
                        );
   }

//  явное выполнение задания 
    public function execTest($qId) {
          
        $testData=$this->storage->showQueueData($qId);
        if ($testData == FALSE) throw new Exception('QUEUE_NOT_EXIST');
        $this->storage->editQueue($qId,'','HOLD', FALSE, TRUE);
        if (!$this->grid->isAvalible()) throw new Exception('GRID_NOT_AVALIBLE'); 
        $selen = new SelenBase($qId,$testData['testINI'], array('browserName' => $testData['browserName'], 
                                                                'version'=> $testData['browserVersion'], 
                                                                'platform' => $testData['platform']), 
        self::GridConnectInfo());
        $return = $selen->runTest();
        // если после завершения теста по заданию статус задания остался HOLD, меняем его на SEEK
        if ($this->storage->returnQueueStatus($qId) == 'HOLD')   
            $this->storage->editQueue($qId, NULL,'SEEK', TRUE, FALSE);
        return $return;
    }

//  выводит информацию об используемом Grid   
    public function infoGrid($is_print=TRUE){
        if ($is_print)  echo $this->grid->showInfo();
        else return $this->grid->showInfo();
    }
    
}
?>
