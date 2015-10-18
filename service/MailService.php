<?php

include_once("service/ItemService.php");
include_once("{$_SERVER['DOCUMENT_ROOT']}/notification_sender/crud/UserRepository.php");
include_once("{$_SERVER['DOCUMENT_ROOT']}/notification_sender/entity/Email.php");

/*
 * Класс-сервис для работы с уведомлениями. Класс обьеденяет в себе логику подготовки информации для уведомлений
 * и отправки. Настройка обьекта класса происходит при его создании.
 */

class MailService
{
    /*
     * $limit максимальное количество писем, которые можна отправить за раз.
     * $base_mail адрес для обратной связи (адрес отправителя).
     * $notification_terms дни отправки уведомлений
     * $item_service сервис для работы с обьявлениями.
     * $user_repository который содержит логику работы с базой данных для обьектов класса User
     */
    private $mail_limit;
    private $base_email;
    private $notification_days;

    private $item_service;
    private $user_repository;

    public function __construct($mail_limit, $base_email, $notification_days, $launch_interval)
    {
        $this->mail_limit = $mail_limit;
        $this->base_email = $base_email;
        $this->notification_days = $notification_days;
        $this->item_service = new ItemService($notification_days, $launch_interval);
        $this->user_repository = new UserRepository();
    }

    /*
     * Метод отправки уведомлений. При возникновении ошибок, информация о них заносится в журнал ошибок.
     */
    public function send_mails()
    {
        try {
            $mails_info = $this->prepare_mail_info();
            foreach ($mails_info as $mail) {

                $to = $mail->email;
                $subject = "Истечение срока публикации обьявления";
                $message = "Добрый день!<br/> До снятия с публикации обьявления осталось {$mail->days} дней.<br/>
                    Детали обьявления:<br/>id = {$mail->id} <br>
                    ссылка = {$mail->link}<br/>тема: {$mail->title}<br/> ";

                $headers = "From: {$this->base_email}" . "\r\n" .
                    "Reply-To: {$this->base_email}" . "\r\n";
                mail($to, $subject, $message, $headers);

                echo "<br>{$to}<br>" . "{$subject}" . "<br>{$message}<br>";
            }
        } catch (Exception $e) {
            Logger::log_error($e);
        }
    }

    /*
     * Метод возвращает масив обьектов Email, которые будут использованы при отправке уведомлений.
     * Даный масив строится на основе масива обьектов Item, владельцам которых нужно отправить уведомления
     * и на обьектах User, которіе соответсвуют владельцам.
     */
    private function prepare_mail_info()
    {
        $mails_info = array();
        $items = $this->item_service->prepare_items_for_mail($this->mail_limit);
        echo "<br/> Will be sended" . count($items) . " mails <br/>";
        foreach ($items as $item) {
            $user = $this->user_repository->select_by_id($item->user_id);
            $mails_info[] = new Email($item, $user);
        }
        return $mails_info;
    }


}