// postman，測試站
// ========== 1. API 基礎設定 ==========
const baseUrl = "http://greattree-api.mohist-internal.net";

const loginUrl = `${baseUrl}/GetLoginToken.php`;
const stampUrl = `${baseUrl}/StampPointMapping.php`;

// 前置作業：GetLoginToken 登入資訊
const loginPayload = {
  account_id: "mohist",
  password: "24470678test"
};

const accountId = "8888"; // 測試站帳號
const dynamicOrderNo = "ORD_" + Date.now();                // 情境 3~7 共用單號
const multiPriceOrderNo = "ORD_MULTI_PRICE_" + Date.now(); // 情境 8 系列共用單號
const zeroCapOrderNo = "ORD_ZERO_CAP_" + Date.now();       // 情境 9 系列共用單號

// 3 個商品清單 (price 必填，以原 product 表單價帶入)
const myProducts = [
  { sku_id: "SKUA0128", quantity: 3, price: 100 }, // 100元 X3 = 300元  (product_id: 28)
  { sku_id: "717171",   quantity: 6, price: 300 }, // 300元 X6 = 1800元 (product_id: 44)
  { sku_id: "000002",   quantity: 3, price: 200 }  // 200元 X3 = 600元  (product_id: 56)
];

// 測試站目前有效活動：
// 84 = 指定商品/全館 (滿件型 QUANTITY: 每 1 件送 1 點)
// 89 = 全館滿額 (滿額型 AMOUNT: 每滿 100 元贈 1 點)

// ========== 2. 定義測試案例 ==========
const testCases = [
  // ----- 1. 建立訂單前的商品存在性與格式驗證 (ADD) -----
  {
    name: "情境 1: 整筆訂單商品皆不存在 ADD (預期 return_code: 0019)",
    payload: {
      account_id: accountId,
      order_no: "NO_ORD_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 1, price: 100 }
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
        { sku_id: "SKUA0128", quantity: 3, price: 100 },           // 3 件 / 300 元
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 10, price: 100 } // 應被忽略
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 3 }, // 3 件得 3 點
      { event_id: 89, point: 3 }  // floor(300/100)=3 x 1 = 3 點
    ]
  },
  {
    name: "情境 1-2: 缺少商品單價 price 防呆 (預期 return_code: 0023)",
    payload: {
      account_id: accountId,
      order_no: "ORD_NO_PRICE_" + Date.now(),
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 1 } // 未提供 price
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
        { sku_id: "SKUA0128", quantity: 2.5, price: 100 }
      ]
    },
    expectedReturnCode: "0004"
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
      products: myProducts // 共 12 件 / 2700 元
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 12 }, // 12 件得 12 點
      { event_id: 89, point: 27 }  // floor(2700/100)=27 x 1 = 27 點
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
        { sku_id: "NOT_EXIST_SKU_8888", quantity: 1, price: 100 }, // 不在資料表
        { sku_id: "000001", quantity: 2, price: 100 }              // 在資料表但不在原訂單
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
        { sku_id: "SKUA0128", quantity: 1, price: 100 },           // 原 3 件退 1 件，剩 2 件
        { sku_id: "NOT_EXIST_SKU_9999", quantity: 10, price: 100 } // 不存在商品：應被自動忽略
      ]
    },
    // 剩 11 件 / 2600 元
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 11 }, // 11 件
      { event_id: 89, point: 26 }  // floor(2600/100)=26 x 1 = 26 點
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
        { sku_id: "717171", quantity: 6, price: 300 } // 退掉 717171 全部 6 件 (1800元)
      ]
    },
    // 剩 SKUA0128: 2件(200元) + 000002: 3件(600元) = 5 件 / 800 元
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 5 }, // 5 件
      { event_id: 89, point: 8 }  // floor(800/100)=8 x 1 = 8 點
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
        { sku_id: "SKUA0128", quantity: 1, price: 100 }, // 原剩 2 件，退 1 件，剩 1 件
        { sku_id: "000001", quantity: 5, price: 100 }    // 原訂單沒買過，應被自動忽略
      ]
    },
    // 剩 SKUA0128: 1件(100元) + 000002: 3件(600元) = 4 件 / 700 元
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 4 }, // 4 件
      { event_id: 89, point: 7 }  // floor(700/100)=7 x 1 = 7 點
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
        { sku_id: "SKUA0128", quantity: 10, price: 100 } // 原剩 1 件，退 10 件超過現有數量
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
        { sku_id: "SKUA0128", quantity: 1, price: 100 }, // 原剩 1 件
        { sku_id: "SKUA0128", quantity: 1, price: 100 }  // 合併為 2 件，超過剩餘 1 件
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
        { sku_id: "SKUA0128", quantity: 1, price: 100 }, // 原剩 1 件，退光
        { sku_id: "000002",   quantity: 3, price: 200 }  // 原剩 3 件，退光
      ]
    },
    expectedReturnCode: "0006",
    expectedEventListEmpty: true
  },
  {
    name: "情境 7-1: 訂單已退完整筆訂單後，再次執行 DEDUCT (數量已全數歸零，不可再退，預期 return_code: 0011)",
    payload: {
      account_id: accountId,
      order_no: dynamicOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 1, price: 100 }
      ]
    },
    expectedReturnCode: "0011"
  },

  // ----- 8. 多單價專項測試 (同商品同單價合併、同商品不同單價分筆、依單價扣回金額) -----
  {
    name: "情境 8: ADD 傳入同商品同單價(合併為1筆) + 同商品不同單價(各寫1筆) (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: multiPriceOrderNo,
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 2, price: 100 },
        { sku_id: "SKUA0128", quantity: 3, price: 100 }, // 同商品同單價 -> 合併為 5 件 (500元)
        { sku_id: "SKUA0128", quantity: 1, price: 120 }  // 同商品不同單價 -> 獨立寫入 1 件 (120元)
      ]
    },
    // 合計 6 件 / 620 元
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 6 }, // 6 件
      { event_id: 89, point: 6 }  // floor(620/100)=6 x 1 = 6 點
    ]
  },
  {
    name: "情境 8-1: DEDUCT 依退貨單價扣回金額，退 2 件 100 元 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: multiPriceOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 2, price: 100 }
      ]
    },
    // 剩 3件x100 + 1件x120 = 4 件 / 420 元
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 4 }, // 4 件
      { event_id: 89, point: 4 }  // floor(420/100)=4 x 1 = 4 點
    ]
  },
  {
    name: "情境 8-2: DEDUCT 同時退回兩種單價的剩餘商品，全數歸零 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: multiPriceOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 3, price: 100 },
        { sku_id: "SKUA0128", quantity: 1, price: 120 }
      ]
    },
    expectedReturnCode: "0006",
    expectedEventListEmpty: true
  },
  {
    name: "情境 8-3: 內層陣列格式異常傳入非物件防呆 (預期 return_code: 0024)",
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
  },

  // ----- 9. 高單價退貨：滿額點數歸零防呆與歸零後持續退貨測試 -----
  {
    name: "情境 9: ADD 建立測試訂單 SKUA0128 3件 100元 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: zeroCapOrderNo,
      transaction_type: "ADD",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 3, price: 100 } // 合計 3 件 / 300 元
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 3 }, // 3 件得 3 點
      { event_id: 89, point: 3 }  // floor(300/100)=3 x 1 = 3 點
    ]
  },
  {
    name: "情境 9-1: DEDUCT 帶入高於原購買單價 (500元退1件)，滿額點數扣至歸零為止不可為負 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: zeroCapOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 1, price: 500 } // 剩 2 件，金額為 300 - 500 = -200 元
      ]
    },
    expectedReturnCode: "0006",
    expectedEvents: [
      { event_id: 84, point: 2 } // 滿件活動看剩餘 2 件得 2 點；滿額活動 89 因金額小於等於 0 扣至歸零，不為負數 (不顯示)
    ]
  },
  {
    name: "情境 9-2: DEDUCT 滿額已歸 0 後，繼續退回剩餘 2 件 100 元，可正常退貨且點數維持歸零 (預期成功 0006)",
    payload: {
      account_id: accountId,
      order_no: zeroCapOrderNo,
      transaction_type: "DEDUCT",
      refer: "門市",
      products: [
        { sku_id: "SKUA0128", quantity: 2, price: 100 } // 退光剩下 2 件
      ]
    },
    expectedReturnCode: "0006",
    expectedEventListEmpty: true // 全數退光，所有活動點數皆為 0
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
  // 4.1 前置作業：呼叫 GetLoginToken 取得 Bearer Token，取得失敗則中止後續測試
  let token = "";
  let loginData = {};
  try {
    loginData = await sendPostmanRequest(loginUrl, loginPayload);
    token = (loginData && loginData.token) ? loginData.token : "";
  } catch (loginErr) {
    loginData = { error: loginErr.message };
  }

  pm.test("前置作業: GetLoginToken 取得 Token", function () {
    pm.expect(token, `取得 Token 失敗，回應: ${JSON.stringify(loginData)}`).to.be.a('string').and.not.empty;
  });
  pm.test.skip(` [Login RS]: ${JSON.stringify(loginData)}`);

  if (!token) {
    return; // 沒有 Token，後續情境必定驗證失敗，直接中止
  }

  try {
    pm.environment.set("greattree_token", token); // 存到環境變數，方便手動測試時使用 {{greattree_token}}
  } catch (e) { /* 未選擇 Environment 時忽略 */ }

  // 4.2 依序執行各情境測試
  for (const tc of testCases) {
    let data = {};
    try {
      data = await sendPostmanRequest(stampUrl, tc.payload, token);

      // 1. 驗證 return_code
      pm.test(tc.name, function () {
        pm.expect(data.return_code).to.eql(tc.expectedReturnCode);
      });

      // 2. 核對 event_list 活動與點數 (活動數量需完全一致，不可多出預期以外的活動)
      if (tc.expectedEvents && data.return_code === '0006') {
        const pointSummary = tc.expectedEvents.map(e => `[活動${e.event_id}: ${e.point}點]`).join(' ');

        pm.test(`  -- 核對點數: ${pointSummary}`, function () {
          pm.expect(data.event_list).to.be.an('array');
          pm.expect(data.event_list.length, `event_list 活動數量應為 ${tc.expectedEvents.length}`).to.eql(tc.expectedEvents.length);

          tc.expectedEvents.forEach(expected => {
            const matched = (data.event_list || []).find(e => e.event_id === expected.event_id);
            pm.expect(matched, `回傳的 event_list 應該包含活動 ID: ${expected.event_id}`).to.not.be.undefined;
            pm.expect(matched.point, `活動 ID ${expected.event_id} 點數不符！預期為 ${expected.point}，但實際為 ${matched.point}`).to.eql(expected.point);
          });
        });
      }

      // 3. 核對全退光點數歸零
      if (tc.expectedEventListEmpty && data.return_code === '0006') {
        pm.test("  -- 核對點數: [全數歸零，event_list 為空]", function () {
          pm.expect(data.event_list).to.be.an('array');
          pm.expect(data.event_list.length, "全數退貨後 event_list 長度應為 0").to.eql(0);
        });
      }

    } catch (err) {
      pm.test(tc.name, function () {
        pm.expect.fail(`請求異常: ${err.message}`);
      });
    } finally {
      pm.test.skip(` [RQ]: ${JSON.stringify(tc.payload)}`);
      pm.test.skip(` [RS]: ${JSON.stringify(data)}`);
    }
  }
})();
