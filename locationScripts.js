// postman，本地
// ========== 1. API 基礎設定 ==========
const url = "http://localhost/greattree/StampPointMapping.php";
const accountId = "7777777"; 
const dynamicOrderNo = "ORD_" + Date.now(); // 動態產生唯一訂單編號

// 本地測試免驗證 Token
const authToken = ""; 

// 3 個商品清單
const myProducts = [
  { sku_id: "sku_id_1", quantity: 5 },
  { sku_id: "sku_id_8", quantity: 6 },
  { sku_id: "sku_id_10", quantity: 3 }
];

// ========== 2. 定義測試情境 (主管要求：加入預期的活動 ID 與點數) ==========
const testCases = [
  {
    name: "情境 1: 整筆訂單商品皆不存在 (預期 return_code: 0019)",
    payload: {
      account_id: accountId,
      order_no: "ORD_NOT_EXIST_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 1 }
      ]
    },
    expectedReturnCode: "0019"
  },
  {
    name: "情境 1-2: 部分商品存在、部分不存在(預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: "ORD_PARTIAL_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 3 },
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 10 } // 應被忽略
      ]
    },
    expectedReturnCode: "0006",

    expectedEvents: [
      { event_id: 11, point: 5 }
    ]
  },
  {
    name: "情境 2: 訂單不存在不可執行退貨 DEDUCT (預期 return_code: 0021)",
    payload: {
      account_id: accountId,
      order_no: "NO_ORD" + Date.now(),
      transaction_type: "DEDUCT",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0021"
  },
  {
    name: "情境 3: 首次新增訂單贈點 ADD (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "ADD",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0006",

    expectedEvents: [
      { event_id: 3,  point: 80 },
      { event_id: 11, point: 5 },
      { event_id: 12, point: 2 },
      { event_id: 19, point: 56 }
    ]
  },
  {
    name: "情境 4: 訂單已存在，不可重複執行新增 ADD (預期 return_code: 0020)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo, // 使用情境 3 相同的訂單編號
      transaction_type: "ADD",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0020"
  },
  {
    name: "情境 5: 退貨扣點 DEDUCT (退掉部分商品，預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo, // 退掉情境 3 的訂單
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 2 },
        { sku_id: "sku_id_8", quantity: 6 }
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
    name: "情境 4-2: 訂單退貨過後，不可再次 ADD (預期 return_code: 0020)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "ADD",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0020"
  },
  {
    name: "情境 5-2: DEDUCT 退貨數量超過原購買數量，累計不可為負 (預期 return_code: 0011)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo, // 原購買 sku_id_1 為 5 件，前面情境 5 已退 2 件，剩 3 件
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "sku_id_1", quantity: 10 } // 超過現有數量
      ]
    },
    expectedReturnCode: "0011"
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

    } catch (err) {
      pm.test(tc.name, function () {
        pm.expect.fail(`請求異常: ${err.message}`);
      });
    } finally {
      // 主管要求的樹狀 SKIPPED 格式
      const rqStr = JSON.stringify(tc.payload);
      const rsStr = JSON.stringify(data);

      pm.test.skip(` [RQ]: ${rqStr}`);
      pm.test.skip(` [RS]: ${rsStr}`);
    }
  }
})();

