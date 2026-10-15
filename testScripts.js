// postman，測試站
// ========== 1. API 基礎設定 ==========
const baseUrl = "http://greattree-api.mohist-internal.net"; 
// const baseUrl = "http://localhost/greattree"; // 本地

const loginUrl = `${baseUrl}/GetLoginToken.php`;
const stampUrl = `${baseUrl}/StampPointMapping.php`;

const accountId = "8888"; // 測試站帳號
const dynamicOrderNo = "ORD_" + Date.now(); // 唯一訂單編號

// 3 個商品清單
const myProducts = [
  { sku_id: "SKUA0128", quantity: 3 }, // 100元 X3 = 300元 (product_id: 28)
  { sku_id: "717171",   quantity: 6 }, // 300元 X6 = 1800元 (product_id: 44)
  { sku_id: "000002",   quantity: 3 }  // 200元 X3 = 600元 (product_id: 56)
];

// ========== 2. 定義測試案例 (含精確點數與活動驗證) ==========
const testCases = [
  {
    name: "情境 1: 整筆訂單商品皆不存在 (預期 return_code: 0019)",
    payload: {
      account_id: accountId,
      order_no: "NO_ORD_" + Date.now(),
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
        { sku_id: "SKUA0128", quantity: 3 },
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 10 } // 應被忽略
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 3 },
      { event_id: 88, point: 9 }
    ]
  },
  {
    name: "情境 2: 訂單不存在不可執行退貨 DEDUCT (預期 return_code: 0021)",
    payload: {
      account_id: accountId,
      order_no: "NO_ORD_" + Date.now(),
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
      { event_id: 84, point: 12 },
      { event_id: 86, point: 600 },
      { event_id: 88, point: 81 }
    ]
  },
  {
    name: "情境 4: 訂單已存在，不可重複執行新增 ADD (預期 return_code: 0020)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo, // 重複使用情境 3 的單號
      transaction_type: "ADD",
      refer: "門市",
      products: myProducts
    },
    expectedReturnCode: "0020"
  },
  {
    name: "情境 5: 退貨扣點 DEDUCT (退掉部分或全部商品，預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo, // 退掉情境 3 的單號
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 1 },
        { sku_id: "717171", quantity: 6 }
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 5 },
      { event_id: 86, point: 600 },
      { event_id: 88, point: 24 }
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
      order_no: dynamicOrderNo, // 原購買 SKUA0128 為 3 件，前面情境 5 已退 1 件，剩 2 件
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 10 } // 超過現有數量
      ]
    },
    expectedReturnCode: "0011"
  }
];

// ========== 3. Postman 通用請求封裝 ==========
function sendPostmanRequest(targetUrl, payload, authToken = "") {
  return new Promise((resolve, reject) => {
    const headers = {
      'Content-Type': 'application/json',
      ...(authToken ? { 'Authorization': `Bearer ${authToken}` } : {})
    };

    pm.sendRequest({
      url: targetUrl,
      method: 'POST',
      header: headers,
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

// ========== 4. 主執行程序 ==========
(async function runAll() {
  let token = "";

  // 4.1 先呼叫登入 API 取得 Bearer Token
  try {
    const loginData = await sendPostmanRequest(loginUrl, {
      account_id: "mohist",
      password: "24470678test"
    });

    if (loginData && loginData.token) {
      token = loginData.token;
      console.log("登入成功，取得 Token:", token.substring(0, 20) + "...");
    }
  } catch (loginErr) {
    console.warn("登入 Token 取得失敗或為免驗證環境:", loginErr.message);
  }

  // 4.2 依序執行各情境測試
  for (const tc of testCases) {
    let data = {};
    try {
      data = await sendPostmanRequest(stampUrl, tc.payload, token);

      // 1. 驗證 return_code
      pm.test(tc.name, function () {
        pm.expect(data.return_code).to.eql(tc.expectedReturnCode);
      });

      // 2. 獨立顯示：核對 event_list 活動與點數
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
      // 樹狀 SKIPPED 呈現
      const rqStr = JSON.stringify(tc.payload);
      const rsStr = JSON.stringify(data);

      pm.test.skip(` [RQ]: ${rqStr}`);
      pm.test.skip(` [RS]: ${rsStr}`);
    }
  }
})();
