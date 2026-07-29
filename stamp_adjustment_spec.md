# 印花點數 API 調整規格書 (transaction_type 增減功能)

本文件記錄「印花點數 API」新增與減少點數的實作邏輯需求。

## 1. 新增欄位與參數
* **新增欄位**：`transaction_type`
  * `ADD`：代表新增/追加訂單商品（點數增加）。
  * `DEDUCT`：代表減少/退貨訂單商品（點數減少）。

---

## 2. 業務邏輯調整說明

### A. 訂單商品寫入邏輯
* 取消原本的「訂單已存在即直接回傳、不作處理」的限制。
* 訂單可以無限次新增與減少。
* **資料表欄位拆分設計 (大樹主檔與歷史明細表拆分)**：
  * **主檔 `greattree_orders`**：移除 `product_id` 和 `quantity` 欄位，僅作主檔記錄，並將主鍵設為唯一的 `order_id`。
  * **歷史明細表 `greattree_order_history`**：專門用來記錄所有商品的流水分支。設有自增 `sernum` 主鍵與 `order_id` 索引，`quantity` 設為 `INT(11)` (可接受負數)。
  * **寫入邏輯**：
    1. 當 API 接收到請求，先去 `greattree_orders` 查詢該訂單是否存在。若不存在，則在 `greattree_orders` 內 INSERT 一筆訂單主檔紀錄。
    2. 對於本次異動的每個商品，直接 `INSERT` 寫入一筆細項紀錄至 `greattree_order_history` 中（`DEDUCT` 時數量以負值寫入）。
  * 寫入前均需預檢，確保計算 `SUM(quantity)` 後的累計總量 `quantity` 不會小於零。

### B. 點數重新計算與寫入邏輯
為了準確計算每次調整後應補發或退回的點數，必須採取**「歷史總量對比法」**：

1. **取得該訂單在資料庫中的最新商品總量**：
   * 當新一筆（正數或負數）的商品寫入 `greattree_orders` 後，查詢該訂單目前為止**所有歷史明細的加總數量**：
     ```sql
     SELECT product_id, SUM(quantity) as total_qty 
     FROM greattree_orders 
     WHERE order_id = ? 
     GROUP BY product_id
     ```
2. **重新計算整筆訂單應得的最新總點數 ($P_{new}$)**：
   * 根據步驟 1 算出的各商品最新加總數量，搭配當前的活動規則，重新計算出該訂單截止目前為止**「應該擁有的總點數」**。
3. **查詢該訂單先前已發放的總點數 ($P_{already\_awarded}$)**：
   * 查詢 `point_history` 中該訂單先前已寫入的點數加總：
     ```sql
     SELECT refer_stamp_id as event_id, SUM(point) as total_point 
     FROM point_history 
     WHERE refer_order_id = ? 
     GROUP BY refer_stamp_id
     ```
4. **計算差額並寫入歷史紀錄**：
   * 針對每個活動計算差值：$\Delta P = P_{new} - P_{already\_awarded}$。
   * **若 $\Delta P > 0$**：在 `point_history` 中新增一筆記錄，`point` 填入**正數**差額（補發點數）。
   * **若 $\Delta P < 0$**：在 `point_history` 中新增一筆記錄，`point` 填入**負數**差額（扣回點數）。
   * **若 $\Delta P = 0$**：代表點數沒有變動，不寫入任何點數歷史紀錄。

### C. 累計數量安全檢查
* **防超扣機制**：如果任何商品在計算 `SUM(quantity)` 後的**累積總數量小於零**（代表扣除數量大於購買數量，即退貨超額），API 必須立即中斷並拋出 Exception：
  * `訂單商品扣減數量異常，累計商品數量不可為負數` (Code: 11)
  * `transaction_type 參數異常` (Code: 12)
  * `寫入訂單明細失敗` (Code: 13)
  * 藉此防止倒扣商品數量與不合邏輯的點數虧損（允許累計總量為 0，即全部退貨）。
