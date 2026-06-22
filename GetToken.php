<?php
require_once 'autoload.php';

class GetToken extends ApiTemplate
{
    private $validSecretKey = "84563183dade16f9244f84b05febc2d9f0da59b212c98f647600983b1813dede";

    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $accountId = $input['account_id'] ?? '';
        $secretKey = $input['secret_key'] ?? '';

        // 1. 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $accountId)) {
            throw new Exception('account_id參數異常只限英文和數字共20碼', 1);
        }

        if ($secretKey !== $this->validSecretKey) {
            throw new Exception('secret_key驗證不通過', 2);
        }

        // 2. 檢查帳號是否存在
        $memberModel = new Member($DB);
        if (!$memberModel->queryMember($accountId)) {
            throw new Exception("帳號不存在", 3);
        }

        // 3. 檢查現有的有效 Token
        // 條件：尚未驗證 (verify_at IS NULL) 且 尚未過期 (expire_at > NOW)
        $now = date('Y-m-d H:i:s');
        $checkTokenSql = "SELECT code 
            FROM login_token 
            WHERE account_id = ? 
            AND verify_at IS NULL 
            AND expire_at > ? 
            ORDER BY id DESC 
            LIMIT 1
        ";
        $tokenRs = $Conn->execute($checkTokenSql, [$accountId, $now]);

        if ($tokenRs && $tokenRs->RecordCount() > 0) {
            // 如果還有有效的 Token，則直接回傳舊的
            $token = $tokenRs->fetchObj()->code;
            $this->response['token'] = $token;
            return $this->setResponse('0006', '取得成功');
        } else {
            // 若無有效 Token (過期、已驗證、或從未建立)，則產出新的
            $token = bin2hex(random_bytes(16));
            $expireAt = date('Y-m-d H:i:s', strtotime('+5 minutes')); // 效期 5 分鐘

            $insertData = [
                'code' => $token,
                'account_id' => $accountId,
                'expire_at' => $expireAt,
                'create_at' => $now
            ];

            $ok = $Conn->autoExecute('login_token', $insertData, 'INSERT');

            if (!$ok) {
                throw new Exception('Token 建立失敗', 4);
            }

            $this->response['token'] = $token;
            return $this->setResponse('0006', '建立成功');
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new GetToken())->run();
}
?>
