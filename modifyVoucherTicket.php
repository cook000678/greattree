<?php
require_once 'autoload.php';

class modifyVoucherTicket extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $voucherCode = $input['voucher_code'] ?? ''; // 票券編號
        $modifyType = $input['modify_type'] ?? ''; // 異動類型 (redeem 核銷/refund 作廢/reverse 反核銷)

        // 參數驗證
        if (!is_string($voucherCode) || strlen($voucherCode) > 20 || $voucherCode === '') {
            throw new Exception('voucher_code參數型態異常，僅限20碼字串', 1);
        }

        if (!is_string($modifyType) || !in_array($modifyType, ['redeem', 'refund', 'reverse'])) {
            throw new Exception('modify_type參數型態異常，僅限redeem或refund或reverse', 2);
        }

        // 檢查票券是否存在
        $queryVoucher = $Conn->execute("SELECT voucher_code FROM order_item_vouchers WHERE voucher_code = ?", [$voucherCode]);
        if (!$queryVoucher || $queryVoucher->RecordCount() == 0) {
            throw new Exception('票券不存在', 3);
        }

        // 檢查目前票券狀態
        $queryVoucherStatus = $Conn->execute("SELECT voucher_code, status FROM voucher_status WHERE voucher_code = ?", [$voucherCode]);
        $currentStatus = '';
        if ($queryVoucherStatus && $queryVoucherStatus->RecordCount() > 0) {
            $currentStatus = strtoupper($queryVoucherStatus->fetchObj()->status);
        }

        // 狀態轉換檢查
        if ($currentStatus == 'CANCEL') {
            if ($modifyType == 'redeem') {
                throw new Exception('票券已作廢，不可核銷', 7);
            }
            if ($modifyType == 'refund') {
                throw new Exception('票券已作廢，不可重複作廢', 10);
            }
            if ($modifyType == 'reverse') {
                throw new Exception('票券已作廢，不可反核銷', 11);
            }
        }

        if ($currentStatus == 'USED') {
            if ($modifyType == 'redeem') {
                throw new Exception('票券已核銷，不可重複核銷', 12);
            }
            if ($modifyType == 'refund') {
                throw new Exception('票券已核銷，不可作廢', 13);
            }
        }

        if ($currentStatus == '' && $modifyType == 'reverse') {
            throw new Exception('票券尚未核銷，不可反核銷', 14);
        }

        // 異動票券狀態
        if ($modifyType == 'reverse') {
            $modifyVoucherStatus = $Conn->execute("DELETE FROM voucher_status WHERE voucher_code = " . $Conn->qstr($voucherCode));
        } else {
            $voucherStatus = ($modifyType == 'redeem') ? 'USED' : 'CANCEL';
            if ($currentStatus != '') {
                $modifyVoucherStatus = $Conn->execute(
                    "UPDATE voucher_status SET status = ?, update_at = ? WHERE voucher_code = ?",
                    [$voucherStatus, date('Y-m-d H:i:s'), $voucherCode]
                );
            } else {
                $modifyVoucherStatus = $Conn->execute(
                    "INSERT INTO voucher_status (voucher_code, status, update_at) VALUES (?, ?, ?)",
                    [$voucherCode, $voucherStatus, date('Y-m-d H:i:s')]
                );
            }
        }

        if (!$modifyVoucherStatus) {
            throw new Exception('票券狀態更新失敗：' . $Conn->ErrorMsg(), 4);
        }

        // 寫入票券異動紀錄
        $addVoucherHistory = $Conn->execute(
            "INSERT INTO voucher_history (voucher_code, modify_status) VALUES (?, ?)",
            [$voucherCode, $modifyType]
        );
        if (!$addVoucherHistory) {
            throw new Exception('票券異動紀錄寫入失敗', 5);
        }

        return $this->setResponse('0006', '票券狀態更新成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new modifyVoucherTicket())->run();
}
?>
