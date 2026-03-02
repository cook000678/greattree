<?php
// 模擬 API 呼叫測試
require_once 'autoload.php';
require_once 'StampPointMapping.php';
require_once 'MemberCardHistory.php';
// require_once 'ModifyMemberLevel.php';

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

/* class TestModifyMemberLevel extends ModifyMemberLevel {
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
 } } */

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

$mockInput = [
    'card_id' => 'ABCDEFG',
    'order_no' => 'ORDER123',
    'products' => [
        ['product_id' => 1, 'quantity' => 12],
        ['product_id' => 2, 'quantity' => 5],
        ['product_id' => 3, 'quantity' => 3]
    ]
];

$api = new TestStampPointMapping();
// $res = $api->testRun($mockInput);

$memberData = [
    'account_id' => '123422156',
    'level' => 3
];

// $apiModifyLevel = new TestModifyMemberLevel();
// $res = $apiModifyLevel->testRun($memberData);

$historyData = [
    'account_id' => '123422156',
    'card_id' => 'ABCDEFG',
    'point_type' => 'BONUS'
];

$apiHistory = new TestMemberCardHistory();
$res = $apiHistory->testRun($historyData);

echo "--- 測試結果 (Mock Response) ---\n<br>";
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
?>
