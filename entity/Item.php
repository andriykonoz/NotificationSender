<?php

include_once("Entity.php");


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