<?php
    require_once 'autoload.php';

    class CreateMemberCard extends ApiTemplate
    {
        protected function execute()
        {
            $DB     = new DB();
            // $Conn   = $DB->getConnection();
            // $Conn->execute("select * from accounts");

            $member     = new Member($DB);
            $card       = new Card($DB);
            $input      = $this->input;
            $accountId  = $input['account_id'];
            $cardId     = $input['card_id'];

            if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $accountId)) {
                return $this->setResponse('0001', 'account_id參數異常');
            }

            if (!preg_match('/^[a-zA-Z0-9]{1,50}$/', $cardId)) {
                return $this->setResponse('0002', 'card_id參數異常');
            }
            
            $getMember = $member->queryMember($accountId);         
            if (!$getMember) {
                $getMember->createMember($accountId);
            }
            
            $getCard   = $card->queryCard($cardId);
            if ($getCard) {
                throw new Exception("卡號已存在", 3);
            }

            $card->createCard($accountId, $cardId);
            return $this->setResponse('0006', '建立成功');
        }
    }

    (new CreateMemberCard())->run();
?>