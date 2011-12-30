<?php
/*
Шаблон авторизации для reg.ru
*/

self::get('http://'.$this->inputData['auth_url'].'/');

// ищем необходимые элементы
$login_field = self::findElementBy(LocatorStrategy::id, "header-login");
$password_field = self::findElementBy(LocatorStrategy::id, "header-password"); 
$submit_button = self::findElementBy(LocatorStrategy::id,"header-submit");

// Вводим данные в найденные поля из входных данных
$this->elements->$login_field->sendKeys($this->inputData['auth_login']);
$this->elements->$password_field->sendKeys($this->inputData['auth_pass']); 
$this->elements->$submit_button->click();
sleep(3);
$assert_auth_text = self::findElementBy(LocatorStrategy::id, "user_login");

if (!isset($assert_auth_text) || $this->elements->$assert_auth_text->GetText() !== $this->inputData['auth_login']) {
    $this->addStatus('Авторизация провалена',FALSE);
}
else 
   $this->addStatus('Успешная авторизация');

?>