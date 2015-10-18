<?php

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