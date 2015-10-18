<?php
/*
 * ����������� �������, ������� ���������� � ���� ����� ���� ���� ��������� ���������.
 * �� ����� ������������� � ���� ������ � �� ������������� ��� ����������������� �������������.
 * ��� �������� ������ ������������� �� ������ ������.
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