<?php
require_once 'autoload.php';

class StampPointMapping extends ApiTemplate
{
    /**
     * 檢查數值是否為純整數
     * @param mixed $value
     * @return bool
     */
    private function isIntegerValue($value): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1);
    }

    protected function execute()
    {
        date_default_timezone_set('Asia/Taipei');
        $DB = new DB();
        $Conn = $DB->getConnection();

        $input = $this->input;
        $accountId = isset($input['account_id']) ? $input['account_id'] : '';
        $orderId = isset($input['order_no']) ? $input['order_no'] : '';
        $products = isset($input['products']) ? $input['products'] : [];
        $transactionType = isset($input['transaction_type']) ? $input['transaction_type'] : ''; // (ADD / DEDUCT)
        $refer = isset($input['refer']) ? $input['refer'] : ''; // (線上 / 門市)

        // 1. 參數驗證
        if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $accountId)) {
            throw new Exception('account_id參數異常僅限英文和數字共20碼', 1);
        }

        if (!preg_match('/^[a-zA-Z0-9_-]{1,30}$/', $orderId)) {
            throw new Exception('order_no參數異常僅限英文、數字、底線與減號共30碼', 16);
        }

        if (!in_array($transactionType, ['ADD', 'DEDUCT'])) {
            throw new Exception('transaction_type參數異常，僅限ADD或DEDUCT', 12);
        }

        if (empty($products) || !is_array($products)) {
            throw new Exception('商品清單格式異常或未提供', 7);
        }

        if ($refer != "門市" && $refer != "線上") {
            throw new Exception('refer參數異常，僅限門市或線上', 18);
        }

        // 2. 檢查帳號是否存在
        $memberModel = new Member($DB);
        if (!$memberModel->queryMember($accountId)) {
            throw new Exception("帳號不存在", 2);
        }

        // 2.2檢查訂單若已存在，與傳入的account_id是否一致，且若已存在則不可再做ADD
        $existingOrder = $Conn->GetRow("SELECT account_id FROM greattree_orders WHERE order_id = ?", [$orderId]);
        if ($existingOrder) {
            if ($existingOrder['account_id'] !== $accountId) {
                throw new Exception("訂單所屬帳號與傳入帳號不一致", 17);
            }
            if ($transactionType === 'ADD') {
                throw new Exception("訂單編號已存在，不可重複執行新增", 21);
            }
        } else {
            if ($transactionType === 'DEDUCT') {
                throw new Exception("訂單不存在，無法執行退貨", 22);
            }
        }

        // 3. 取當前訂單的商品總量
        $currentQtyMap = [];
        $currentOrderRs = $Conn->execute("SELECT product_id, SUM(quantity) as total_qty FROM greattree_order_history WHERE order_id = ? GROUP BY product_id", [$orderId]);
        if ($currentOrderRs) {
            while (!$currentOrderRs->EOF) {
                $row = $currentOrderRs->fetchObj();
                $currentQtyMap[intval($row->product_id)] = intval($row->total_qty);
                $currentOrderRs->MoveNext();
            }
        }

        // 3.2. 驗證傳入商品的 sku_id、quantity 與 price 格式（嚴格檢查純整數，避免小數被靜默截斷）
        $inputSkuIds = [];
        foreach ($products as $p) {
            if (!is_array($p)) {
                throw new Exception("商品明細格式異常", 24);
            }

            $skuId = isset($p['sku_id']) ? trim(strval($p['sku_id'])) : '';
            if ($skuId === '') {
                throw new Exception("sku_id格式異常或未提供", 3);
            }

            if (!isset($p['quantity']) || !$this->isIntegerValue($p['quantity']) || intval($p['quantity']) <= 0) {
                throw new Exception("商品數量格式異常或未提供", 4);
            }

            if (!isset($p['price']) || !$this->isIntegerValue($p['price']) || intval($p['price']) < 0) {
                throw new Exception("商品單價格式異常或未提供", 23);
            }

            $inputSkuIds[] = $skuId;
        }
        $inputSkuIds = array_unique($inputSkuIds);

        // 3.3. 透過 sku_id 查詢 product 資料表取得 product_id
        $skuToProductMap = [];

        if (!empty($inputSkuIds)) {
            $skuPlaceholders = implode(',', array_fill(0, count($inputSkuIds), '?'));
            $productRs = $Conn->execute("SELECT product_id, sku_id FROM product WHERE sku_id IN ($skuPlaceholders)", $inputSkuIds);
            if ($productRs) {
                while (!$productRs->EOF) {
                    $pObj = $productRs->fetchObj();
                    $pid = intval($pObj->product_id);
                    $skuToProductMap[strval($pObj->sku_id)] = $pid;
                    $productRs->MoveNext();
                }
            }
        }

        // 3.4. 忽略不存在的商品：
        // 若為 ADD：只保留存在於 product 資料表的商品，若整筆都不存在則拋錯 (代碼 19)
        // 若為 DEDUCT：只保留原訂單中曾購買過的商品，其他商品（無論資料表是否存在）皆直接忽略；
        //              若整筆退貨商品皆不屬於原訂單，則拋錯 (代碼 20)
        $validProducts = [];
        foreach ($products as $p) {
            $skuId = trim(strval($p['sku_id']));
            if (isset($skuToProductMap[$skuId])) {
                $pid = $skuToProductMap[$skuId];
                if ($transactionType === 'DEDUCT') {
                    // 退貨只管原訂單內有的商品；只要原訂單曾購買過，即為有效商品，未購買過者直接忽略
                    if (isset($currentQtyMap[$pid])) {
                        $validProducts[] = $p;
                    }
                } else {
                    $validProducts[] = $p;
                }
            }
        }

        if (empty($validProducts)) {
            if ($transactionType === 'DEDUCT') {
                throw new Exception("整筆退貨商品皆不存在於原訂單中", 20);
            } else {
                throw new Exception("整筆訂單所有商品都不存在", 19);
            }
        }

        // 3.5. 收集傳入商品明細，依 (sku_id + price) 聚合合併
        // 若 sku_id 和 price 都相同則合併數量；若 sku_id 相同但 price 不同則視為不同明細項目
        $mergedProducts = [];
        $inputQtyMap = []; // 依 product_id 累計總數量，用於校驗總扣減數量
        $totalAddAmount = 0; // 本次 ADD 商品總金額

        foreach ($validProducts as $p) {
            $skuId = trim(strval($p['sku_id']));
            $pid = $skuToProductMap[$skuId];
            $qty = intval($p['quantity']);
            $price = intval($p['price']);

            $key = $skuId . '_' . $price;
            if (!isset($mergedProducts[$key])) {
                $mergedProducts[$key] = [
                    'sku_id' => $skuId,
                    'product_id' => $pid,
                    'price' => $price,
                    'quantity' => 0
                ];
            }
            $mergedProducts[$key]['quantity'] += $qty;

            if (!isset($inputQtyMap[$pid])) {
                $inputQtyMap[$pid] = 0;
            }
            $inputQtyMap[$pid] += $qty;

            if ($transactionType === 'ADD') {
                $totalAddAmount += ($qty * $price);
            }
        }

        // 3.6. 將API傳入的數量和資料表比對，若扣完後小於0則拋錯
        foreach ($inputQtyMap as $pid => $qtyInput) {
            $qtyChange = ($transactionType === 'DEDUCT') ? -$qtyInput : $qtyInput;

            $currentQty = isset($currentQtyMap[$pid]) ? $currentQtyMap[$pid] : 0;
            $newQty = $currentQty + $qtyChange;

            if ($newQty < 0) {
                throw new Exception("訂單商品扣減數量異常，累計商品數量不可為負數", 11);
            }
        }

        // 4. 寫入本次交易紀錄
        // A. 寫入/更新大樹訂單主檔 (greattree_orders) - 只有在主檔不存在時才 INSERT 一次主檔 (order_amount 僅在 ADD 時寫入)
        $checkOrder = $Conn->execute("SELECT 1 FROM greattree_orders WHERE order_id = ? AND account_id = ? LIMIT 1", [$orderId, $accountId]);
        if (!$checkOrder || $checkOrder->RecordCount() == 0) {
            $insertOrderHeader = $Conn->autoExecute('greattree_orders', [
                'order_id' => $orderId,
                'account_id' => $accountId,
                'order_date' => date('Y-m-d'),
                'order_time' => date('H:i:s'),
                'order_amount' => $totalAddAmount
            ], 'INSERT');
            if (!$insertOrderHeader) {
                throw new Exception('寫入訂單主檔失敗', 13);
            }
        }

        // B. 寫入本次商品異動明細至歷史明細表 (greattree_order_history，依 sku_id + price 合併後寫入，同商品且同單價合併為一筆)
        foreach ($mergedProducts as $item) {
            $pid = $item['product_id'];
            $price = $item['price'];
            $qty = ($transactionType === 'DEDUCT') ? -$item['quantity'] : $item['quantity'];

            $insertOrderHistory = $Conn->autoExecute('greattree_order_history', [
                'order_id' => $orderId,
                'product_id' => $pid,
                'quantity' => $qty,
                'price' => $price
            ], 'INSERT');

            if (!$insertOrderHistory) {
                throw new Exception('寫入訂單明細失敗', 14);
            }
        }

        // 5. 取得該訂單調整後的所有商品歷史明細，統計各商品最新總數量與金額
        $totalProducts = [];
        $totalOrderHistoryRs = $Conn->execute("SELECT product_id, SUM(quantity) as total_qty, SUM(CAST(quantity AS SIGNED) * CAST(price AS SIGNED)) as total_amount FROM greattree_order_history WHERE order_id = ? GROUP BY product_id", [$orderId]);
        if (!$totalOrderHistoryRs) {
            throw new Exception('查詢訂單明細統計失敗', 25);
        }
        while (!$totalOrderHistoryRs->EOF) {
            $row = $totalOrderHistoryRs->fetchObj();
            $totalProducts[] = [
                'product_id' => intval($row->product_id),
                'quantity' => intval($row->total_qty),
                'amount' => intval($row->total_amount)
            ];
            $totalOrderHistoryRs->MoveNext();
        }
        // 6. 查詢該訂單先前已發放的總點數
        $alreadyAwardedPointsMap = [];
        $historySql = "SELECT refer_stamp_id as event_id, SUM(point) as total_point FROM point_history WHERE refer_order_id = ? AND account_id = ? AND point_type = 3 GROUP BY refer_stamp_id";
        $historyRs = $Conn->execute($historySql, [$orderId, $accountId]);
        if ($historyRs) {
            while (!$historyRs->EOF) {
                $row = $historyRs->fetchObj();
                $alreadyAwardedPointsMap[intval($row->event_id)] = intval($row->total_point);
                $historyRs->MoveNext();
            }
        }

        // 7. 重新計算每項印花活動最新應得總點數
        $now = date('Y-m-d H:i:s');

        // 收集所有需要比對的活動 ID：
        // 若為 DEDUCT，只針對先前 ADD 給過的活動進行判斷，其他活動不看
        // 若為 ADD，則比對目前有效活動 + 先前活動
        $alreadyAwardedEventIds = array_keys($alreadyAwardedPointsMap);

        if ($transactionType === 'DEDUCT') {
            $allEventIds = $alreadyAwardedEventIds;
            $activeEventIds = []; // DEDUCT 不看當前其他活動
        } else {
            $activeEventIds = [];
            $activeEventRs = $Conn->execute("SELECT id FROM stamp WHERE is_active = 1 AND start_time <= ? AND end_time >= ?", [$now, $now]);
            if ($activeEventRs) {
                while (!$activeEventRs->EOF) {
                    $activeEventIds[] = intval($activeEventRs->fetchObj()->id);
                    $activeEventRs->MoveNext();
                }
            }
            $allEventIds = array_unique(array_merge($activeEventIds, $alreadyAwardedEventIds));
        }

        $newPointsMap = [];
        if (!empty($allEventIds)) {
            $placeholders = implode(',', array_fill(0, count($allEventIds), '?'));
            $eventRs = $Conn->execute("SELECT id, name, type FROM stamp WHERE id IN ($placeholders)", $allEventIds);

            if ($eventRs && $eventRs->RecordCount() > 0) {
                while (!$eventRs->EOF) {
                    $event = $eventRs->fetchObj();
                    $qualifiedAmount = 0;
                    $qualifiedQuantity = 0;

                    // 先找出該活動的所有符合商品 id (如果是 SPECIFIC_PRODUCT)
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
                        $itemAmount = $p['amount'];
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
                            $qualifiedAmount += $itemAmount;
                            $qualifiedQuantity += $qty;
                        }
                    }

                    // 避免扣退貨金額大於原始金額導致 $qualifiedAmount 為負數
                    if ($qualifiedAmount < 0) {
                        $qualifiedAmount = 0;
                    }

                    // 取得規則，並依門檻金額排序
                    $ruleSql = "SELECT threshold_type, threshold_amount, reward_points FROM stamp_rule WHERE event_id = ? ORDER BY threshold_amount DESC";
                    $ruleRs = $Conn->execute($ruleSql, [$event->id]);

                    $rewardPoints = 0;
                    if ($ruleRs && $ruleRs->RecordCount() > 0) {
                        while (!$ruleRs->EOF) {
                            $rule = $ruleRs->fetchObj();
                            // 依照模式判斷是否達標：滿額(AMOUNT) 與 滿件(QUANTITY) 皆累加各階梯倍數
                            if ($rule->threshold_type == 'AMOUNT') {
                                if ($rule->threshold_amount > 0 && $qualifiedAmount >= $rule->threshold_amount) {
                                    $multiplier = floor($qualifiedAmount / $rule->threshold_amount);
                                    $rewardPoints += ($multiplier * $rule->reward_points);
                                }
                            } else if ($rule->threshold_type == 'QUANTITY') {
                                if ($rule->threshold_amount > 0 && $qualifiedQuantity >= $rule->threshold_amount) {
                                    $multiplier = floor($qualifiedQuantity / $rule->threshold_amount);
                                    $rewardPoints += ($multiplier * $rule->reward_points);
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
        }

        // 8. 比較並將點數差額寫入點數歷史，並取得本次異動清單
        $eventList = [];

        // 比對目前活動的點數
        foreach ($newPointsMap as $eventId => $eventInfo) {
            $alreadyAwarded = isset($alreadyAwardedPointsMap[$eventId]) ? $alreadyAwardedPointsMap[$eventId] : 0; // 已經回饋的點數
            $targetPoints = $eventInfo['points'];
            $diffPoint = $targetPoints - $alreadyAwarded;

            // 限制已過期活動（不在目前有效活動 activeEventIds 中）僅能退回點數，不可追加
            if (!in_array($eventId, $activeEventIds) && $diffPoint > 0) {
                $diffPoint = 0;
            }

            // 扣到沒點數為止：若本次為扣點，最多扣回已發點數 ($alreadyAwarded)，確保該活動累計 SUM(point) 不為負數
            if ($diffPoint < 0) {
                if ($alreadyAwarded <= 0) {
                    $diffPoint = 0;
                } else if (abs($diffPoint) > $alreadyAwarded) {
                    $diffPoint = -$alreadyAwarded;
                }
            }

            if ($diffPoint != 0) {
                $insertHistory = $Conn->autoExecute('point_history', [
                    'account_id' => $accountId,
                    'point_type' => 3, // STAMP
                    'point' => $diffPoint,
                    'note' => ($transactionType === 'DEDUCT') ? '退貨' : "",
                    'refer' => $refer,
                    'refer_order_id' => $orderId,
                    'refer_stamp_id' => $eventId
                ], 'INSERT');

                if (!$insertHistory) {
                    throw new Exception('差額寫入點數歷史失敗', 10);
                }
            }
        }

        // 比對非目前活動（可能已過期或被停用，但先前有發過點數需退回的情況）
        foreach ($alreadyAwardedPointsMap as $eventId => $alreadyAwarded) {
            if (!isset($newPointsMap[$eventId]) && $alreadyAwarded != 0) {
                // 若是 ADD 交易，且活動已從資料庫中完全刪除，不可在此時將先前的點數歸零
                if ($transactionType === 'ADD') {
                    continue;
                }

                // 扣到沒點數為止：最多只扣回正數已發點數
                $diffPoint = ($alreadyAwarded > 0) ? -$alreadyAwarded : 0;
                if ($diffPoint != 0) {
                    $insertHistory = $Conn->autoExecute('point_history', [
                        'account_id' => $accountId,
                        'point_type' => 3, // STAMP
                        'point' => $diffPoint,
                        'note' => ($transactionType === 'DEDUCT') ? '退貨' : "",
                        'refer' => $refer,
                        'refer_order_id' => $orderId,
                        'refer_stamp_id' => $eventId
                    ], 'INSERT');
                    if (!$insertHistory) {
                        throw new Exception('過期活動點數寫入點數歷史失敗', 15);
                    }
                }
            }
        }

        // 返回改為該筆訂單所有的印花點數，event_list 裡面同一個活動只會有一筆
        $eventListSql = "SELECT SUM(ph.point) AS point, ph.refer_stamp_id, s.name FROM point_history AS ph LEFT JOIN stamp AS s ON ph.refer_stamp_id = s.id WHERE ph.refer_order_id = ? AND ph.point_type = 3 AND ph.account_id = ? GROUP BY ph.refer_stamp_id, s.name";
        $eventListRs = $Conn->execute($eventListSql, [$orderId, $accountId]);
        if ($eventListRs && $eventListRs->RecordCount() > 0) {
            while (!$eventListRs->EOF) {
                $row = $eventListRs->fetchObj();
                $pointSum = intval($row->point);
                if ($pointSum > 0) {
                    $eventList[] = [
                        'event_id' => intval($row->refer_stamp_id),
                        'event_name' => $row->name ? $row->name : '未知印花活動',
                        'point' => $pointSum
                    ];
                }
                $eventListRs->MoveNext();
            }
        }

        $this->response['event_list'] = $eventList;
        return $this->setResponse('0006', '點數計算成功並已記錄');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    (new StampPointMapping())->run();
}
