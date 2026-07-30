<?php
class Logger
{
    /**
     * 記錄日誌到指定目錄下的 TXT 檔案
     * 路徑：greattree/logs/年-月/年-月-日.txt
     *
     * @param string $message 日誌訊息
     * @param string $level 日誌層級 (ERROR/INFO/DEBUG)
     */
    public static function log($message, $level = 'ERROR')
    {
        // 移至根目錄後，直接使用 __DIR__ . '/logs' 即可
        $logDir = __DIR__ . '/logs/' . date('Y-m');

        // 如果目錄不存在則建立
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . '/' . date('Y-m-d') . '.txt';
        $time = date('Y-m-d H:i:s');

        $formattedMessage = "[$time] [$level] $message" . PHP_EOL;

        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }
}
