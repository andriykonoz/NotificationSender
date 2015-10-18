<?php
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