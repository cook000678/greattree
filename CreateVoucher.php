<?php
require_once 'autoload.php';

class CreateVoucher extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $redeemType = $input['redeem_type'] ?? '';
        $type = $input['type'] ?? '';
        $voucherName = $input['voucher_name'] ?? '';
        $quantity = $input['quantity'] ?? '';
        $skuId = $input['sku_id'] ?? null;
        $storeSkuId = $input['store_sku_id'] ?? '';

        // 參數驗證 (sku_id 非必填，其餘必填)
        if (!is_int($redeemType) && !(is_string($redeemType) && ctype_digit($redeemType))) {
            throw new Exception('redeem_type參數型態異常，僅限數字', 1);
        }

        if (!is_string($type) || strlen($type) > 8 || $type === '') {
            throw new Exception('type參數型態異常，僅限8碼字串', 2);
        }

        if($type != 'TICKET' && $type != 'DELIVERY') {
            throw new Exception('type參數型態異常，僅限TICKET或DELIVERY', 3);
        }

        if (!is_string($voucherName) || strlen($voucherName) > 30 || $voucherName === '') {
            throw new Exception('voucher_name參數型態異常，僅限30碼字串', 4);
        }

        if (!is_int($quantity) && !(is_string($quantity) && ctype_digit($quantity))) {
            throw new Exception('quantity參數型態異常，僅限數字', 5);
        }

        if ($skuId !== null && $skuId !== '' && (!is_string($skuId) || strlen($skuId) > 20)) {
            throw new Exception('sku_id參數型態異常，僅限20碼字串', 7);
        }

        if (!is_string($storeSkuId) || strlen($storeSkuId) > 50 || $storeSkuId === '') {
            throw new Exception('store_sku_id參數型態異常，僅限50碼字串', 10);
        }

        // 寫入商品券
        $insertData = [
            'redeem_type' => (int)$redeemType,
            'type' => $type,
            'sku_id' => ($skuId === '' ? null : $skuId),
            'store_sku_id' => $storeSkuId,
            'name' => $voucherName,
            'prices' => 0,
            'quantity' => (int)$quantity,
            'image_url' => '',
            'remarks' => '',
            'is_active' => 0
        ];

        $createVoucher = $Conn->autoExecute('voucher', $insertData, 'INSERT');
        if (!$createVoucher) {
            throw new Exception('建立失敗', 11);
        }

        return $this->setResponse('0006', '建立成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new CreateVoucher())->run();
}
?>