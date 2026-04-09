<?php
require_once 'autoload.php';

class AddPoint extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $accountId = $input['account_id'] ?? '';
        $pointType = $input['point_type'] ?? '';
        $point = $input['point'] ?? '';
        $refer = $input['refer'] ?? '';

        // 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $accountId)) {
            throw new Exception('account_id參數異常只限英文和數字共10碼', 1);
        }

        if (!is_numeric($point)) {
            throw new Exception('point參數為必填且僅限數字', 2);
        }

        if (!in_array($refer, ['門市', '線上'])) {
            throw new Exception('refer參數為必填僅限門市或線上', 3);
        }

        // 檢查資料
        // 1. 查詢會員
        $member = new Member($DB);
        if (!$member->queryMember($accountId)) {
            throw new Exception('會員不存在', 4);
        }

        // 2. 取得點數類型 ID
        $queryPointType = $Conn->execute("SELECT type_id FROM point_types WHERE type_key = ?", [$pointType]);
        if (!$queryPointType || $queryPointType->RecordCount() == 0) {
            throw new Exception('點數類型不存在', 5);
        }
        $typeId = $queryPointType->fetchObj()->type_id;

        $note = $input['note'] ?? '';
        // $referOrderId = $input['refer_order_id'] ?? '';
        // $referStampId = intval($input['refer_stamp_id'] ?? 0); // 預設 0 防止資料庫出錯

        // 寫入點數
        $insertData = [
            'account_id' => $accountId,
            'point_type' => $typeId,
            'point' => intval($point),
            'create_at' => date('Y-m-d H:i:s'),
            'refer' => $refer,
            'note' => $note,
            // 'refer_order_id' => $referOrderId,
            // 'refer_stamp_id' => $referStampId
        ];

        $addPoint = $Conn->autoExecute('point_history', $insertData, 'INSERT');

        if (!$addPoint) {
            throw new Exception('點數寫入失敗', 7);
        }

        return $this->setResponse('0006', '點數寫入成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new AddPoint())->run();
}

?>