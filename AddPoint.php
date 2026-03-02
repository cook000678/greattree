<?php
require_once 'autoload.php';

class AddPoint extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $cardId = $input['card_id'] ?? '';
        $pointType = $input['point_type'] ?? '';
        $point = $input['point'] ?? 0;

        // 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,50}$/', $cardId)) {
            throw new Exception('card_id參數異常只限英文和數字共50碼', 1);
        }

        if (!is_numeric($point) || $point <= 0) {
            throw new Exception('point參數異常，僅限數字且需大於0', 2);
        }

        // 檢查資料
        // 1. 查詢卡號
        $card = new Card($DB);
        if (!$card->queryCard($cardId)) {
            throw new Exception('卡號不存在', 3);
        }

        // 2. 取得點數類型 ID
        $queryPointType = $Conn->execute("SELECT type_id FROM point_types WHERE type_name = ?", [$pointType]);
        if (!$queryPointType || $queryPointType->RecordCount() == 0) {
            throw new Exception('點數類型不存在', 4);
        }
        $typeId = $queryPointType->fetchObj()->type_id;

        $note = $input['note'] ?? '';
        $referOrderId = $input['refer_order_id'] ?? '';

        // 寫入點數
        $insertData = [
            'card_id' => $cardId,
            'point_type' => $typeId,
            'point' => $point,
            'note' => $note,
            'refer_order_id' => $referOrderId
        ];

        $addPoint = $Conn->autoExecute('point_history', $insertData, 'INSERT');

        if (!$addPoint) {
            throw new Exception('點數寫入失敗', 5);
        }

        return $this->setResponse('0006', '點數寫入成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new AddPoint())->run();
}

?>