<?php
    abstract class ApiTemplate
    {
        protected $response = [
            'returnCode'    => '9999',
            'returnMessage' => 'initial error'
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
            ini_set('display_errors', 1);
            try {
                $input = file_get_contents("php://input");
                if((json_decode($input)?'true':'false')!='true'){
                    throw new Exception("串接異常，不為Json格式", 9998);
                }
                $inputArray = json_decode($input, true);
                $this->input= $inputArray;
                $this->init();
                $this->execute();
            } catch (\Throwable $th) {
                $this->response['returnCode']    = sprintf("%04d", $th->getCode());
                $this->response['returnMessage'] = $th->getMessage();
            } finally {
                echo json_encode($this->response, JSON_UNESCAPED_UNICODE);
            }
        }

        // 子類別可用此設定回應
        protected function setResponse(string $code, string $message)
        {
            $this->response['returnCode']    = $code;
            $this->response['returnMessage'] = $message;
        }
    }
?>