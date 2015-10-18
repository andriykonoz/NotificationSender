<?php

include_once("service/ItemService.php");
include_once("{$_SERVER['DOCUMENT_ROOT']}/notification_sender/crud/UserRepository.php");
include_once("{$_SERVER['DOCUMENT_ROOT']}/notification_sender/entity/Email.php");

/*
 * �����-������ ��� ������ � �������������. ����� ���������� � ���� ������ ���������� ���������� ��� �����������
 * � ��������. ��������� ������� ������ ���������� ��� ��� ��������.
 */

class MailService
{
    /*
     * $limit ������������ ���������� �����, ������� ����� ��������� �� ���.
     * $base_mail ����� ��� �������� ����� (����� �����������).
     * $notification_terms ��� �������� �����������
     * $item_service ������ ��� ������ � ������������.
     * $user_repository ������� �������� ������ ������ � ����� ������ ��� �������� ������ User
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
     * ����� �������� �����������. ��� ������������� ������, ���������� � ��� ��������� � ������ ������.
     */
    public function send_mails()
    {
        try {
            $mails_info = $this->prepare_mail_info();
            foreach ($mails_info as $mail) {

                $to = $mail->email;
                $subject = "��������� ����� ���������� ����������";
                $message = "������ ����!<br/> �� ������ � ���������� ���������� �������� {$mail->days} ����.<br/>
                    ������ ����������:<br/>id = {$mail->id} <br>
                    ������ = {$mail->link}<br/>����: {$mail->title}<br/> ";

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
     * ����� ���������� ����� �������� Email, ������� ����� ������������ ��� �������� �����������.
     * ����� ����� �������� �� ������ ������ �������� Item, ���������� ������� ����� ��������� �����������
     * � �� �������� User, ������ ������������ ����������.
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