<?php
// проверяем что мы находимся в корзине
if (!is_numeric(strpos(self::getCurrentUrl(), $this->inputData['shop_card_url'])))
    $this->addStatus('Корзина недоступна', FALSE);
else 
   $this->addStatus('Находимся в корзине');
    
// переходим к оплате    
$pay_choose_button = self::findElementBy(LocatorStrategy::id, "pay_choose_button"); 
$this->elements->$pay_choose_button->click();
sleep(5);
// выбираем способ оплаты в офисе
$pay_in_office_button = self::findElementBy(LocatorStrategy::xpath, "//div[@id='content']/div/ul[4]/li/a/b"); 
$this->elements->$pay_in_office_button->click();

// проверяем что заявка принята
$is_orded=self::findElementBy(LocatorStrategy::cssSelector, 'h1[class="thin_blue"]');  
if (!is_numeric(strpos($this->elements->$is_orded->getText(), $this->inputData['order_end_marker'])))  $this->addStatus('Заказ не оформлен', FALSE);
else 
   $this->addStatus('Заказ оформлен');
   ?>