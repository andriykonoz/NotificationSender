<?php

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