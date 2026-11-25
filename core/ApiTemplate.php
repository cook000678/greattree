<?php
abstract class ApiTemplate
{
    protected $response = [
        'return_code' => '9999',
        'return_msg' => 'initial error'
    ];

    protected $input;

    // 子類別必須實現此方法
    abstract protected function execute();

    // 初始化必要物件
    protected function init()
    {
    // autoload 在 bootstrap 已加載
    // 可在此初始化常用物件
    }

    public final function run()
    {
        $RqId       = bin2hex(random_bytes(16));
        $startTime  = microtime(true);
        try {
            $input      = file_get_contents("php://input");
            $logInput   = $input;
            // $header     = $_SERVER['HTTP_AUTHORIZATION'] ? $_SERVER['HTTP_AUTHORIZATION'] : '';
            $userAgent  = $_SERVER['HTTP_USER_AGENT'];

            $DB         = new DB();
            $Conn       = $DB->getConnection();
            $getToken   = $Conn->execute("SELECT id FROM api_user_agent WHERE user_agent = ? ", [$userAgent]);
            if ($getToken->RecordCount() == '0') {
                $Conn->autoExecute('api_user_agent', [
                    'user_agent' => $userAgent
                ], 'INSERT');
                $uaId   = $Conn->Insert_ID();
            } else {
                $uaId   = $getToken->Fields('id');
            }

            if (get_class($this)==='GetLoginToken') {
                $logInput = '';
            }
            if((json_decode($input)?'true':'false')!='true'){
                throw new Exception("串接異常，不為Json格式", 9998);
            }
            $inputArray = json_decode($input, true);
            $this->input= $inputArray;
            if (!$this->init()) {
                return;
            }
            // $this->init();
            $this->execute();
        } catch (\Throwable $th) {
            $this->response['return_code']  = sprintf("%04d", $th->getCode());
            $this->response['return_msg']   = $th->getMessage();
        } finally {
            $endTime    = microtime(true);
            $response   = json_encode($this->response, JSON_UNESCAPED_UNICODE);
            $runtime_ms = (int)round(($endTime - $startTime) * 1000);
            $logInput   = isset($logInput) ? $logInput : '';
            $this->logger($RqId, $uaId, $_SERVER['REQUEST_URI'], $logInput, $response, $runtime_ms);

            //     "($RqId)" . $_SERVER['REMOTE_ADDR'] . PHP_EOL . 
            //     "url: " .  . PHP_EOL .
            //     "header: " . $header . PHP_EOL .
            //     "Rq: " . $logInput
            // );
            // $this->logger("($RqId)\r\nRs: " . $response . "\r\n");
            echo $response;
        }
    }

    // 子類別可用此設定回應
    protected function setResponse(string $code, string $message, array $data = [])
    {
        $this->response['return_code']  = $code;
        $this->response['return_msg']   = $message;
        if (!empty($data)) {
            $this->response = array_merge($this->response, $data);
        }
    }

    protected function authenticateApiRequest()
    {
        // $jwtSecret  = file_get_contents('/connect/project/greattree/jwt-secret.txt');
        // $jwt        = new NativeJWT($jwtSecret);
        // // 1. 提取 Header 中的 Authorization Token
        // $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

        // if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        //     $this->setResponse('9998', '未提供 Authorization 標頭');
        //     return false;
        // }
        // $token      = $matches[1];
        // // 2. 第一階段：原生 PHP 驗證簽名與 exp
        // $payload    = $jwt->decode($token);
        // if (!$payload || !isset($payload['jti'])) {
        //     $this->setResponse('9998', 'Token 簽名無效或已過期');
        //     return false;
        // }
        
        $DB         = new DB();
        $Conn       = $DB->getConnection();
        $getToken   = $Conn->execute("SELECT id FROM api_user_tokens WHERE jti = ? AND is_revoked = 0 AND expires_at > NOW()", [$payload['jti']]);

        // 3. 第二階段：MySQL 驗證狀態（是否被廢止或在 DB 端已過期）
        if ($getToken->RecordCount() == '0') {
            $this->setResponse('9997', 'Token 已被廢止或失效');
            return false;
        }

        // 驗證成功，傳回 Payload
        // return $payload;
    }

    /**
     * 記錄日誌
     *
     * @param string $requestId
     * @param string $uaId
     * @param string $api_path
     * @param string $request
     * @param string $response
     * @param float $runtime
     * @return void
     */
    public function logger($requestId, $uaId, $api_path, $request, $response, $runtime)
    {
        $DB         = new DB();
        $Conn       = $DB->getConnection();
        $httpMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        $clientIp   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $ipBinary   = @inet_pton($clientIp);
        if ($ipBinary === false) {
            $ipBinary = inet_pton('127.0.0.1');
        }

        $headers = [
            'User-Agent'    => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'Authorization' => isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '',
            'Content-Type'  => isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : ''
        ];

        $Conn->autoExecute("api_log", [
            'http_method'   => $httpMethod,
            'request_id'    => $requestId,
            'ip'            => $ipBinary,
            'user_agent_id' => $uaId,
            'header'        => json_encode($headers, JSON_UNESCAPED_SLASHES),
            'api_path'      => $api_path,
            'request'       => $request,
            'response'      => $response,
            'runtime_ms'    => $runtime
        ], 'INSERT');
    }
}
?>