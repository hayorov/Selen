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
require_once('other/phpwebdriver/WebDriver.php');
require_once('WebElementExt.php');

abstract class WebDriverExt extends WebDriver {
    public $elements;
    
    public function __construct(){
        
    }
    
// инициируем WebDriver, он сединяется с Grid Hub
    public function init($host,$port){
    parent::__construct($host,$port);
    $this->addStep('INFO','WebDriver init proccess');
    }

    public function connect($browserName='chrome',$version='',$platform=NULL){
    if ($platform==NULL) parent::connect($browserName,$version);
    else parent::connect($browserName,$version,array('platform'=>$platform));
    $this->addStep('INFO','Браузер '.$browserName.' '.$version.' on '.$platform);
}
    
    public function __destruct() {
        self::close();
        $this->AddStep('INFO','Grid disconnected & browser session closed'); 
    }

// возвращает ID сессии или false когда её нет
    public function returnSessionId(){
        $SessionId=explode('/wd/hub/session/',$this->requestURL);
        $SessionId=$SessionId[1];
        if(is_numeric($SessionId))
            return $SessionId;
        else 
            return false;
    }
    
    /**
     * Search for an element on the page, starting from the document root. 
     * @param string $locatorStrategy
     * @param string $value
     * @return WebElement found element
     */
    public function findElementBy($locatorStrategy, $value) {    
        $ename =$locatorStrategy.':'.$value; 
        $this->addStep('SEARCH ELEMENT','Поиск '.$locatorStrategy.':'.$value); 
        $elementCaps=$this->getElementCaps($ename);
        try {
        $request = $this->requestURL . "/element";
        $session = $this->curlInit($request);
        $args = array('using' => $locatorStrategy, 'value' => $value);
        $postargs = json_encode($args);
        $this->preparePOST($session, $postargs);
        $response = curl_exec($session);
        $json_response = json_decode(trim($response));
        if (!$json_response) {
            $this->elements->$ename = new WebElementExt($this, NULL, NULL, $ename,NULL);
        }
        $this->handleResponse($json_response);
        $element = $json_response->{'value'};
        $this->elements->$ename = new WebElementExt($this, $element, null,$ename,$elementCaps);
        }
        catch (Exception $e) {
           $this->addStep('SEARCH ELEMENT',$locatorStrategy.' по '.$value.' не найден', FALSE);
           self::checkForElementPriory($elementCaps);
           $this->elements->$ename = new WebElementExt($this, NULL, NULL, $ename, NULL);
        }
        return  $ename;
    }    
    
    /**
     * Search for an element on the page, starting from the document root. 
     * @return WebElement found element
     */
    public function findActiveElement() {
        $this->addStep('SEARCH ACTIVE ELEMENT','Поиск активного элемента');
        try{
        $request = $this->requestURL . "/element/active";
        $session = $this->curlInit($request);
        $this->preparePOST($session, null);
        $response = curl_exec($session);
        $json_response = json_decode(trim($response));
        if (!$json_response) {
            return null;
        }
        $this->handleResponse($json_response);
        $element = $json_response->{'value'};
        return new WebElementExt($this, $element, null);
        }
        catch (Exception $e) {
            $this->addStep('ACTIVE ELEMENT','Активный элемент не найден', FALSE,'ACTIVE_ELEMENT:NULL');
            return NULL;
        }
    }

    
    /**
     *     Search for multiple elements on the page, starting from the document root. 
     * @param string $locatorStrategy
     * @param string $value
     * @return array of WebElement
     */
    /*    public function findElementsBy($locatorStrategy, $value) {
        $request = $this->requestURL . "/elements";
        $session = $this->curlInit($request);
        //$postargs = "{'using':'" . $locatorStrategy . "', 'value':'" . $value . "'}";
        $args = array('using' => $locatorStrategy, 'value' => $value);
        $postargs = json_encode($args);
        $this->preparePOST($session, $postargs);
        $response = trim(curl_exec($session));
        $json_response = json_decode($response);
        $elements = $json_response->{'value'};
        $webelements = array();
        foreach ($elements as $key => $element) {
            $webelements[] = new WebElementExt($this, $element, null);
        }
        return $webelements;
    }
*/    
    /**
     * Добавляет запись о выполнении команды (шаге)
     * @param string $opDetail
     * @param string $opCode
     * @param string $opFlag
     * @return TRUE
     */
    abstract function addStep($opCode,$opDetail='',$opFlag=true);
     
// Перехват команд для логирования
    public function setCookie($name, $value, $cookie_path='/', $domain='', $secure=false, $expiry='') {  
        parent::setCookie($name, $value, $cookie_path='/', $domain='', $secure=false, $expiry='');    
        $this->addStep('SET COOKIE',$name.'='.$value,TRUE);
        }    

    public function deleteCookie($name) {
        parent::deleteCookie($name); 
        $this->addStep('DEL COOKIE','С name='.$name,TRUE);
        }

    public function refresh() {
        parent::refresh();
        $this->addStep('REFRESH PAGE','Страница '.parent::getCurrentUrl(),TRUE);  
    }
    
    public function forward() {
        parent::forward();
        $this->addStep('BROWSER FORWARD','Навигация в браузере ДАЛЕЕ',TRUE);  
    }
    
    public function back() {
    parent::back();
    $this->addStep('BROWSER BACK','Навигация в браузере НАЗАД',TRUE);  
    }
    
    public function getActiveElement() {
        $return = parent::getActiveElement();
        $this->addStep('SEARCH ACTIVE ELEMENT','Поиск активного элемента',TRUE);
        if(!isset($return)) $this->addStep('ACTIVE ELEMENT','Нет активного элемента', FALSE);
        else $this->addStep('ACTIVE ELEMENT','Активный элемент '.$return, TRUE);
        return $return;
    }
        
    public function get($url) {   
        parent::get($url);
        $this->addStep('OPEN URL',$url);
    }
    
    public function close() {   
        parent::close();
        $this->addStep('CLOSE','Браузер закрыт');
    }
    
    public function getAllCookies() {
        $this->addStep('GET ALL COOKIES','Получить все COOKIES');
        return parent::getAllCookies();
    }
    
    public function deleteAllCookies() {
        $this->addStep('DEL ALL COOKIES','Очистить все COOKIES');
        return parent::deleteAllCookies(''); 
    }
    
    public function addStatus($opDesc,$opFlag = TRUE) {
        $this->addStep('STATUS',$opDesc, $opFlag);  
        if(!$opFlag) {
            $failshot_name=$this->testPlan->qID.'_'.$this->testPlan->testID.'_'.time().'.png';
            $this->getScreenshotAndSaveToFile('./model/tests/scripts/failshots/'.$failshot_name);
            $this->addStep('screenshot',$opDetail='Cоздан снимок с именем '.$failshot_name);
            throw new Exception('ACT_WITH_PRIO_ELEM_FAIL');   
        }
    }
  
    abstract function getElementCaps($ename);  
    
    public function checkForElementPriory($elementCaps){
        if($elementCaps['element'] == 1) {
            $this->addStep('INFO','Действие с важным элементом '.$ename.' провалено', FALSE);   
            throw new Exception('ACT_WITH_PRIO_ELEM_FAIL');      
        }  
        if ($this->getGroupCaps ($elementCaps['group']))  {
            $this->addStep('INFO','Действие с элементом '.$ename.' в важной группе провалено', FALSE);   
            throw new Exception('ACT_WITH_PRIO_GROUP_FAIL');      
        }
    }     
}
?>