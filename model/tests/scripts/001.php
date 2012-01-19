<?php
/*
Тест заказ услуги хостинга для существующего домена
*/

// осуществляем авторизацию
$this->execPattern('regru_auth_header');

// открываем первую страницу мастера
self::get('http://'.$this->inputData['start_url']);

// проверяем её доступность
if ($this->getTitle() !== $this->inputData['hosting_page_title']) $this->addStatus('Страница недоступна', FALSE);

// выбираем тариф
$tariff =self::findElementBy(LocatorStrategy::cssSelector, "form[name=".$this->inputData['tariff']."_order_form] > div > input.image_choose");
$this->elements->$tariff->click();

// выбираем период
$period = self::findElementBy(LocatorStrategy::xpath, "//input[@name='period' and @value='".$this->inputData['period']."']"); 
$this->elements->$period->click();

// выбираем автопродление
if (!(bool)$this->inputData['autopay']) {
           $autoplay=self::findElementBy(LocatorStrategy::cssSelector, "span.switch"); 
           $this->elements->$autoplay->click();  // костыль-fix делающий фокус на объект
           $this->elements->$autoplay->click();  // а теперь кликаем
         } 
         
// выбираем панель хостинга
$panel = self::findElementBy(LocatorStrategy::id, $this->inputData['panel']);
$this->elements->$panel->click(); 

// Вводим существующий домен
$dname=self::findElementBy(LocatorStrategy::id, "dname"); 
$this->elements->$dname->sendKeys($this->inputData['dname_value']); 

// подтверждаем и переходим на вторую страницу мастера
$submit_button = self::findElementBy(LocatorStrategy::id, "order_hosting_button"); 
$this->elements->$submit_button->click();  

// проверяем что перешли на вторую страницу мастера
if (self::findElementBy(LocatorStrategy::id, 'antispam_for_'.$this->inputData['dname_value'])) 
    $this->addStatus('Вторая страница мастера');
else 
    $this->addStatus('Вторая страница мастера недоступна', FALSE);
    
// подключить защиту от спама?
if ((bool) $this->inputData['is_spam_on']) {
$is_spam_on=self::findElementBy(LocatorStrategy::id, "srv_antispam"); 
$this->elements->$is_spam_on->click(); 
}

// что делалть со спамом?
$spam_action=self::findElementBy(LocatorStrategy::name, 'antispam_type_'.$this->inputData['dname_value']); 
$spam_action_value=$this->elements->$spam_action->findOptionElementByValue($this->inputData['spam_action']);
$spam_action_value->click();

// подключить доп IP?
if ((bool) $this->inputData['is_ip_on']) {
$is_ip_on=self::findElementBy(LocatorStrategy::id, "srv_addip"); 
$this->elements->$is_ip_on->click(); 
}

// выбераем время ip
$ip_period = self::findElementBy(LocatorStrategy::xpath, "//input[@name='addip_period' and @value='".$this->inputData['ip_period']."']");       
$this->elements->$ip_period->click(); 

// выбираем количество ip
$ip_count = self::findElementBy(LocatorStrategy::id, "ip_address_num"); 
$ip_count_value=$this->elements->$ip_count->findOptionElementByValue($this->inputData['ip_count']);
$ip_count_value->click();

// переходим на третью страницу мастера
$submit_button_2page = self::findElementBy(LocatorStrategy::id, "order_hosting_button"); 
$this->elements->$submit_button_2page->click();

// данные уже задана контактные, подтверждаем
$submit_button_3page = self::findElementBy(LocatorStrategy::id, "order_hosting_button"); 
$this->elements->$submit_button_3page->click();


//проверка на существование подобной усулги
if (is_numeric(strpos(self::getCurrentUrl(), $this->inputData['pre_orded_page'])))
    $this->addStatus('Хостинг для '.$this->inputData['dname_value'].' уже заказан', FALSE);

    // проверяем что мы находимся в корзине и делаем заказ в офисе наличными
$this->execPattern('regru_do_orded_in_office');

?>