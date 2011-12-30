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
require_once('classes/WebDriverExt.php'); 
      
class SelenBase extends WebDriverExt{
    
      private   $gridCurConfig,     // хост порт грида    
                $webdriver,     
                $testEnv;           // тестовое окружение Browser + version + platform
      public    $testPlan,          // тест план
                $storage,           // БД
                $inputData;         // входные данные
                
             //   $elementsCaps,
             //   $groupCaps;
      
      public function __construct ($qID,$testFile,$testEnv,$gridCurConfig) {
        $this->gridCurConfig = $gridCurConfig;
        $this->testEnv= array ( 'browserName' => $testEnv['browserName'], 
                                'version'=> $testEnv['version'],
                                'platform'=> $testEnv['platform'],
                                );                  
        if (!$this->storage) 
            $this->storage = new StorageBase('main.db'); // see method AddStep
        $this->testPlan->testID=$this->storage->createTest($qID);
        $this->testPlan->qID=$qID;  
        self::setTest($testFile);   
      }
      
      public function __destruct () {
         parent::__destruct();
      }      
      
      public function getElementCaps ($ename) {
          // ищем и возвращем флаг объекта
          foreach ($this->elementsInGroups as $groups_name=>$value) {
                if (is_numeric(strpos($value,$ename))) {
                    $group_return = $groups_name;
                    break;
                }
          }
          if (!isset($group_return)) 
            $group_return = 'default';
          if(isset($this->elementsCaps[$ename]) && $this->elementsCaps[$ename] == 1 ) 
            return array('element' => 1, 'group' => $group_return);   
          return array('element' => 0, 'group' => $group_return);   
      }

//  получаем  свойство группы  
      public function getGroupCaps ($group){
          if(isset($this->groupCaps[$group]) && $this->groupCaps[$group] == 1 ) 
            return 1;   
          return 0;     
      }
      
// формируем входной массив
       private function InputDataFormer($testFile) {
       $this->inputData = $testFile['Selen Test Input']; 
       require_once('model/tests/scripts/funcs/base.php');
       $selenBasicFuncs = new SelenBasicFuncs;
       foreach($this->inputData as $someInputKey => $someInputParam) {
           // ишем в значении пременной функцию
           if (substr($someInputParam,0,6) == 'FUNC::') {
               // имя функции между FUNC:: и (... 
               $my_func = substr($someInputParam,6,strpos($someInputParam, '(')-6);
               // подключаем базовые функции
               if (method_exists($selenBasicFuncs,$my_func))  {
               $my_args=substr($someInputParam,strpos($someInputParam, '(')+1,-1);    
               $my_args=explode(',',$my_args);
               $this->inputData[$someInputKey]=$selenBasicFuncs->{$my_func}($my_args);
               }  
               else throw new Exception('SELEN_INPUT_FUNC_NOT_EXISTS');
           }
           if (is_numeric(strpos($someInputParam,'^^'))) {
                    $inputParamPool = explode ('^^',$someInputParam);
                    $this->inputData[$someInputKey]= $inputParamPool[mt_rand(0,count($inputParamPool)-1)];
               }
           }   
       }    
      
      
      // создание скелета теста,     
      private function setTest($testFile) {
              $testFile=$this->parseTestFile($testFile);
              $this->testPlan->name = $testFile['Selen Test']['name'];
              $this->testPlan->script = $testFile['Selen Test']['script'];
              self::InputDataFormer($testFile);
              $this->elementsCaps =  $testFile['Selen Elements Caps'];
              $this->elementsInGroups = $testFile['Selen Elements in Group'];
              $this->groupCaps = $testFile['Selen Groups'];
              $this->testPlan->totalsuccess = (real) $testFile['Selen Test']['totalsuccess'];
              $this->storage->saveInputTestDataDB($this->testPlan->testID,$this->inputData);
              $this->testPlan->status = FALSE;    
              self::AddStep('INFO',$this->testPlan->name.' is set.');        
              return TRUE;
      }
      
      public function runTest(){
          if ($this->checkScript()) {
              self::AddStep('INFO','Test script '.$this->testPlan->script.' seems be PHP script'); 
              self::init($this->gridCurConfig['gridHost'],$this->gridCurConfig['gridPort']);  
              self::connect($this->testEnv['browserName'],$this->testEnv['version'],$this->testEnv['platform']); 
              if(is_numeric(self::returnSessionId())) 
                self::AddStep('INFO','Browser session created');            
              ##########################
              // выполняем буферизацию вывода
              ob_start();  
              try {
                  // подключаем и выполняем тестовый скрипт
                  eval('?>'.file_get_contents('model/tests/scripts/'.$this->testPlan->script));
                  $traceStats=$this->storage->returnTraceStats($this->testPlan->testID);
                  $traceStats['all']= (int) $traceStats['pass']+$traceStats['fail'];
                  if ($traceStats['all'] == 0 || $traceStats['fail'] == 0 || round($traceStats['pass']/$traceStats['all'],2) >= $this->testPlan->totalsuccess) 
                    $this->testPlan->status= TRUE;
                  else 
                    $this->testPlan->status= FALSE;
              }
              catch(Exception $e) {
                    $this->addStep('INFO','Тест прерван', FALSE);
                    $this->testPlan->status= FALSE;   
              }
              // заносим буфер и выключаем его
              $this->testPlan->testEcho = ob_get_contents(); 
              ob_end_clean(); 
              ###########################
              // завершаем тест
              $this->storage->endTest($this->testPlan->testID,$this->testPlan->status,$this->testPlan->testEcho);
              if ($this->testPlan->status == TRUE) 
                $this->storage->editQueue($this->testPlan->qID,$this->testPlan->testID,'DONE',TRUE);
              else 
                $this->storage->editQueue($this->testPlan->qID,$this->testPlan->testID,'SEEK',TRUE); 
          } 
          else {
            $this->addStep('INFO','Сценарий некорректен', FALSE);
          }
          return array('testID'=> $this->testPlan->testID, 
                        'status' => $this->testPlan->status);
      }
 
 // проверка синтаксиса сценария   
      private function checkScript(){
                $someScript=file_get_contents('model/tests/scripts/'.$this->testPlan->script);
                if (isset($someScript) && (substr ($someScript,0,2)=='<?' || substr($someScript,0,5)=='<?php') && substr($someScript,-2,2)=='?>')  
                    return TRUE;
                else 
                    return FALSE;
      }    

// парсит файл конфигурации теста     
      private function parseTestFile($testFile) {
          @$someTest=parse_ini_file('model/tests/'.$testFile,true);
          if(isset($someTest['Selen Test']) && isset($someTest['Selen Test Input']) && isset($someTest['Selen Groups']) && isset($someTest['Selen Elements Caps'])) {
            return $someTest;
          }
          else {
            throw new Exception('BAD_TEST_INI_FILE');    
            return FALSE;
          }
      }

// Вывести все шаги операций
      public function showStepTrace($is_printable=TRUE) {
          return $this->storage->showTraceBytestID($this->testPlan->testID,$is_printable);
      }
      
// Добавление шаг операции в журнал
      function AddStep($opCode,$opDetail='',$opFlag=true) {
        if (!$this->storage) 
            $this->storage = new StorageBase('main.db'); // fix, когда родитель обращается к абстрактному методу, который работает с storage
        $this->storage->addStepTrace($opCode,$opDetail,$opFlag,$this->testPlan->testID);  
        return TRUE;
      }

// выполяет паттерн       
      private function execPattern($pattern_name){
          $pattern_name = './model/tests/scripts/patterns/'.$pattern_name.'.php';
          if (file_exists($pattern_name)) {  
            require($pattern_name);            
            $this->AddStep('PATTERN','Загрузка паттерна '.$pattern_name);
          }
          else
            $this->AddStep('PATTERN','Не найден паттерн '.$pattern_name, FALSE);
      }         
}
?>