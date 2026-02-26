<?php
class DB
{
    private $connection;

    function __construct()
    {
        $configure = [
            'hostname' => '127.0.0.1',
            'username' => 'root',
            'password' => 'root',
            'database' => 'greattree_member',
            'dbdriver' => 'mysqli'
        ];
        $connection = ADONewConnection($configure['dbdriver']);
        $connection->PConnect($configure['hostname'], $configure['username'], $configure['password'], $configure['database']);
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
?>