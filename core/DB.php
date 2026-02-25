<?php
    class DB
    {
        private $connection;

        function __construct()
        {
            $configure = [
                'hostname' => '127.0.0.1',
                'username' => file_get_contents("/connect/project/greattree/greattree_AccountId.txt"),
                'password' => file_get_contents("/connect/project/greattree/greattree_Password.txt"),
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