# AI 助手指令與偏好

## 語言設定
- **請務必使用「繁體中文」回答我所有的問題。** (Always respond in Traditional Chinese.)
- 所有的代碼註解 (Comments) 也請使用繁體中文編寫。

## 任務格式 (Task Format)
- 當列出 Task 或實作步驟時，請使用繁體中文描述任務內容。
- 每個 Task 的標題與說明必須清晰且符合台灣技術術語。

## 技術背景 (根據目前進度)
- 本機環境：PHP 7.2.34 (Windows Apache / Docker 容器 `gt_php72_server`，對外映射 Port `8111`)
- 資料庫環境：MariaDB 10.2 / MySQL (Docker 容器 `shared-db-test`，Port `13306`)
- 開發目標：會員系統點數 API (含建立會員卡、修改等級、查詢紀錄、建立紅利 API、印花點數 API)


# 專案開發規範 (GreatTree Member API)

## 1. 環境與架構
- **PHP 版本**: 7.2.34 (Zend Engine v3.2.0)。
- **外部套件**: 使用 ADOdb 作為資料庫封裝層。
- **基底類別**: 所有 API 必須繼承 `ApiTemplate` 並實做 `execute()` 方法。
- **自動載入**: 依賴 `autoload.php` 載入 `core/` 與 `Model/`。

## 2. 資料庫開發規範 (ADOdb)
- **連線方式**: 透過 `new DB()` 取得連線。在本機開發環境使用 `root/root`。
- **資料表引擎注意事項**:
  - 現行資料表（如 `greattree_orders`、`greattree_order_history`）為 **MyISAM**，不支援事務交易與行級鎖 (`FOR UPDATE`)。
  - 當欄位（如 `price`）具備 `UNSIGNED` 屬性時，計算退貨負數數量相乘必須使用 `CAST(quantity AS SIGNED) * CAST(price AS SIGNED)`，防止 MySQL / MariaDB 出現 `BIGINT UNSIGNED value is out of range` 錯誤。
  - 重要金額或數量統計查詢時，務必檢查 `if (!$rs)` 並拋出異常，切勿靜默吞錯導致誤算點數。
- **重要 (防止死迴圈)**: 
  - 當使用 `while` 遍歷查詢結果 (`RecordSet`) 時，**必須**在迴圈底部呼叫 `$rs->MoveNext()`。
  - 建議寫法：`while (!$rs->EOF) { ... $rs->MoveNext(); }`。
- **資料讀取**: 為了與現有 Model 保持一致，優先使用 `$rs->fetchObj()` 將結果轉換為物件。

## 3. API 溝通規格 (依據 PDF 文件)
- **回應格式**: 必須為 JSON。
- **回傳欄位**: 固定使用 snake_case 名稱（已在 ApiTemplate 定義）：
  - `return_code`: 4 位數字字串（例：`0000`）。
  - `return_msg`: 對應訊息。
- **成功狀態**: 統一為 `return_code: "0006"`，`return_msg: "點數計算成功並已記錄"`（或依各 API 指定之成功訊息如 `"接收成功"`）。
- **異常處理**: 在 `execute()` 內使用 `throw new Exception("訊息", 代碼)`。代碼會自動補足 4 位數轉為 `return_code`。
- **印花點數錯誤代碼清冊 (`StampPointMapping.php`)**:
  - `0001`: `account_id參數異常僅限英文和數字共20碼`
  - `0002`: `帳號不存在`
  - `0003`: `sku_id格式異常或未提供`
  - `0004`: `商品數量格式異常或未提供` (嚴格純整數，不可為小數或 <= 0)
  - `0007`: `商品清單格式異常或未提供` (外層 products 格式非陣列或為空)
  - `0010`: `差額寫入點數歷史失敗`
  - `0011`: `訂單商品扣減數量異常，累計商品數量不可為負數`
  - `0012`: `transaction_type參數異常，僅限ADD或DEDUCT`
  - `0013`: `寫入訂單主檔失敗`
  - `0014`: `寫入訂單明細失敗`
  - `0015`: `過期活動點數寫入點數歷史失敗`
  - `0016`: `order_no參數異常僅限英文、數字、底線與減號共30碼`
  - `0017`: `訂單所屬帳號與傳入帳號不一致`
  - `0018`: `refer參數異常，僅限門市或線上`
  - `0019`: `整筆訂單所有商品都不存在` (ADD 時所有傳入商品皆不在 product 表)
  - `0020`: `整筆退貨商品皆不存在於原訂單中` (DEDUCT 時所有商品皆未曾於該訂單購買過)
  - `0021`: `訂單編號已存在，不可重複執行新增` (訂單已存在時不可再做 ADD)
  - `0022`: `訂單不存在，無法執行退貨` (訂單不存在時不可做 DEDUCT)
  - `0023`: `商品單價格式異常或未提供` (price 必須為純整數且 >= 0)
  - `0024`: `商品明細格式異常` (products 陣列內元素非物件/陣列)
  - `0025`: `查詢訂單明細統計失敗` (SQL 執行異常防護)

## 4. 業務邏輯重點
- **印花點數 (Stamp Point)**:
  - 需檢查 `stamp` 是否為啟用 (`is_active=1`) 且在效期內。
  - 活動分為 `ALL_PRODUCT` (全館) 與 `SPECIFIC_PRODUCT` (指定商品)。
  - **累計邏輯**: 一筆訂單中的商品可以同時符合多個活動，點數需分別計算並列出。
  - **門檻邏輯**: 
    - 滿額 (`AMOUNT`)：累加所有符合階梯之倍數點數，無 break。
    - 滿件 (`QUANTITY`)：累加所有符合階梯之倍數點數，無 break。

## 5. 常見測試工具
- `verify_api.php`: 用於模擬前端傳入 JSON 並測試 API 邏輯。
- `test_db.php`: 用於檢查資料庫連線狀態。
- `locationScripts.js`: 本地端 Postman 批次整合測試腳本（活動 3, 11, 12, 19）。
- `testScripts.js`: 測試站 Postman 批次整合測試腳本（自動取得 Token，活動 84）。

---

# 專案異動紀錄 (Change Log)

## 2026-09-17 印花點數 API 規格更新 (`StampPointMapping.php`)

### 一、 需求背景
- 外部 API Request 的 `products` 欄位原本為 `product_id`，現依照新規格改為傳入 `sku_id` (`VARCHAR(20)`)。
- 專案內部資料庫各表（`greattree_order_history` 訂單明細表、`stamp_product` 印花商品表）依然維持使用 `product_id`。

### 二、 核心調整邏輯
1. **輸入參數驗證 (3.2)**：
   - 驗證 `sku_id` 是否為空字串或未提供 ➔ 異常時拋出 Exception 3 (`sku_id格式異常或未提供`)。
   - 驗證 `quantity` 是否小於等於 0 ➔ 異常時拋出 Exception 4 (`商品數量格式異常或未提供`)。
2. **查詢 product 表與欄位轉譯 (3.3 & 3.4)**：
   - 透過 `SELECT product_id, sku_id, prices FROM product WHERE sku_id IN (...)` 查出商品資訊。
   - 建立 `sku_id => product_id` 與 `product_id => prices` 的映射表。
   - 若傳入的 `sku_id` 在 `product` 表找不到 ➔ 拋出 Exception 5 (`sku_id不存在: {sku_id}`)。
3. **歷史訂單商品價格補查 (3.5)**：
   - 補查歷史訂單既有商品的價格，避免退貨／部分退貨時因未傳入既有商品而漏算未退商品的金額。
4. **數量累計與防負數檢查 (3.6 & 3.7)**：
   - 依轉譯後的 `product_id` 進行數量加總比對，若退貨扣減後累計數量小於 0 則拋出 Exception 11。
5. **歷史明細寫入 (4.B)**：
   - `greattree_order_history` 明細表寫入轉譯後的 `product_id`，維持內部資料庫型態一致性。
6. **資料庫 SQL 同步 (`sql/greattree_member.sql`)**：
   - `product` 表增加 `sku_id varchar(20) DEFAULT NULL` 欄位與 `KEY sku_id (sku_id)` 索引，並補齊測試資料預設值。

---

## 2026-11-12 印花點數 API 滿額與單價新邏輯重大更新 (`StampPoint_v2`)

### 一、 需求背景
- 舊邏輯商品單價取自 `product.prices`，無法支援同商品促銷或不同單價之業務場景。
- 舊邏輯滿件與滿額取最高階梯（含 `break`），現業務調整為所有符合門檻全動回饋（累加倍數）。
- 原有退貨計算易發生負數點數、訂單主檔金額被改動，以及未過濾無效商品等問題。

### 二、 核心調整邏輯
1. **商品單價 (`price`) 必填與多單價機制**：
   - 外部每項商品強制提供 `price` 參數（純整數且 $\ge 0$），否則報 `0023`。全面廢除並忽略 `product.prices` 欄位。
   - **同商品多單價機制 (ADD / DEDUCT 均適用)**：
     - 不論是 `ADD` 還是 `DEDUCT`，同一個 `sku_id` 都允許帶入不同的 `price`（例如不同批次促銷價、優惠價與原價並存）。
     - **聚合寫入明細**：傳入清單中，若有**相同 `sku_id` 且相同 `price`**，必須合併數量為一筆寫入 `greattree_order_history`；若**相同 `sku_id` 但不同 `price`**，則各自獨立寫入一筆至 `greattree_order_history`。
   - **退貨數量防呆**：以 `product_id`（商品總數量）校驗退貨上限，累計數量不可為負數（報 `0011`）。
2. **訂單主檔金額只寫一次**：
   - `greattree_orders.order_amount` 僅在 `ADD` 時寫入商品總金額；`DEDUCT` 退貨時**絕不更動**主檔金額。
   - 限制訂單只能 `ADD` 一次，重複 `ADD` 報 `0021`；訂單不存在 `DEDUCT` 報 `0022`。
3. **滿額 (AMOUNT) 與滿件 (QUANTITY) 全階梯累加**：
   - 移除 `break`，各階梯依 `$multiplier = floor(qualifiedAmount / threshold_amount)` 累進累加 `$rewardPoints`。
4. **退貨扣點核心算法（淨額重算差額制）與扣點下限 (Net Amount Recalculation & Floor Capping)**：
   - **主管糾正的核心扣點哲學（重要）**：
     - 退貨不是單純將退貨商品的金額換算成扣點（例如退200元就獨立扣2點是錯誤的）。
     - **正確算法**：退貨必須以訂單**「剩餘淨額」重新計算該活動最新應得總點數**，再與該訂單先前已發放的累計點數（`alreadyAwarded`）相比對，寫入差額（`diffPoint = targetPoints - alreadyAwarded`）。
     - 範例：ADD 購買 300 元（滿 100 送 1 點 ➔ 拿 3 點）。退貨 200 元後，訂單剩餘淨額 100 元，重算最新應得 1 點；與先前已拿 3 點比對，差額為 `1 - 3 = -2` 點，故寫入扣點 `-2` 點。
   - **退貨活動範圍**：
     - 退貨時僅對原訂單曾獲贈點之活動進行評估，其餘當前活動不看。
   - **訂單金額重新統計**：
     - 由明細重新統計 `SUM(CAST(quantity AS SIGNED) * CAST(price AS SIGNED))`。
   - **點數下限為 0 (不可為負數)**：
     - 若同一個 `sku_id` 退貨時帶入的單價金額比原本 ADD 購買時更高，計算出的扣點可能超出原本已回饋的點數。
     - 此時點數即便被扣完，也**絕不能繼續扣成負數**寫入 `point_history`。
     - 訂單在 `point_history` 的累計點數 `SUM(point)` 最小就是 0。
     - **已為 0 時持續退貨的處理**：若該活動累計點數已經歸 0，但後續還有其他商品要繼續退貨，差額 `$diffPoint` 為 0，不會也不可再扣負數點數（前端回傳的 `event_list` 點數為 0 或不顯示，歷史紀錄維持下限 0）。
5. **商品過濾防呆**：
   - `ADD` 時忽略不存在於 `product` 表的商品，若整筆皆不存在報 `0019`。
   - `DEDUCT` 時忽略原訂單沒買過的商品（即使存在於 `product` 表但未曾於該訂單購買過也忽略），若整筆退貨皆非原訂單商品報 `0020`。
6. **點數型態常數固定**：
   - 印花點數在 `point_history` 表寫入時固定為 `point_type = 3`（對應 `point_types` 表的 `STAMP`）。
7. **SQL 防溢位與防靜默出錯**：
   - 因 `greattree_order_history.price` 在部分環境具備 `UNSIGNED` 屬性，相乘時強制加入 `CAST(... AS SIGNED)` 防止負數溢位。
   - 明細統計查詢加入 `if (!$rs)` 拋出 `0025` 防呆，避免因查詢異常誤扣光點數。
