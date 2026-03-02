<?php
    class Member
    {

        private $connection;

        public function __construct($connection)
        {
            $this->connection = $connection->getConnection();
        }

        public function queryMember($AccountId) 
        {
            $member = $this->connection->execute("select * from accounts where account_id = ?", [$AccountId]);
            if ($member->RecordCount()=='1') {
                return $member->fetchObj();
            }
        }

        public function queryCard($cardId) 
        {
            $card = $this->connection->execute("select * from accounts where card_id = ?", [$cardId]);
            if ($card->RecordCount()=='1') {
                return $card->fetchObj();
            }
        }

        public function createMember($AccountId) 
        {
            $res = $this->connection->autoExecute('accounts', [
                'account_id' => $AccountId
            ], 'INSERT');

            if (!$res) {
                throw new Exception("會員建立失敗", 1);
            }

            return true;
        }

        public function modifyMemberLevel($AccountId, $MemberLevel)
        {
            $MemberLevel= intval($MemberLevel);
            $member     = $this->connection->execute("select * from accounts where account_id = ?", [$AccountId]);
            if ($member->RecordCount()=='0') {
                throw new Exception("會員不存在", 1);
            }
            
            $this->connection->execute("update accounts set account_level = ? where account_id = ? limit 1", [$MemberLevel, $AccountId]);
            if ($this->connection->affected_rows()!='1') {
                throw new Exception("會員等級更新失敗", 2);
            }

            return true;
        }
    }
?>