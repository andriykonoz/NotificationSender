<?php
include_once("{$_SERVER['DOCUMENT_ROOT']}/notification_sender/entity/User.php");
/*
 * ����� ������������� ������ ������ � ����� ������ ��� �������� User.
 */
class UserRepository{

    public function select_by_id($id){
        $connection = Connetor::get_connection();
        $result_set = $connection->query("SELECT * FROM users WHERE id={$id}");
        $row = $result_set->fetch(PDO::FETCH_ASSOC);
        return new User($row["id"], $row["email"]);

    }


}