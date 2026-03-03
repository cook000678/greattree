<?php
require_once 'autoload.php';

class StampPointMapping extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $accountId = $input['account_id'] ?? '';
        $orderId = $input['order_no'] ?? '';
        $products = $input['products'] ?? [];

        // 1. 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $accountId)) {
            throw new Exception('account_id參數異常只限英文和數字共10碼', 1);
        }

        if (empty($products) || !is_array($products)) {
            throw new Exception('商品格式異常', 7);
        }

        // 2. 檢查帳號是否存在
        $memberModel = new Member($DB);
        if (!$memberModel->queryMember($accountId)) {
            throw new Exception("帳號不存在", 2);
        }

        // 3. 檢查訂單是否存在 (greattree_orders)
        $checkOrder = $Conn->execute("SELECT * FROM greattree_orders WHERE order_id = ? LIMIT 1", [$orderId]);

        if ($checkOrder && $checkOrder->RecordCount() > 0) {
            // 訂單已存在：從 point_history 抓取現有紀錄回傳
            $historySql = "
                SELECT h.point, h.refer_stamp_id as event_id, s.name as event_name
                FROM point_history h
                JOIN stamp s ON h.refer_stamp_id = s.id
                WHERE h.refer_order_id = ? AND h.account_id = ?
            ";
            $historyRs = $Conn->execute($historySql, [$orderId, $accountId]);
            $eventList = [];
            if ($historyRs) {
                while (!$historyRs->EOF) {
                    $row = $historyRs->fetchObj();
                    $eventList[] = [
                        'event_id' => intval($row->event_id),
                        'event_name' => $row->event_name,
                        'point' => intval($row->point)
                    ];
                    $historyRs->MoveNext();
                }
            }
            $this->response['event_list'] = $eventList;
            return $this->setResponse('0006', '訂單已處理，回傳現有點數紀錄');
        }

        // 4. 訂單不存在：讀取商品價格與格式檢查
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

        foreach ($products as $p) {
            $pid = intval($p['product_id'] ?? 0);
            $qty = intval($p['quantity'] ?? 0);
            if ($pid <= 0) {
                throw new Exception("商品編號格式異常或未提供", 3);
            }
            if ($qty <= 0) {
                throw new Exception("商品數量格式異常或未提供", 4);
            }
            if (!isset($priceMap[$pid])) {
                throw new Exception("商品編號不存在: " . $pid, 5);
            }
        }

        // 5. 取得活動清單並計算點數
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT id, name, type FROM stamp WHERE is_active = 1 AND start_time <= ? AND end_time >= ?";
        $eventRs = $Conn->execute($sql, [$now, $now]);

        $eventList = [];
        if ($eventRs && $eventRs->RecordCount() > 0) {
            while (!$eventRs->EOF) {
                $event = $eventRs->fetchObj();
                $qualifiedAmount = 0;

                foreach ($products as $p) {
                    $pid = intval($p['product_id']);
                    $qty = intval($p['quantity']);

                    $isQualified = false;
                    if ($event->type == 'ALL_PRODUCT') {
                        $isQualified = true;
                    }
                    else if ($event->type == 'SPECIFIC_PRODUCT') {
                        $checkRs = $Conn->execute("SELECT count(*) as count FROM stamp_product WHERE event_id = ? AND product_id = ?", [$event->id, $pid]);
                        if ($checkRs && $checkRs->fetchObj()->count > 0) {
                            $isQualified = true;
                        }
                    }

                    if ($isQualified) {
                        $qualifiedAmount += (($priceMap[$pid] ?? 0) * $qty);
                    }
                }

                $ruleRs = $Conn->execute("SELECT threshold_amount, reward_points FROM stamp_rule WHERE event_id = ? AND threshold_amount <= ?", [$event->id, $qualifiedAmount]);
                if ($ruleRs && $ruleRs->RecordCount() > 0) {
                    $rule = $ruleRs->fetchObj();
                    // 不累贈邏輯：只要大於門檻，就只給一次獎勵點數
                    $rewardPoints = $rule->reward_points;

                    if ($rewardPoints > 0) {
                        $eventList[] = [
                            'event_id' => intval($event->id),
                            'event_name' => $event->name,
                            'point' => intval($rewardPoints)
                        ];
                    }
                }
                $eventRs->MoveNext();
            }
        }

        // 6. 寫入資料庫記錄
        if (!empty($eventList)) {
            // A. 寫入大樹訂單表 (greattree_orders)
            foreach ($products as $p) {
                $Conn->autoExecute('greattree_orders', [
                    'order_id' => $orderId,
                    'account_id' => $accountId,
                    'order_date' => date('Y-m-d'),
                    'order_time' => date('H:i:s'),
                    'product_id' => intval($p['product_id']),
                    'quantity' => intval($p['quantity'])
                ], 'INSERT');
            }

            // B. 寫入點數歷史 (point_history)
            foreach ($eventList as $event) {
                $Conn->autoExecute('point_history', [
                    'account_id' => $accountId,
                    'point_type' => 3, // STAMP
                    'point' => $event['point'],
                    'create_at' => date('Y-m-d H:i:s'),
                    'note' => '',
                    'refer_order_id' => $orderId,
                    'refer_stamp_id' => $event['event_id']
                ], 'INSERT');
            }
        }

        $this->response['event_list'] = $eventList;
        return $this->setResponse('0006', '點數計算成功並已記錄');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new StampPointMapping())->run();
}
?>