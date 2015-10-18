<?php
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