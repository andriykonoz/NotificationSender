<?php
/*
 * Класс реализирует создание соединений в одной точке, для упрощения изменения конфигурации соединений.
 *
 */
class Connetor {

    public static function get_connection(){
        $dbtype = 'mysql';
        $host = 'localhost';
        $dbase = 'add_base';
        $user = 'root';
        $pass = 'root';

        return new PDO("{$dbtype}:host={$host};dbname={$dbase}", $user,$pass);
    }
}