<?php
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