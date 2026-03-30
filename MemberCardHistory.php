<?php
require_once 'autoload.php';

class MemberCardHistory extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $accountId = $input['account_id'] ?? '';
        $pointType = $input['point_type'] ?? '';

        // 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $accountId)) {
            throw new Exception('account_id參數異常只限英文和數字共10碼', 1);
        }

        // 檢查資料
        // 1.檢查會員是否存在
        $member = new Member($DB);
        if (!$member->queryMember($accountId)) {
            throw new Exception('會員不存在', 2);
        }

        // 2. 取得點數類型 ID
        $queryPointType = $Conn->execute("SELECT type_id FROM point_types WHERE type_key = ?", [$pointType]);
        if (!$queryPointType || $queryPointType->RecordCount() == 0) {
            throw new Exception('點數類型不存在', 3);
        }
        $typeId = $queryPointType->fetchObj()->type_id;

        // 查詢點數紀錄
        $queryPointHistory = $Conn->execute("SELECT create_at, point, refer, note FROM point_history WHERE account_id = ? AND point_type = ? ORDER BY create_at DESC", [$accountId, $typeId]);

        $historyList = [];
        if ($queryPointHistory) {
            while (!$queryPointHistory->EOF) {
                $row = $queryPointHistory->fetchObj();
                $historyList[] = [
                    'date' => date('Y-m-d H:i:s', strtotime($row->create_at)),
                    'point' => intval($row->point),
                    'refer' => $row->refer,
                    'note' => $row->note
                ];
                $queryPointHistory->MoveNext();
            }
        }

        $this->response['point_history'] = $historyList;
        return $this->setResponse('0006', '點數記錄查詢成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new MemberCardHistory())->run();
}
?>