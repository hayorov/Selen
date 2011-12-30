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
 
class GridBase {
   private      $gridName,                   // название grid
                $gridHost,$gridPort,         // хост:порт grid
                $gridStatus,                 // статусы: 
                                             // 0 No grid found
                                             // 1 No webdrivers avalible
                                             // 2 Grid avalible
                $node,                       // массив нод
//              $node->nodeId                // нода
//                            ->instances    // число возможных инстансов
                $queue;                      // массив очереди к браузерам
   
   public function __construct($gridHost='127.0.0.1',$gridPort=8888) {
       $this->Set($gridHost,$gridPort);
       $this->gridName = null;
       $this->gridStatus = 0;
       // запускам метод сбора информации о созданном Grid
       $this->initGrid();
   }
   
// задаем параметры для созданяи grid
   private function Set($gridHost,$gridPort) {
       $this->gridHost=$gridHost;
       $this->gridPort=$gridPort;
   }
   
// задаем имя grid  и меняем статус на существующий  
   private function addName($gridName=null) {
       $this->gridName=$gridName;
       if (is_string($this->gridName)) $this->gridStatus=1;
   }
   
// справочная информация о grid в читаемом виде для сторонних запросов  
   public function showInfo($html_br=true){
       if ($html_br) $br='<br />';
       else "\n";
      if($this->gridHost != 0) {
          $return=$this->gridName.' # '.$this->gridHost.':'.$this->gridPort .' @ '.$this->showGridtextStatus().$br;
      if($this->gridHost > 1)   {
          $return .='Nodes: '.$this->countNodes().$br;
          $return .='Instances: '.$this->usedInstances().'/'.$this->countInstances().$br;
          $return .='Grid queue: '.$this->countQueue().$br;
           }
      }
      else {
          $return = null;
      }
      return $return;
   }

// возвращает число запущенных node на grid
   public function countNodes() {
     $countNodes=0;
     $pointer=0;
     if (isset($this->node->$pointer)) {
         foreach($this->node as $somenode) {
           $countNodes++;  
         }
      } 
      return $countNodes;
   } 
   
// возвращает количество возможных одновременных запусков на Grid
   public function countInstances(){
       $countInstances=0;
       $pointer=0;
       if(isset($this->node->$pointer->instances)) {
        foreach($this->node as $somenode) {
        foreach($somenode as $param=>$value) {
            if ($param=='instances') $countInstances=$countInstances+$value;
                 }
            }
       }
   return        $countInstances;
   }

// возвращает число запущенных браузеров
   public function usedInstances(){
       $usedInstances=0;
     if (isset($this->node->{0}->browser->{0}->status) && $this->gridStatus>1)
     foreach ($this->node as $someNode) {
         foreach ($someNode->{'browser'} as $SomeInstanceValue) {
             if ($SomeInstanceValue->{'status'} == 1) $usedInstances++;
         }
     }
   return $usedInstances;
   }  

// возвращает читаемый статус grid
   public function showGridtextStatus () {
       switch($this->gridStatus) {
           case 0: return 'No grid found';
           case 1: return 'Grid found without instances';
           case 2: return 'Grid found with instance(s)';
       }
       
   }
   
// возвращает весь grid как массив для отладок !!!
   public function returnInfoArray(){
       return (array) $this;
   }
   
// первичное исследование Grid
   private function initGrid () {
      require_once('./classes/other/simple_html_dom/simple_html_dom.php');
      // получаем html с данными о состоянии grid
      @$concole_page=file_get_html('http://' . $this->gridHost . ':' . $this->gridPort.'/grid/console');
      if ($concole_page){
      // ищем название grid
      foreach($concole_page->find('h1') as $e) {
       if (is_string($e->plaintext)) { 
           $this->addName($e->plaintext);
           break;
           }
      }
    
      if ($this->gridStatus == 1) {
        
        foreach($concole_page->find('fieldset') as $e) {
         if (is_string($e->children(0)->plaintext)) { 
             if (!isset($nodeId)) $nodeId=0;
             if (is_numeric($e->children(3)->plaintext)) {
                 $this->node->$nodeId->instances=$e->children(3)->plaintext; // число возможных одновременных запросов
                 $this->node->$nodeId->name=$e->children(0)->plaintext; // название node
             }
             else { 
                 $this->node->$nodeId->instances = 0;
             }
         }
           if (isset($this->node->$nodeId->name) && $this->node->$nodeId->instances>0) {
            $nodeinfo=explode("\n",$e->plaintext);
            if (strpos($nodeinfo[0],'wd/hub') !== false ) {
                $this->node->$nodeId->url=substr($nodeinfo[0],strpos($nodeinfo[0],'http://')); // url ноды
               
               foreach ($e->children as $somebro) {
                   if (isset($somebro->title)) {
                       if (!isset($browserId)) $browserId=0;
                      
                       $this->node->$nodeId->browser->$browserId->platform = null;
                       $this->node->$nodeId->browser->$browserId->browserName = null;
                       $this->node->$nodeId->browser->$browserId->version = null;
                       if(isset($somebro->class)) $this->node->$nodeId->browser->$browserId->status=1;
                       else $this->node->$nodeId->browser->$browserId->status=0;
                      
                      $tmp=substr($somebro->title,1,-1); // избавляемся от { }
                      $tmp=explode(', ',$tmp); // получаем параметр=значение
                
                    foreach($tmp as $e) {
                    $tmpchild=explode('=',$e);
                        switch ($tmpchild[0]) {
                            case 'platform'     : $this->node->$nodeId->browser->$browserId->platform = $tmpchild[1];
                            case 'browserName'  : $this->node->$nodeId->browser->$browserId->browserName =$tmpchild[1];
                            case 'version'      : $this->node->$nodeId->browser->$browserId->version = $tmpchild[1];
                        }
        
                     }
                       $browserId++;
                  }
               }
               unset($browserId);
            }
            unset($nodeinfo);
        } 
        @$nodeId++;
      }
    }
      
   if(isset($nodeId)) {
         $queueId=0;   
         foreach($concole_page->find('li') as $e) {
            $this->queue->$queueId=substr($e->plaintext,1,-1);
            $queueId++;
                 }
             }
             $pointer=0;
   if ($this->countInstances() > 0) {
        $this->gridStatus=2;
         }
     
        } 
     }
     
// возвращает длину очереди
    public function countQueue () {
      $countQueue=0;
      if(isset($this->queue)) $countQueue=count($this->queue);  
      return $countQueue;
    }
    
// возвращает true если можно сделать запрос grid, в противном false
    public function isAvalible () {
        $return = false;
/*    условия доступности grid :
        - код статуса не ниже Grid found with instance(s)
        - есть доступные инстансы
        - нет очереди к grid 
*/    
    if (($this->gridStatus>1) && ($this->countInstances()-$this->usedInstances() > 0) && ($this->countQueue() == 0))  $return = true;
    return $return;
    } 
    
// возвращаем данные о хосте и порте в виде массива    
    public function connectInfo(){
        return array($this->gridHost,$this->gridPort);
    }  
    
    public function InstancesDetail($html_br = TRUE) {
        
    if (isset($this->node->{0}->browser->{0}->status) && $this->gridStatus>1) {
     foreach ($this->node as $someNode) {
         foreach ($someNode->{'browser'} as $SomeInstanceValue) {
             if (!isset($return[$SomeInstanceValue->{'browserName'}]['free'])) $return[$SomeInstanceValue->{'browserName'}]['free'] = 0;
             if (!isset($return[$SomeInstanceValue->{'browserName'}]['used'])) $return[$SomeInstanceValue->{'browserName'}]['used'] = 0;
             if ($return[$SomeInstanceValue->{'status'}]['free'] == 0) $return[$SomeInstanceValue->{'browserName'}]['free']++;
             else $return[$SomeInstanceValue->{'browserName'}]['used']++;
             
            
             
         }
     } 
     
  if ($html_br)
      foreach($return as $browserName=>$params) {
          $total=$params['used']+$params['free'];
      echo $browserName.': '.$params['used'].'/'.$total.'<br />';
    }
    else return json_encode($return); 
  }
     else return NULL;
    }    
}
?>