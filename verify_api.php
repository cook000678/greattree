<?php
// 模擬 API 呼叫測試
require_once 'autoload.php';
require_once 'StampPointMapping.php';
// require_once 'ModifyMemberLevel.php';
require_once 'AddPoint.php';

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

// class TestModifyMemberLevel extends ModifyMemberLevel
// {
//     public function testRun($mockInput)
//     {
//         $this->input = $mockInput;
//         try {
//             $this->execute();
//         }
//         catch (\Throwable $th) {
//             $this->setResponse(sprintf("%04d", $th->getCode()), $th->getMessage());
//         }
//         return $this->response;
//     }
// }

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

$mockInput = [
    'card_id' => 'ABCDEFG',
    'order_no' => 'ORDER123',
    'products' => [
        ['product_id' => 1, 'quantity' => 12],
        ['product_id' => 2, 'quantity' => 5],
        ['product_id' => 3, 'quantity' => 3]
    ]
];

/**
 * 測試案例：
 * 商品1 (價格 10) 購買 12個 = 120元
 * 商品2 (價格 20) 購買 5個 = 100元
 * 商品3 (價格 30) 購買 3個 = 90元
 * 總額 310元
 * 
 * 資料庫中的活動：
 * 1. 全館活動 (ID=1)：門檻 100 贈 2 點。
 *    計算：310 / 100 = 3 (倍), 3 * 2 = 6 點。
 * 2. ABC活動 (ID=2)：指定商品 1, 2, 3。門檻 200 贈 1 點。
 *    計算：符合金額 310。 310 / 200 = 1 (倍), 1 * 1 = 1 點。
 * 3. 加碼全館活(ID=3)：門檻 50 贈 5 點。
 *    計算：符合金額 310。 310 / 50 = 6 (倍), 6 * 5 = 30 點。
 */


$api = new TestStampPointMapping();
$res = $api->testRun($mockInput);

$memberData = [
    'account_id' => '1234256',
    'level' => 2
];

// $apiModifyLevel = new TestModifyMemberLevel();
// $res = $apiModifyLevel->testRun($memberData);

$addPointData = [
    "card_id" => "2ABC1D123EF11G",
    "point_type" => "BONUS",
    "point" => 10
];
$apiAddPoint = new TestAddPoint();
$res = $apiAddPoint->testRun($addPointData);

echo "--- 測試結果 (Mock Response) ---\n<br>";
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
?>
