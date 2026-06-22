<?php
class DB
{
    private $connection;

    function __construct()
    {
        $configure = [
            'hostname' => 'shared-db-test',
            'username' => 'root',
            'password' => 'root',
            'database' => 'greattree_member',
            'dbdriver' => 'mysqli'
        ];
        $connection = ADONewConnection($configure['dbdriver']);
        $connection->PConnect($configure['hostname'], $configure['username'], $configure['password'], $configure['database']);
        $connection->Execute("SET NAMES 'utf8mb4'");
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
?>