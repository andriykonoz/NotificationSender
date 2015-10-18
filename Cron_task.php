<?php
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

//----------------------------------------------------------------------------------------------------------------------

/*
 * Класс позволяет вести запись возникших ошибок.
 */

class Logger
{
    /*
     * Реализация-заглушка.
     */
    public static function log_error(Exception $e)
    {
        //TODO вставить нужную реализацию.
//        $date = date('Y-m-d-H-i-s');
//        echo "ERROR [{$date}]: {$e->getMessage()}<br/>";


    }
}

//----------------------------------------------------------------------------------------------------------------------
//-------------------------Сервисы--------------------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------

/*
 * Класс-сервис. Клас предоставляет методы для работы с обьектами Item на уровне решения поставленой проблемы.
 */

class ItemService
{
    /*
     * $item_repository содержит логику работы с базой данных для обьектов Item.
     * $buffer_repository содержит логику работы с базой данных при буферизации обьектов Item.
     * $notification_days дни для отправки уведомлений.
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
     * Метод выборки обьектов обьявлений, требующих отправки уведомлений владельцу.
     * Алгоритм метода ориентирован на работу в условиях, когда в отправки уведомлений нуждаются
     * больше обьявлений, чем разрешено отправить за раз.
     * Вибор обьявлений производится за принципом временной рамки - обьявлениями, владельцам которых нужно отправить
     * уведомления, считаются те обьявления, дата окончания публикации которых лежит в интервале от
     * (now - $launch_interval) до (now).
     *
     * Алгоритм работы метода:
     *  Обновляется буфер.
     *      Если обьявление находилось в буфере длительный период, то информация может быть не актуальной.
     *  Выбираются обьявления с буфера.
     *      Если в буфере находится больше обьявлений, чем можна отправить за раз, то выбирается только то количество,
     *      которое будет отправлено.
     *      В буфер попадают те обьявления, которые были выбраны сверх лимита во время прошлого запуска и не были
     *      отпралены.
     *  Выбираются новые обьявления.
     *      Выбираются обьявления, владельцам которых нужно отправить уведомления, и которые отсутствуют в буфере.
     *      Если на отправку уже набраны все обьявления, то те, которые оказались сверх лимита, сохраняются в буфере.
     *  Очищается буфер от выбраных обьявлений.
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
     * Метод обновляет информацию о остатке дней для буферизированых обьявлений.
     * Предназначен, для тех случаев, система будет слишком перегружена и обьявления в буфере будут находится
     * длительное время.
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
     * Метод выборки обьявлений Item с буфера за указаной квотой $max_limit
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
     * Выбираются обьявления, владельцам которых нужно отправить уведомления, и которые отсутствуют в буфере.
     * Если на отправку уже набраны все обьявления, то те, которые оказались сверх лимита, сохраняются в буфере.
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

//----------------------------------------------------------------------------------------------------------------------
//------------------------Сущности (Классы обертки для данных)----------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------

/*
 * Абстрактная сущнось, которая обьеденяет в себе общие поля всех сущностей программы.
 * Не имеет представления в базе данных и не предназначена для непосредственного использования.
 * Все сущности должны наследоваться от даного класса.
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
 * Сущность представляет собой обьявление.
 * В базе данных отображена как запись таблицы items.
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
 * Сущность представляет собой пользователя.
 * В базе данных отображена как запись таблицы users
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
 * Сущность которая содержит в себе информацию для отправки уведомления.
 * Не имеет представления в базе данных. Создается на основе пары обьектов Item и User.
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
//------------------------Уровень работы с базой данных-----------------------------------------------------------------
//----------------------------------------------------------------------------------------------------------------------

/*
 * Класс реализирует создание соединений в одной точке, для упрощения изменения конфигурации соединений.
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
 * Класс предоставляет набор методов для работы работы с базой данных для обьектов Item.
 */
class ItemRepository
{
    /*
     * $launch_interval интервал между запусками скрипта.
     */
    private $launch_interval;

    public function __construct($launch_interval){
        $this->launch_interval = $launch_interval;
    }
    /*
     * Производит выборку опубликованых обьявлений, которые нуждаются в отправке уведомлений владельцам.
     * Вибор обьявлений производится за принципом временной рамки - обьявлениями, владельцам которых нужно отправить
     * уведомления, считаются те обьявления, дата окончания публикации которых лежит в интервале от
     * (now - $launch_interval) до (now).
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
     * Производит выборку буфферизированых обьявлений за указаным временем.
     * Вибор обьявлений производится за принципом временной рамки.
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
     * На основе результата запроса метод строит масив с обьектов Item.
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
 * Класс предоставляет набор методов для манипуляции с обьектами Item в таблице buffered_items.
 * Таблица buffered_items представляет собой буфер, в который будут записыватся id и остаток дней обьявлений,
 *  извещения которых не были отправлены через превышение лимимта.
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
 * Класс предоставляет методы работы с базой данных для обьектов User.
 */
class UserRepository{

    public function select_by_id($id){
        $connection = Connetor::get_connection();
        $result_set = $connection->query("SELECT * FROM users WHERE id={$id}");
        $row = $result_set->fetch(PDO::FETCH_ASSOC);
        return new User($row["id"], $row["email"]);

    }


}