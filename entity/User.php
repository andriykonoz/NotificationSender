<?php

include_once("Entity.php");
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