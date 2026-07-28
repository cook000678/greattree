<?php
require_once 'autoload.php';

class CreateVoucher extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input          = $this->input;
        $type           = isset($input['type']) ? $input['type'] : ''; // 商品類型 (TICKET/DELIVERY)
        $voucherName    = trim(isset($input['voucher_name']) ? $input['voucher_name'] : ''); // 商品名稱
        $quantity       = isset($input['quantity']) ? $input['quantity'] : ''; // 庫存數量
        $skuId          = isset($input['sku_id']) ? $input['sku_id'] : null; // 對應6編碼
        $storeSkuId     = trim(isset($input['store_sku_id']) ? $input['store_sku_id'] : ''); // 商派優惠券ID
        $remarks        = isset($input['remarks']) ? $input['remarks'] : ''; // 注意事項

        // 參數驗證 (sku_id、remarks 非必填，其餘必填)
        if (!is_string($type) || strlen($type) > 8 || $type === '') {
            throw new Exception('type參數型態異常，僅限8碼字串', 1);
        }

        if ($type != 'TICKET' && $type != 'DELIVERY') {
            throw new Exception('type參數型態異常，僅限TICKET或DELIVERY', 2);
        }

        if (!is_string($voucherName) || strlen($voucherName) > 30 || $voucherName === '') {
            throw new Exception('voucher_name參數型態異常，僅限30碼字串', 3);
        }

        if (!is_int($quantity) && !(is_string($quantity) && ctype_digit($quantity))) {
            throw new Exception('quantity參數型態異常，僅限整數', 4);
        }

        if ((int)$quantity <= 0) {
            throw new Exception('quantity必須大於0', 5);
        }

        if ($skuId !== null && $skuId !== '' && (!is_string($skuId) || strlen($skuId) > 20)) {
            throw new Exception('sku_id參數型態異常，僅限20碼字串', 7);
        }

        if (!is_string($storeSkuId) || strlen($storeSkuId) > 50 || $storeSkuId === '') {
            throw new Exception('store_sku_id參數型態異常，僅限50碼字串', 10);
        }

        // 不是int和float，且 is_string($remarks) ===false就會進去else
        // 例如 array，bool，object，null，等等
        if (is_int($remarks) || is_float($remarks)) {
            $remarks = (string)$remarks;
        } elseif (!is_string($remarks)) {
            throw new Exception('remarks參數型態異常，需為字串', 11);
        }

        // 寫入商品券
        $insertData = [
            'type' => $type,
            'sku_id' => ($skuId === '' ? null : (string)$skuId),
            'store_sku_id' => (string)$storeSkuId,
            'name' => (string)$voucherName,
            'prices' => 0,
            'quantity' => (int)$quantity,
            'image_url' => '',
            'remarks' => $remarks,
            'is_active' => 1
        ];

        $createVoucher = $Conn->autoExecute('voucher', $insertData, 'INSERT');
        if (!$createVoucher) {
            throw new Exception('建立失敗', 12);
        }

        return $this->setResponse('0006', '建立成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new CreateVoucher())->run();
}
