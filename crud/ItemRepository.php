<?php

include_once("{$_SERVER['DOCUMENT_ROOT']}/notification_sender/crud/ItemRepository.php");
include_once("{$_SERVER['DOCUMENT_ROOT']}/notification_sender/entity/Item.php");
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