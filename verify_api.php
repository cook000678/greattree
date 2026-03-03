<?php
// 模擬 API 呼叫測試
require_once 'autoload.php';
require_once 'StampPointMapping.php';
require_once 'ModifyMemberLevel.php';
require_once 'MemberCardHistory.php';
require_once 'AddPoint.php';

// --- 測試工具類別 ---
class TestStampPointMapping extends StampPointMapping
{
    public function testRun($mockInput)
    {
        $this->input = $mockInput;
        try {
            $this->execute();
        }
        catch (\Throwable $th) {
            $this->setResponse(sprintf("%04d", $th->getCode()), $th->getMessage());
        }
        return $this->response;
    }
}
class TestModifyMemberLevel extends ModifyMemberLevel
{
    public function testRun($mockInput)
    {
        $this->input = $mockInput;
        try {
            $this->execute();
        }
        catch (\Throwable $th) {
            $this->setResponse(sprintf("%04d", $th->getCode()), $th->getMessage());
        }
        return $this->response;
    }
}
class TestMemberCardHistory extends MemberCardHistory
{
    public function testRun($mockInput)
    {
        $this->input = $mockInput;
        try {
            $this->execute();
        }
        catch (\Throwable $th) {
            $this->setResponse(sprintf("%04d", $th->getCode()), $th->getMessage());
        }
        return $this->response;
    }
}
class TestAddPoint extends AddPoint
{
    public function testRun($mockInput)
    {
        $this->input = $mockInput;
        try {
            $this->execute();
        }
        catch (\Throwable $th) {
            $this->setResponse(sprintf("%04d", $th->getCode()), $th->getMessage());
        }
        return $this->response;
    }
}

// --- 執行測試 ---
$results = [];

// 1. 印花查詢
/**
 * 商品編號:1，價格:10。。購買數量12
 * 商品編號:2，價格:20。。購買數量5
 * 商品編號:11，價格:110。購買數量11
 * 總金額 10*12 + 20*5 + 110*11 = 120 + 100 + 1210 = 
 * 
 * 
 * 活動1，ALL，門檻100送2點。。1260/100 = 12*2 = 24
 * 活動2，SPECIFIC，門檻200送1點。。1260/200 = 6*1 = 6
 * 活動3，ALL，門檻50送5點。。1260/50 = 25 * 5 = 125
 */
$stampData = [
    'card_id' => 'ABCDEFG',
    'order_no' => 'ORDER123',
    'products' => [
        ['product_id' => 1, 'quantity' => 12],
        ['product_id' => 2, 'quantity' => 5],
        ['product_id' => 11, 'quantity' => 11]
    ]
];
$results['stampData'] = (new TestStampPointMapping())->testRun($stampData);

// 2. 修改等級
$memberData = [
    'account_id' => '123422156', // 123422156 ， 1234221156 ， 8888
    'level' => '1'
];
$results['memberData'] = (new TestModifyMemberLevel())->testRun($memberData);

// 3. 歷史紀錄
$historyData = [
    "account_id" => "123422156",
    "card_id" => "ABCDEFG",
    "point_type" => "BONUS" // BONUS / EVENT / STAMP
];
$results['historyData'] = (new TestMemberCardHistory())->testRun($historyData);

// 4. 新增點數
$addPointData = [
    "card_id" => "ABCDEFG",
    "point_type" => "EVENT",
    "point" => '100',
];
$results['addPointData'] = (new TestAddPoint())->testRun($addPointData);

echo "--- 綜合測試結果 ---\n<br>";
echo "<pre>";
print_r($results);
echo "</pre>";
?>
