<?php
require_once 'autoload.php';

class ModifyMemberLevel extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $account_id = $input['account_id'] ?? '';
        $level = $input['level'] ?? '';

        // 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $account_id)) {
            throw new Exception('account_id參數異常只限英文和數字共10碼', 3);
        }

        //查詢會員等級是否存在
        $queryLevel = $Conn->execute("SELECT * FROM member_level WHERE name = ?", [$level]);
        if ($queryLevel->RecordCount() == 0) {
            throw new Exception('會員等級不存在', 4);
        }
        $levelId = $queryLevel->fetchObj()->id;


        // 1. 檢查會員是否存在與修改等級
        $member = new Member($DB);
        $member->modifyMemberLevel($account_id, $levelId);

        return $this->setResponse('0006', '會員等級更新成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new ModifyMemberLevel())->run();
}
?>
