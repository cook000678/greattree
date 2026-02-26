<?php
require_once 'autoload.php';

class StampPointMapping extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $cardId = $input['card_id'] ?? '';
        $orderNo = $input['order_no'] ?? '';
        $products = $input['products'] ?? [];

        // 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,50}$/', $cardId)) {
            throw new Exception('card_id參數異常', 2);
        }

        if (empty($products) || !is_array($products)) {
            throw new Exception('串接異常，不為json格式', 9);
        }

        // 批次讀取商品價格
        $productIds = [];
        foreach ($products as $p) {
            $pid = intval($p['product_id'] ?? 0);
            if ($pid > 0)
                $productIds[] = $pid;
        }
        $productIds = array_unique($productIds);

        $priceMap = [];
        if (!empty($productIds)) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $priceRs = $Conn->execute("SELECT product_id, prices FROM product WHERE product_id IN ($placeholders)", $productIds);
            if ($priceRs) {
                while (!$priceRs->EOF) {
                    $pObj = $priceRs->fetchObj();
                    $priceMap[intval($pObj->product_id)] = floatval($pObj->prices);
                    $priceRs->MoveNext();
                }
            }
        }

        // 資料庫存在驗證

        // 1. 檢查卡號是否存在
        $checkCard = $Conn->execute("SELECT count(*) as count FROM card_id WHERE card_id = ?", [$cardId]);
        if (!$checkCard || $checkCard->fetchObj()->count == 0) {
            throw new Exception("卡號不存在", 2);
        }

        // 2. 檢查訂單編號是否存在
        $checkOrder = $Conn->execute("SELECT count(*) as count FROM orders WHERE order_id = ?", [$orderNo]);
        if (!$checkOrder || $checkOrder->fetchObj()->count == 0) {
            throw new Exception("訂單編號不存在", 1);
        }

        // 3. 檢查商品是否存在與格式
        foreach ($products as $p) {
            $pid = intval($p['product_id'] ?? 0);
            $qty = intval($p['quantity'] ?? 0);

            if ($pid <= 0) {
                throw new Exception("商品編號格式異常或未提供", 1);
            }
            if ($qty <= 0) {
                throw new Exception("商品數量格式異常或未提供", 1);
            }
            if (!isset($priceMap[$pid])) {
                throw new Exception("商品編號不存在: " . $pid, 1);
            }
        }

        // 1. 取得活動清單：必須是啟用中且在有效期限內
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT * FROM stamp WHERE is_active = 1 AND start_time <= ? AND end_time >= ?";
        $eventRs = $Conn->execute($sql, [$now, $now]);

        $eventList = [];

        if ($eventRs && $eventRs->RecordCount() > 0) {
            while (!$eventRs->EOF) {
                $event = $eventRs->fetchObj();
                $eventId = $event->id;
                $eventName = $event->name;
                $eventType = $event->type; // ALL_PRODUCT / SPECIFIC_PRODUCT

                $qualifiedAmount = 0;

                // 2. 計算該活動符合條件的累積金額
                foreach ($products as $p) {
                    $pid = intval($p['product_id']);
                    $qty = intval($p['quantity']);

                    $isQualified = false; //預設該商品不符合活動資格
                    if ($eventType == 'ALL_PRODUCT') {
                        $isQualified = true;
                    }
                    else if ($eventType == 'SPECIFIC_PRODUCT') {
                        // 檢查商品是否在該活動的指定清單中
                        $checkSql = "SELECT count(*) as count FROM stamp_product WHERE event_id = ? AND product_id = ?";
                        $checkRs = $Conn->execute($checkSql, [$eventId, $pid]);
                        if ($checkRs && $checkRs->fetchObj()->count > 0) {
                            $isQualified = true;
                        }
                    }

                    if ($isQualified) {
                        // 直接從快取好的 priceMap 取得價格，不再進 DB 查詢
                        $price = $priceMap[$pid] ?? 0;
                        $qualifiedAmount += ($price * $qty);
                    }
                }

                // 3. 根據累積金額計算點數 (查找最接近且符合的門檻)
                $ruleSql = "SELECT * FROM stamp_rule WHERE event_id = ? AND threshold_amount <= ? ORDER BY threshold_amount DESC LIMIT 1";
                $ruleRs = $Conn->execute($ruleSql, [$eventId, $qualifiedAmount]);

                if ($ruleRs && $ruleRs->RecordCount() > 0) {
                    $rule = $ruleRs->fetchObj();
                    // 計算贈送點數：(累積金額 / 門檻) * 單位贈點
                    $multiples = floor($qualifiedAmount / $rule->threshold_amount);
                    $rewardPoints = $multiples * $rule->reward_points;

                    if ($rewardPoints > 0) {
                        $eventList[] = [
                            'event_id' => intval($eventId),
                            'event_name' => $eventName,
                            'point' => intval($rewardPoints)
                        ];
                    }
                }

                $eventRs->MoveNext();
            }
        }

        $this->response['event_list'] = $eventList;
        return $this->setResponse('0000', '接收成功');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new StampPointMapping())->run();
}
?>
