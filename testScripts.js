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

// ========== 2. 定義測試案例 (含精確點數與活動驗證，按邏輯順序編號) ==========
const testCases = [
  // ----- 1. 建立訂單前的商品存在性驗證 (ADD) -----
  {
    name: "情境 1: 整筆訂單商品皆不存在 ADD (預期 return_code: 0019)",
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
    name: "情境 1-1: 1個商品存在，1個商品不存在 ADD，忽略不存在的商品 (預期成功 0006)",
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

  // ----- 2. 訂單不存在時的退貨防呆 (DEDUCT) -----
  {
    name: "情境 2: 訂單不存在不可執行退貨 DEDUCT (預期 return_code: 0022)",
    payload: {
      account_id: accountId,
      order_no: "NO_ORD_" + Date.now(),
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
      products: myProducts
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 12 },
      { event_id: 86, point: 600 },
      { event_id: 88, point: 81 }
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
        { sku_id: "NOT_EXIST_SKU_8888", quantity: 1 }, // 不在資料表
        { sku_id: "000001", quantity: 2 }              // 在資料表但不在原訂單
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
        { sku_id: "SKUA0128", quantity: 1 },            // 存在商品：原 3 件退 1 件，剩 2 件
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 10 }  // 不存在商品：應被自動忽略
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 11 },
      { event_id: 86, point: 600 },
      { event_id: 88, point: 78 }
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
        { sku_id: "717171", quantity: 6 } // 退掉 717171 全部 6 件 (目前剩餘 SKUA0128: 2件, 000002: 3件)
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
    name: "情境 5-3: DEDUCT 同時退 1個原訂單商品 + 1個資料表有但原訂單沒買過的商品，忽略沒買過者且正常扣點 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 1 }, // 原訂單有（原剩 2 件，退 1 件，剩 1 件）
        { sku_id: "000001", quantity: 5 }   // 資料表有但原訂單沒買過，應被自動忽略！
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 4 },           // 剩餘 SKUA0128: 1件, 000002: 3件 => 4件
      { event_id: 86, point: 600 },         // 000002 依然 600元 => 600點
      { event_id: 88, point: 21 }          // 總金額 100 + 600 = 700元 => floor(700/100)*3 = 21點
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
        { sku_id: "SKUA0128", quantity: 10 } // 原剩 1 件，退 10 件超過現有數量
      ]
    },
    expectedReturnCode: "0011"
  },
  {
    name: "情境 5-5: DEDUCT 傳入重複同一商品，合併加總後超過剩餘數量 (預期 return_code: 0011)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 1 }, // 原剩 1 件
        { sku_id: "SKUA0128", quantity: 1 }  // 重複傳入同一商品，合併加總為 2 件，超過剩餘數量 1 件
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
        { sku_id: "SKUA0128", quantity: 1 }, // 原剩 1 件，退光
        { sku_id: "000002",   quantity: 3 }  // 原剩 3 件，退光
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
        { sku_id: "SKUA0128", quantity: 1 }
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
            pm.expect(matched.point, `活動 ID ${expected.event_id} 點數不符！預期為 ${expected.point}，但實際為 ${matched.point}`).to.eql(expected.point);
          });
        });
      }

      // 3. 獨立顯示：核對全退光點數歸零
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
