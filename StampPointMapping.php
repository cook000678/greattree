<?php
require_once 'autoload.php';

class StampPointMapping extends ApiTemplate
{
    protected function execute()
    {
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $accountId = isset($input['account_id']) ? $input['account_id'] : '';
        $orderId = isset($input['order_no']) ? $input['order_no'] : '';
        $products = isset($input['products']) ? $input['products'] : [];
        $transactionType = isset($input['transaction_type']) ? $input['transaction_type'] : '';

        // 1. 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $accountId)) {
            throw new Exception('account_id參數異常只限英文和數字共20碼', 1);
        }

        if (!in_array($transactionType, ['ADD', 'DEDUCT'])) {
            throw new Exception('transaction_type參數異常，僅限ADD或DEDUCT', 12);
        }

        if (empty($products) || !is_array($products)) {
            throw new Exception('商品格式異常', 7);
        }

        // 2. 檢查帳號是否存在
        $memberModel = new Member($DB);
        if (!$memberModel->queryMember($accountId)) {
            throw new Exception("帳號不存在", 2);
        }

        // 3. 讀取商品價格與格式檢查
        $productIds = [];
        foreach ($products as $p) {
            $pid = intval(isset($p['product_id']) ? $p['product_id'] : 0);
            if ($pid > 0) {
                $productIds[] = $pid;
            }
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
            $pid = intval(isset($p['product_id']) ? $p['product_id'] : 0);
            $qty = intval(isset($p['quantity']) ? $p['quantity'] : 0);
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

        // 3.5. 先行撈取當前訂單的商品累積總量，進行安全比對驗證 (改查歷史明細表 greattree_order_history)
        $currentQtyMap = [];
        $currentOrderRs = $Conn->execute("SELECT product_id, SUM(quantity) as total_qty FROM greattree_order_history WHERE order_id = ? GROUP BY product_id", [$orderId]);
        if ($currentOrderRs) {
            while (!$currentOrderRs->EOF) {
                $row = $currentOrderRs->fetchObj();
                $currentQtyMap[intval($row->product_id)] = intval($row->total_qty);
                $currentOrderRs->MoveNext();
            }
        }

        // 預檢本次異動後的數量
        foreach ($products as $p) {
            $pid = intval($p['product_id']);
            $qtyInput = intval($p['quantity']);
            $qtyChange = ($transactionType === 'DEDUCT') ? -$qtyInput : $qtyInput;

            $currentQty = isset($currentQtyMap[$pid]) ? $currentQtyMap[$pid] : 0;
            $newQty = $currentQty + $qtyChange;

            if ($newQty < 0) {
                throw new Exception("訂單商品扣減數量異常，累計商品數量不可為負數", 11);
            }
        }

        // 4. 寫入本次交易紀錄
        // A. 寫入/更新大樹訂單主檔 (greattree_orders) - 只有在主檔不存在時才 INSERT 一次主檔
        $checkOrder = $Conn->execute("SELECT 1 FROM greattree_orders WHERE order_id = ? LIMIT 1", [$orderId]);
        if (!$checkOrder || $checkOrder->RecordCount() == 0) {
            $insertOrderHeader = $Conn->autoExecute('greattree_orders', [
                'order_id' => $orderId,
                'account_id' => $accountId,
                'order_date' => date('Y-m-d'),
                'order_time' => date('H:i:s')
            ], 'INSERT');
            if (!$insertOrderHeader) {
                throw new Exception('寫入訂單主檔失敗，原因：' . $Conn->ErrorMsg(), 13);
            }
        }

        // B. 寫入本次商品異動明細至歷史明細表 (greattree_order_history)
        foreach ($products as $p) {
            $pid = intval($p['product_id']);
            $qtyInput = intval($p['quantity']);
            $qty = ($transactionType === 'DEDUCT') ? -$qtyInput : $qtyInput;

            $insertOrderHistory = $Conn->autoExecute('greattree_order_history', [
                'order_id' => $orderId,
                'product_id' => $pid,
                'quantity' => $qty
            ], 'INSERT');

            if (!$insertOrderHistory) {
                throw new Exception('寫入訂單明細失敗，原因：' . $Conn->ErrorMsg(), 13);
            }
        }

        // 5. 取得該訂單調整後的所有商品歷史最新總量
        $totalProducts = [];
        $totalOrderRs = $Conn->execute("SELECT product_id, SUM(quantity) as total_qty FROM greattree_order_history WHERE order_id = ? GROUP BY product_id", [$orderId]);
        if ($totalOrderRs) {
            while (!$totalOrderRs->EOF) {
                $row = $totalOrderRs->fetchObj();
                $totalProducts[] = [
                    'product_id' => intval($row->product_id),
                    'quantity' => intval($row->total_qty)
                ];
                $totalOrderRs->MoveNext();
            }
        }

        // 6. 重新計算每項印花活動最新應得總點數
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT id, name, type FROM stamp WHERE is_active = 1 AND start_time <= ? AND end_time >= ?";
        $eventRs = $Conn->execute($sql, [$now, $now]);

        $newPointsMap = [];
        if ($eventRs && $eventRs->RecordCount() > 0) {
            while (!$eventRs->EOF) {
                $event = $eventRs->fetchObj();
                $qualifiedAmount = 0;
                $qualifiedQuantity = 0;

                // 先找出該活動的所有符合商品 ids (如果是 SPECIFIC_PRODUCT)
                $specificProductIds = [];
                if ($event->type == 'SPECIFIC_PRODUCT') {
                    $spRs = $Conn->execute("SELECT product_id FROM stamp_product WHERE event_id = ?", [$event->id]);
                    if ($spRs) {
                        while (!$spRs->EOF) {
                            $specificProductIds[] = intval($spRs->fetchObj()->product_id);
                            $spRs->MoveNext();
                        }
                    }
                }

                foreach ($totalProducts as $p) {
                    $pid = $p['product_id'];
                    $qty = $p['quantity'];
                    if ($qty < 0) {
                        $qty = 0; // 若總數量累計為負數，歸零計算
                    }

                    $isQualified = false;
                    if ($event->type == 'ALL_PRODUCT') {
                        $isQualified = true;
                    } else if ($event->type == 'SPECIFIC_PRODUCT') {
                        if (in_array($pid, $specificProductIds)) {
                            $isQualified = true;
                        }
                    }

                    if ($isQualified) {
                        // 如果 priceMap 找不到該 pid（非本次新增商品，而是資料庫先前明細的商品）
                        if (!isset($priceMap[$pid])) {
                            $singlePriceRs = $Conn->execute("SELECT prices FROM product WHERE product_id = ? LIMIT 1", [$pid]);
                            $priceMap[$pid] = ($singlePriceRs && $singlePriceRs->RecordCount() > 0) ? floatval($singlePriceRs->fetchObj()->prices) : 0;
                        }
                        $qualifiedAmount += ($priceMap[$pid] * $qty);
                        $qualifiedQuantity += $qty;
                    }
                }

                // 取得規則，並依門檻金額由高到低排序，以利找出符合的最高門檻
                $ruleSql = "SELECT threshold_type, threshold_amount, reward_points FROM stamp_rule WHERE event_id = ? ORDER BY threshold_amount DESC";
                $ruleRs = $Conn->execute($ruleSql, [$event->id]);

                $rewardPoints = 0;
                if ($ruleRs && $ruleRs->RecordCount() > 0) {
                    while (!$ruleRs->EOF) {
                        $rule = $ruleRs->fetchObj();
                        // 依照模式判斷是否達標：1.滿額(AMOUNT) 2.滿件(QUANTITY)
                        if ($rule->threshold_type == 'AMOUNT') {
                            if ($qualifiedAmount >= $rule->threshold_amount) {
                                $rewardPoints = $rule->reward_points;
                                break;
                            }
                        } else if ($rule->threshold_type == 'QUANTITY') {
                            if ($qualifiedQuantity >= $rule->threshold_amount) {
                                $rewardPoints = $rule->reward_points;
                                break;
                            }
                        }
                        $ruleRs->MoveNext();
                    }
                }

                $newPointsMap[intval($event->id)] = [
                    'event_id' => intval($event->id),
                    'event_name' => $event->name,
                    'points' => intval($rewardPoints)
                ];

                $eventRs->MoveNext();
            }
        }

        // 7. 查詢該訂單先前已發放的總點數
        $alreadyAwardedPointsMap = [];
        $historySql = "SELECT refer_stamp_id as event_id, SUM(point) as total_point FROM point_history WHERE refer_order_id = ? GROUP BY refer_stamp_id";
        $historyRs = $Conn->execute($historySql, [$orderId]);
        if ($historyRs) {
            while (!$historyRs->EOF) {
                $row = $historyRs->fetchObj();
                $alreadyAwardedPointsMap[intval($row->event_id)] = intval($row->total_point);
                $historyRs->MoveNext();
            }
        }

        // 8. 比較並將點數差額寫入點數歷史，並取得本次異動清單
        $eventList = [];

        // 比對目前活動的點數
        foreach ($newPointsMap as $eventId => $eventInfo) {
            $alreadyAwarded = isset($alreadyAwardedPointsMap[$eventId]) ? $alreadyAwardedPointsMap[$eventId] : 0; // 已經回饋的點數
            $targetPoints = $eventInfo['points'];
            $delta = $targetPoints - $alreadyAwarded;

            if ($delta != 0) {
                $insertHistory = $Conn->autoExecute('point_history', [
                    'account_id' => $accountId,
                    'point_type' => 3, // STAMP
                    'point' => $delta,
                    'create_at' => date('Y-m-d H:i:s'),
                    'note' => '',
                    'refer' => '',
                    'refer_order_id' => $orderId,
                    'refer_stamp_id' => $eventId
                ], 'INSERT');

                if (!$insertHistory) {
                    throw new Exception('寫入點數歷史失敗，原因：' . $Conn->ErrorMsg(), 10);
                }

                $eventList[] = [
                    'event_id' => $eventId,
                    'event_name' => $eventInfo['event_name'],
                    'point' => $delta
                ];
            }
        }

        // 比對非目前活動（可能已過期或被停用，但先前有發過點數需退回的情況）
        foreach ($alreadyAwardedPointsMap as $eventId => $alreadyAwarded) {
            if (!isset($newPointsMap[$eventId]) && $alreadyAwarded != 0) {
                $eventObj = $Conn->GetRow("SELECT name FROM stamp WHERE id = ?", [$eventId]);
                $eventName = $eventObj ? $eventObj['name'] : '未知印花活動';

                $delta = -$alreadyAwarded;
                $insertHistory = $Conn->autoExecute('point_history', [
                    'account_id' => $accountId,
                    'point_type' => 3, // STAMP
                    'point' => $delta,
                    'create_at' => date('Y-m-d H:i:s'),
                    'note' => '',
                    'refer' => '',
                    'refer_order_id' => $orderId,
                    'refer_stamp_id' => $eventId
                ], 'INSERT');

                if (!$insertHistory) {
                    throw new Exception('寫入點數歷史失敗，原因：' . $Conn->ErrorMsg(), 10);
                }

                $eventList[] = [
                    'event_id' => $eventId,
                    'event_name' => $eventName,
                    'point' => $delta
                ];
            }
        }

        $this->response['event_list'] = $eventList;
        return $this->setResponse('0006', '點數計算成功並已記錄');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new StampPointMapping())->run();
}
