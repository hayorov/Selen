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
require_once('other/phpwebdriver/WebElement.php');

class WebElementExt extends WebElement {
    private  $parent,
             $is_dummy = FALSE;
    
    public   $ename, // имя элемента составленное из стратегии_поиска:значение_для_поиска 
             $caps;
             

             
    function __construct($parent, $element, $options, $elementName,$elementCaps){
        if(is_null($element)) $this->is_dummy = TRUE;
        else {    
        parent::__construct($parent, $element, $options);
        $this->caps = $elementCaps;
        }        
        $this->parent = $parent;
        $this->ename = $elementName;
    }


    function __call($methodname, $args ){
        if (!method_exists($this, $methodname ) ) {
            echo $methodname;
            ob_start();
            var_dump($args);    
            $print_args =' с параметрами '.ob_get_contents(); 
            ob_end_clean();
            $this->parent->storage->addStepTrace($methodname,'Действие '.$print_args.' над элементом '.$this->ename.' невозможно', FALSE ,$this->parent->testPlan->testID);
        }
    }


    public  function sendKeys($value) { 
        if (self::checkForDummy('SEND KEYS',$value)) return NULL;
        $this->parent->storage->addStepTrace(__METHOD__,'Вводим '.$value.' в '.$this->ename, TRUE ,$this->parent->testPlan->testID);
        parent::sendKeys(array($value));
    }

    public  function click() { 
        if (self::checkForDummy('CLICK')) return NULL;
        $this->parent->storage->addStepTrace('CLICK','Делаем клик на '.$this->ename, TRUE ,$this->parent->testPlan->testID);
        parent::click();
    }

// возвратит зачение элемента
    public function getValue(){
       if (self::checkForDummy('GET VALUE')) return NULL; 
       $return = parent::getValue();
       $this->parent->storage->addStepTrace('GET VALUE','Получаем значение элемента '.$this->ename, TRUE ,$this->parent->testPlan->testID);
       if (!isset($return))  $this->parent->storage->addStepTrace('GET VALUE','У элемента '.$this->ename.' отсутствует значение', FALSE ,$this->parent->testPlan->testID);
       else $this->parent->storage->addStepTrace('GET VALUE','Элемент '.$this->ename.'='.$value, TRUE ,$this->parent->testPlan->testID);
       return $return;
    }
    
    
    private function checkForDummy  ($opCode = __METHOD__,$opArgs = NULL,$fakeDummy=FALSE) {
        if($opArgs) {
            ob_start();
            print_r($opArgs);    
            $print_args =', args:'.ob_get_contents(); 
            ob_end_clean();
        }
        if ($this->is_dummy or $fakeDummy) {
            $this->parent->storage->addStepTrace($opCode,'Действие над элементом '.$this->ename.' невозможно'.$print_args, FALSE ,$this->parent->testPlan->testID); 
            $this->parent->checkForElementPriory($this->caps);
            return TRUE;
        }
        return FALSE;
    }

        public function findElementBy($locatorStrategy, $value) {
            $ename=$locatorStrategy.':'.$value;
            $request = $this->requestURL . "/element";
            $session = $this->curlInit($request);
            $args = array('using' => $locatorStrategy, 'value' => $value);
            $postargs = json_encode($args);
            $this->preparePOST($session, $postargs);
            $response = curl_exec($session);
            $json_response = json_decode(trim($response));
            if (!$json_response) {
                $this->elements->$ename = new WebElementExt($this, NULL, NULL, $ename, NULL);
            }
            $this->handleResponse($json_response);
            $element = $json_response->{'value'};
            return new WebElement($this, $element, null,$ename);
        }    
      
}
?>