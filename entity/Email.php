<?php
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