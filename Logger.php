<?php

/*
 * Класс позволяет вести запись возникших ошибок.
 */

class Logger
{
    /*
     * Реализация-заглушка.
     */
    public static function log_error(Exception $e)
    {
        $date = date('Y-m-d-H-i-s');
        echo "ERROR [{$date}]: {$e->getMessage()}<br/>";

        //TODO заменить реализацию.
    }
}