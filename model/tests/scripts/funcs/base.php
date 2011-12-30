<?php
// базовые функции для тестов
class SelenBasicFuncs {
    
// генератор доменов для теста  
public function domain_generator($args=array(
                                        //'BODY'=> 
                                        'RAND',
                                        // 'ZONE'=>
                                        'RU',
                                        //'SUBDOMAIN' => 
                                        FALSE)) {
if ($args[0] == 'RAND') {
$body_len= mt_rand(2,30);                                            
$keys = array('a','b','c','d','e','f',  
                 'g','h','i','j','k','l',  
                 'm','n','o','p','r','s',  
                 't','u','v','x','y','z',  
                 'A','B','C','D','E','F',  
                 'G','H','I','J','K','L',  
                 'M','N','O','P','R','S',  
                 'T','U','V','X','Y','Z',  
                 '1','2','3','4','5','6',  
                 '7','8','9','0','-');                                                     
$body = "";
$subdomain = "";
for($i = 0; $i< $body_len; $i++) {
    if ($i == 0 || $i == $body_len-1) $last_key_element_fix = 2; 
    else $last_key_element_fix = 1;
    // Вычисляем случайный индекс массива  
    $index = rand(0, count($keys) - $last_key_element_fix); 
    $body .= $keys[$index];  
    $index = rand(0, count($keys) - $last_key_element_fix); 
    $subdomain .= $keys[$index];   
    }   
}

if($args[1] !== 'RAND') $zone=$args['1'];

if ((bool)$args[2] == FALSE) $subdomain = "";
   
$return = $subdomain.'.'.$body.'.'.$zone;

if (substr($return,0,1) == '.') $return = substr($return,1);

return $return;
} 

}
  
?>
