<?php

/*
 * ����� ��������� ����� ������ ��������� ������.
 */

class Logger
{
    /*
     * ����������-��������.
     */
    public static function log_error(Exception $e)
    {
        $date = date('Y-m-d-H-i-s');
        echo "ERROR [{$date}]: {$e->getMessage()}<br/>";

        //TODO �������� ����������.
    }
}