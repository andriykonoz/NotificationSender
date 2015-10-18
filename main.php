<?php

include("service/MailService.php");
include("crud/Connector.php");
include_once("crud/ItemRepository.php");
include_once("crud/BufferRepository.php");

/*
 * $limit максимальное количество писем, которые можна отправить за раз.
 * $base_mail адрес для обратной связи (адрес отправителя).
 * $notification_terms дни отправки уведомлений
 * &launch_interval интервал, с которым скрипт будет запускаться на сервере (в минутах).
 */
$limit = 100;
$base_mail = 'stub@gmail.com';
$notification_terms = array(1,2,5,10);
$launch_interval = 15;

$mail_sender = new MailService($limit, $base_mail, $notification_terms, $launch_interval);
$mail_sender->send_mails();
