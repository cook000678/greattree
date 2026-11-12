// postman，本地
// ========== 1. API 基礎設定 ==========
const url = "http://localhost:8111/greattree/StampPointMapping.php";
const accountId = "7777777"; 
const dynamicOrderNo = "ORD_" + Date.now(); // 動態產生唯一訂單編號

// 本地測試免驗證 Token
const authToken = ""; 

// 3 個商品清單 (包含單價 prices)
const myProducts = [
  { sku_id: "sku_id_1", quantity: 5, prices: 10 },
  { sku_id: "sku_id_8", quantity: 6, prices: 80 },
  { sku_id: "sku_id_10", quantity: 3, prices: 100 }
];

// ========== 2. 定義測試情境  ==========
const testCases = [
  // ----- 1. 建立訂單前的商品存在性驗證 (ADD) -----
  {
    name: "情境 1: 整筆訂單商品皆不存在 ADD (預期 return_code: 0019)",
    payload: {
      account_id: accountId,
      order_no: "ORD_NOT_EXIST_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 1, prices: 100 }
      ]
    },
    expectedReturnCode: "0019"
  },
  {
    name: "情境 1-1: 1個商品存在，1個商品不存在 ADD，忽略不存在的商品 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: "ORD_PARTIAL_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 3, prices: 10 },
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 10, prices: 100 } // 應被忽略
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 11, point: 5 }
    ]
  },
  {
    name: "情境 1-2: 缺少商品單價 prices 防呆 (預期 return_code: 0023)",
    payload: {
      account_id: accountId,
      order_no: "ORD_NO_PRICE_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 1 } // 未提供 prices
      ]
    },
    expectedReturnCode: "0023"
  },
  {
    name: "情境 1-3: 商品數量為小數防呆 (預期 return_code: 0004)",
    payload: {
      account_id: accountId,
      order_no: "ORD_DECIMAL_QTY_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 2.5, prices: 100 }
      ]
    },
    expectedReturnCode: "0004"
  },

  // ----- 2. 訂單不存在時的退貨防呆 (DEDUCT) -----
  {
    name: "情境 2: 訂單不存在不可執行退貨 DEDUCT (預期 return_code: 0022)",
    payload: {
      account_id: accountId,
      order_no: "NO_ORD" + Date.now(),
      transaction_type: "DEDUCT",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0022"
  },

  // ----- 3. 正常建立訂單並贈點 (ADD) -----
  {
    name: "情境 3: 建立訂單全部商品皆存在 ADD (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "ADD",
      refer: "門市",
      products: myProducts // sku_id_1: 5件, sku_id_8: 6件, sku_id_10: 3件
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 3,  point: 80 },
      { event_id: 11, point: 5 },
      { event_id: 12, point: 2 },
      { event_id: 19, point: 56 }
    ]
  },

  // ----- 4. 訂單已存在時的重複新增防呆 (ADD) -----
  {
    name: "情境 4: 訂單已存在，不可重複執行新增 ADD (預期 return_code: 0021)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo, // 重複使用情境 3 的單號
      transaction_type: "ADD",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0021"
  },

  // ----- 5. 訂單退貨扣點流程與防呆 (DEDUCT) -----
  {
    name: "情境 5: 訂單已存在，但 DEDUCT 傳入整筆皆不存在於原訂單的商品 (預期 return_code: 0020)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "NOT_EXIST_SKU_8888", quantity: 1, prices: 100 }, // 不在資料表
        { sku_id: "sku_id_2", quantity: 2, prices: 20 }             // 在資料表但不在原訂單
      ]
    },
    expectedReturnCode: "0020"
  },
  {
    name: "情境 5-1: DEDUCT 扣 1個存在商品 + 1個不存在商品，忽略不存在商品且正常扣點 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 2, prices: 10 },            // 存在商品：原 5 件退 2 件，剩 3 件
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 10, prices: 100 } // 不存在商品：應被自動忽略
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 3,  point: 80 },
      { event_id: 11, point: 5 },
      { event_id: 12, point: 2 },
      { event_id: 19, point: 56 } // sku_id_1 未參與活動19，故點數維持 56 點
    ]
  },
  {
    name: "情境 5-2: DEDUCT 扣掉1個存在的商品 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_8", quantity: 6, prices: 80 } // 退掉 sku_id_8 全部 6 件 (目前剩餘 sku_id_1: 3件, sku_id_10: 3件)
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 3,  point: 30 },
      { event_id: 11, point: 5 },
      { event_id: 19, point: 21 }
    ]
  },
  {
    name: "情境 5-3: DEDUCT 同時退 1個原訂單商品 + 1個資料表有但原訂單沒買過的商品，忽略沒買過者且正常扣點 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 1, prices: 10 }, // 原訂單有（原剩 3 件，退 1 件，剩 2 件）
        { sku_id: "sku_id_2", quantity: 5, prices: 20 }  // 資料表有但原訂單沒買過，應被自動忽略！
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 3,  point: 30 },
      // 活動 11 (美妝區任選三件贈點)：sku_id_1 剩餘 2 件，未達 3 件門檻，故點數歸 0 (不列在 event_list)
      { event_id: 19, point: 21 }
    ]
  },
  {
    name: "情境 5-4: DEDUCT 退貨數量超過原購買剩餘數量，累計不可為負 (預期 return_code: 0011)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 10, prices: 10 } // 原剩 2 件，退 10 件超過現有數量
      ]
    },
    expectedReturnCode: "0011"
  },
  {
    name: "情境 5-5: DEDUCT 傳入重複同一商品且同單價，合併加總後超過剩餘數量 (預期 return_code: 0011)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 2, prices: 10 }, // 原剩 2 件
        { sku_id: "sku_id_1", quantity: 1, prices: 10 }  // 重複傳入同一商品且同單價，合併加總為 3 件，超過剩餘數量 2 件
      ]
    },
    expectedReturnCode: "0011"
  },

  // ----- 6. 訂單退貨完成後，再次新增之防呆 (ADD) -----
  {
    name: "情境 6: 訂單退貨之後，不可再次 ADD (預期 return_code: 0021)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "ADD",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0021"
  },

  // ----- 7. 全數退貨歸零與再次退貨防呆 (DEDUCT) -----
  {
    name: "情境 7: DEDUCT 退掉訂單剩下的所有商品 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 2, prices: 10 },  // 原剩 2 件，退光
        { sku_id: "sku_id_10", quantity: 3, prices: 100 } // 原剩 3 件，退光
      ]
    },
    expectedReturnCode: "0006",
    expectedEventListEmpty: true // 所有商品退光，剩餘點數為 0，event_list 為空陣列
  },
  {
    name: "情境 7-1: 訂單已退完整筆訂單後，再次執行 DEDUCT (數量已全數歸零，不可再退，預期 return_code: 0011)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 1, prices: 10 }
      ]
    },
    expectedReturnCode: "0011"
  },

  // ----- 8. 今日新增邏輯專項測試 (同商品不同單價、同商品同單價合併、扣點不為負) -----
  {
    name: "情境 8: ADD 傳入同商品同單價(合併為1筆) + 同商品不同單價(各寫1筆) (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: "ORD_MULTI_PRICE_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 2, prices: 100 },
        { sku_id: "sku_id_1", quantity: 3, prices: 100 }, // 同商品同單價 -> 合併為 5 件 (500元)
        { sku_id: "sku_id_1", quantity: 1, prices: 120 }  // 同商品不同單價 -> 獨立寫入 1 件 (120元)
      ]
    },
    expectedReturnCode: "0006"
  },
  {
    name: "情境 8-1: DEDUCT 退貨帶入不同單價，且差額大於原有點數時最多扣到歸零，不可為負 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: "ORD_ZERO_POINT_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 3, prices: 100 } // 300元
      ]
    },
    expectedReturnCode: "0006"
  },
  {
    name: "情境 8-2: 內層陣列格式異常傳入非物件防呆 (預期 return_code: 0024)",
    payload: {
      account_id: accountId,
      order_no: "ORD_BAD_INNER_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        "this_is_not_an_object" // 內層商品明細非物件
      ]
    },
    expectedReturnCode: "0024"
  }
];

// ========== 3. Postman 請求封裝函式 ==========
function sendPostmanRequest(targetUrl, payload) {
  return new Promise((resolve, reject) => {
    pm.sendRequest({
      url: targetUrl,
      method: 'POST',
      header: {
        'Content-Type': 'application/json',
        ...(authToken ? { 'Authorization': `Bearer ${authToken}` } : {})
      },
      body: {
        mode: 'raw',
        raw: JSON.stringify(payload)
      }
    }, (err, res) => {
      if (err) return reject(err);
      try {
        resolve(res.json());
      } catch (e) {
        resolve({ raw_response: res.text() });
      }
    });
  });
}

// ========== 4. 批次循序執行所有測試案例 ==========
(async function runAllTests() {
  for (const tc of testCases) {
    let data = {};
    try {
      data = await sendPostmanRequest(url, tc.payload);

      // 1. 驗證 return_code
      pm.test(tc.name, function () {
        pm.expect(data.return_code).to.eql(tc.expectedReturnCode);
      });

      if (tc.expectedEvents && data.return_code === '0006') {
        const pointSummary = tc.expectedEvents.map(e => `[活動${e.event_id}: ${e.point}點]`).join(' ');
        
        pm.test(`  -- 核對點數成功: ${pointSummary}`, function () {
          pm.expect(data.event_list).to.be.an('array');

          tc.expectedEvents.forEach(expected => {
            const matched = (data.event_list || []).find(e => e.event_id === expected.event_id);
            pm.expect(matched, `回傳的 event_list 應該包含活動 ID: ${expected.event_id}`).to.not.be.undefined;
            pm.expect(matched.point, `活動 ID ${expected.event_id} 的點數應為 ${expected.point}`).to.eql(expected.point);
          });
        });
      }

      if (tc.expectedEventListEmpty && data.return_code === '0006') {
        pm.test("  -- 核對點數成功: [全數歸零，event_list 為空]", function () {
          pm.expect(data.event_list).to.be.an('array');
          pm.expect(data.event_list.length, "全數退貨後 event_list 長度應為 0").to.eql(0);
        });
      }

    } catch (err) {
      pm.test(tc.name, function () {
        pm.expect.fail(`請求異常: ${err.message}`);
      });
    } finally {
      // 樹狀 SKIPPED 呈現
      const rqStr = JSON.stringify(tc.payload);
      const rsStr = JSON.stringify(data);

      pm.test.skip(` [RQ]: ${rqStr}`);
      pm.test.skip(` [RS]: ${rsStr}`);
    }
  }
})();

