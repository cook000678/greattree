<?php
    class Card
    {

        private $connection;

        public function __construct($connection)
        {
            $this->connection = $connection->getConnection();
        }

        public function queryCard($cardId) 
        {
            $card = $this->connection->execute("select * from card_id where card_id = ?", [$cardId]);
            if ($card->RecordCount()=='1') {
                return $card->fetchObj();
            }
        }

        public function createCard($AccountId, $CardId) 
        {
            $res = $this->connection->autoExecute('card_id', [
                'card_id'    => $CardId,
                'account_id' => $AccountId,
            ], 'INSERT');

            if (!$res) {
                throw new Exception("卡號建立失敗", 3);
            }

            return true;
        }

    }
?>