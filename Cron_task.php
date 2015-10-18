<?php
/*
 * $limit ������������ ���������� �����, ������� ����� ��������� �� ���.
 * $base_mail ����� ��� �������� ����� (����� �����������).
 * $notification_terms ��� �������� �����������
 * &launch_interval ��������, � ������� ������ ����� ����������� �� ������� (� �������).
 */
$limit = 100;
$base_mail = 'stub@gmail.com';
$notification_terms = array(1,2,5,10);
$launch_interval = 15;

$mail_sender = new MailService($limit, $base_mail, $notification_terms, $launch_interval);
$mail_sender->send_mails();

//----------------------------------------------------------------------------------------------------------------------

/*
 * ����� ��������� ����� ������ ��������� ������.
 */

class Logger
{
    /*
     * ����������-��������.
     */
    public static function log_error(Exception $e)
    {
        //TODO �������� ������ ����������.
//        $date = date('Y-m-d-H-i-s');
//        echo "ERROR [{$date}]: {$e->getMessage()}<br/>";


    }
}

//----------------------------------------------------------------------------------------------------------------------
//-------------------------�������--------------------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------

/*
 * �����-������. ���� ������������� ������ ��� ������ � ��������� Item �� ������ ������� ����������� ��������.
 */

class ItemService
{
    /*
     * $item_repository �������� ������ ������ � ����� ������ ��� �������� Item.
     * $buffer_repository �������� ������ ������ � ����� ������ ��� ����������� �������� Item.
     * $notification_days ��� ��� �������� �����������.
     */
    private $item_repository;
    private $buffer_repository;
    private $notification_days;

    public function __construct($notification_days, $launch_interval)
    {
        $this->item_repository = new ItemRepository($launch_interval);
        $this->buffer_repository = new BufferRepository();
        $this->notification_days = $notification_days;
    }

    /*
     * ����� ������� �������� ����������, ��������� �������� ����������� ���������.
     * �������� ������ ������������ �� ������ � ��������, ����� � �������� ����������� ���������
     * ������ ����������, ��� ��������� ��������� �� ���.
     * ����� ���������� ������������ �� ��������� ��������� ����� - ������������, ���������� ������� ����� ���������
     * �����������, ��������� �� ����������, ���� ��������� ���������� ������� ����� � ��������� ��
     * (now - $launch_interval) �� (now).
     *
     * �������� ������ ������:
     *  ����������� �����.
     *      ���� ���������� ���������� � ������ ���������� ������, �� ���������� ����� ���� �� ����������.
     *  ���������� ���������� � ������.
     *      ���� � ������ ��������� ������ ����������, ��� ����� ��������� �� ���, �� ���������� ������ �� ����������,
     *      ������� ����� ����������.
     *      � ����� �������� �� ����������, ������� ���� ������� ����� ������ �� ����� �������� ������� � �� ����
     *      ���������.
     *  ���������� ����� ����������.
     *      ���������� ����������, ���������� ������� ����� ��������� �����������, � ������� ����������� � ������.
     *      ���� �� �������� ��� ������� ��� ����������, �� ��, ������� ��������� ����� ������, ����������� � ������.
     *  ��������� ����� �� �������� ����������.
     */
    public function prepare_items_for_mail($max_limit)
    {
        $timestamp = time();
        $items_for_mails = array();
        $this->update_buffer($timestamp);
        $items_for_mails = $this->fetch_items_from_buffer($items_for_mails, $max_limit);
        $items_for_mails = $this->fetch_new_items($timestamp, $items_for_mails, $max_limit);
        $this->clean_buffer($items_for_mails);

        return $items_for_mails;
    }

    /*
     * ����� ��������� ���������� � ������� ���� ��� ��������������� ����������.
     * ������������, ��� ��� �������, ������� ����� ������� ����������� � ���������� � ������ ����� ���������
     * ���������� �����.
     */
    private function update_buffer($timestamp)
    {

        foreach ($this->notification_days as $day) {
            $select_timestamp = $timestamp + 60 * 60 * 24 * $day;
            $items = $this->item_repository->select_buffered_items_by_timestamp($select_timestamp);
            foreach ($items as $item) {
                $this->buffer_repository->update_item($item, $day);
            }
        }
    }

    /*
     * ����� ������� ���������� Item � ������ �� �������� ������ $max_limit
     */
    private function fetch_items_from_buffer($items, $max_limit)
    {
        $buffered_items = $this->item_repository->select_all_buffered_items();
        foreach ($buffered_items as $item) {
            $items[] = $item;
            if (count($items) == $max_limit) {
                return $items;
            }
        }
        return $items;
    }

    private function clean_buffer($items)
    {
        foreach ($items as $item) {
            $this->buffer_repository->delete_item($item);
        }
    }

    /*
     * ���������� ����������, ���������� ������� ����� ��������� �����������, � ������� ����������� � ������.
     * ���� �� �������� ��� ������� ��� ����������, �� ��, ������� ��������� ����� ������, ����������� � ������.
     */
    private function fetch_new_items($timestamp, $items, $max_limit)
    {
        $selected_items = array();
        foreach ($this->notification_days as $day) {
            $select_timestamp = $timestamp + 60 * 60 * 24 * $day;
            $day_items = $this->item_repository->select_published_items($select_timestamp);
            foreach ($day_items as $item) {
                $item->days_left = $day;
                $selected_items [] = $item;
            }
        }
        $selected_items_size = count($selected_items);
        $pointer = 0;
        while (count($items) < $max_limit && $pointer < $selected_items_size) {
            $items[] = $selected_items[$pointer++];
        }

        $this->push_to_buffer(array_slice($selected_items, $pointer));

        return $items;
    }

    private function push_to_buffer($items)
    {
        foreach ($items as $item) {
            $this->buffer_repository->add_item($item, $item->days_left);
        }
    }

}

//----------------------------------------------------------------------------------------------------------------------

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

//----------------------------------------------------------------------------------------------------------------------
//------------------------�������� (������ ������� ��� ������)----------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------

/*
 * ����������� �������, ������� ���������� � ���� ����� ���� ���� ��������� ���������.
 * �� ����� ������������� � ���� ������ � �� ������������� ��� ����������������� �������������.
 * ��� �������� ������ ������������� �� ������ ������.
 */
abstract class Entity {
    protected $id;

    public function __get($property){
        if(property_exists($this, $property)){
            return $this->$property;
        }
    }

    public function __set($property, $value){
        if(property_exists($this, $property)){
            $this->$property = $value;
        }

        return $this;
    }
}

//----------------------------------------------------------------------------------------------------------------------

/*
 * �������� ������������ ����� ����������.
 * � ���� ������ ���������� ��� ������ ������� items.
 */
class Item extends Entity {
    protected $user_id;
    protected $status;
    protected $title;
    protected $link;
    protected $descr;
    protected $publicated_to;
    protected $days_left;


    public function __construct($id, $user_id, $status, $title, $link, $descr, $publicated_to){
        $this->id = $id;
        $this->user_id = $user_id;
        $this->status = $status;
        $this->title = $title;
        $this->link = $link;
        $this->descr = $descr;
        $this->publicated_to = $publicated_to;
    }
}

//----------------------------------------------------------------------------------------------------------------------

/*
 * �������� ������������ ����� ������������.
 * � ���� ������ ���������� ��� ������ ������� users
 */
class User extends Entity{
    protected $email;

    public function __construct($id, $email){
        $this->id = $id;
        $this->email = $email;
    }
}

//----------------------------------------------------------------------------------------------------------------------

/*
 * �������� ������� �������� � ���� ���������� ��� �������� �����������.
 * �� ����� ������������� � ���� ������. ��������� �� ������ ���� �������� Item � User.
 */
class Email extends Entity {
    protected $link;
    protected $title;
    protected $days;
    protected $email;

    public function __construct(Item $item, User $user){
        $this->id = $item->id;
        $this->link = $item->link;
        $this->title = $item->title;
        $this->days = $item->days_left;
        $this->email = $user->email;
    }

}

//----------------------------------------------------------------------------------------------------------------------
//------------------------������� ������ � ����� ������-----------------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------

/*
 * ����� ����������� �������� ���������� � ����� �����, ��� ��������� ��������� ������������ ����������.
 *
 */
class Connetor {

    public static function get_connection(){
        $dbtype = 'mysql';
        $host = 'localhost';
        $dbase = 'add_base';
        $user = 'root';
        $pass = 'root';

        return new PDO("{$dbtype}:host={$host};dbname={$dbase}", $user,$pass);
    }
}

//----------------------------------------------------------------------------------------------------------------------

/*
 * ����� ������������� ����� ������� ��� ������ ������ � ����� ������ ��� �������� Item.
 */
class ItemRepository
{
    /*
     * $launch_interval �������� ����� ��������� �������.
     */
    private $launch_interval;

    public function __construct($launch_interval){
        $this->launch_interval = $launch_interval;
    }
    /*
     * ���������� ������� ������������� ����������, ������� ��������� � �������� ����������� ����������.
     * ����� ���������� ������������ �� ��������� ��������� ����� - ������������, ���������� ������� ����� ���������
     * �����������, ��������� �� ����������, ���� ��������� ���������� ������� ����� � ��������� ��
     * (now - $launch_interval) �� (now).
     */
    public function select_published_items($timestamp){
        $begin_data = date('Y-m-d H:i:s', $timestamp - $this->launch_interval*60);
        $end_data = date('Y-m-d H:i:s', $timestamp);
        $connection = Connetor::get_connection();
        $result_set = $connection->query("SELECT * FROM items WHERE status=2 AND id NOT IN
            (SELECT item_id FROM buffered_items) AND publicated_to > '{$begin_data}' AND publicated_to < '{$end_data}'");

        return $this->build_items_set($result_set);
    }
    /*
     * ���������� ������� ���������������� ���������� �� �������� ��������.
     * ����� ���������� ������������ �� ��������� ��������� �����.
     */
    public function select_buffered_items_by_timestamp($timestamp){
        $begin_data = date('Y-m-d H:i:s', $timestamp - $this->launch_interval*60);
        $end_data = date('Y-m-d H:i:s', $timestamp);
        $connection = Connetor::get_connection();
        $result_set = $connection->query("SELECT * FROM items WHERE status=2 AND id IN
            (SELECT item_id FROM buffered_items) AND publicated_to > '{$begin_data}' AND publicated_to < '{$end_data}'");

        return $this->build_items_set($result_set);
    }

    public function select_all_buffered_items(){
        $connection = Connetor::get_connection();
        $result_set = $connection->query("SELECT * FROM items INNER JOIN buffered_items ON items.id=buffered_items.item_id
              WHERE status=2 AND id IN (SELECT item_id FROM buffered_items) ORDER BY publicated_to");

        return $this->build_items_set($result_set);
    }

    /*
     * �� ������ ���������� ������� ����� ������ ����� � �������� Item.
     */
    private function build_items_set($result_set){
        $items = array();
        foreach($result_set as $item){
            $items[] = new Item($item["id"],$item["user_id"],$item["status"],$item["title"],$item["link"],
                $item["descr"],$item["publicated_to"]);
        }
        return $items;
    }

}

//----------------------------------------------------------------------------------------------------------------------

/*
 * ����� ������������� ����� ������� ��� ����������� � ��������� Item � ������� buffered_items.
 * ������� buffered_items ������������ ����� �����, � ������� ����� ����������� id � ������� ���� ����������,
 *  ��������� ������� �� ���� ���������� ����� ���������� �������.
 *
 */
class BufferRepository {

    public function add_item(Item $item, $days_left){
        $connection = Connetor::get_connection();
        $connection->query("INSERT INTO buffered_items (item_id, days_left)
                            VALUES ({$item->id},{$days_left})");
        $connection = null;
    }

    public function update_item(Item $item, $days_left){
        $connection = Connetor::get_connection();
        $connection->query("UPDATE buffered_items SET days_left = $days_left WHERE item_id={$item->id}");
        $connection = null;
    }

    public function delete_item(Item $item){
        $connection = Connetor::get_connection();
        $connection->query("DELETE FROM buffered_items WHERE item_id={$item->id}");
        $connection = null;
    }



}

//----------------------------------------------------------------------------------------------------------------------

/*
 * ����� ������������� ������ ������ � ����� ������ ��� �������� User.
 */
class UserRepository{

    public function select_by_id($id){
        $connection = Connetor::get_connection();
        $result_set = $connection->query("SELECT * FROM users WHERE id={$id}");
        $row = $result_set->fetch(PDO::FETCH_ASSOC);
        return new User($row["id"], $row["email"]);

    }


}