<?php

include_once("Entity.php");
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