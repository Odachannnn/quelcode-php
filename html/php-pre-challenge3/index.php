<?php
$limit = $_GET['target']; //ここで得た値は文字列なので、正規表現でチェックした後int型に変換する。
if (preg_match('/\.(\d+)?$/', $limit) || !preg_match('/^([1-9][0-9]*)$/', $limit)) { //小数か、先頭に0が入ってないか、半角数字以外が入ってないか,負の数ではないか。この場合はDB接続の必要ないのでPDOより上
    http_response_code(400);
    exit();
} else {
    $limit = (int) $_GET['target'];
}
$dsn = 'mysql:dbname=test;host=mysql;charset=utf8';
$dbuser = 'test';
$dbpassword = 'test';
try {
    $db = new PDO($dsn, $dbuser, $dbpassword);
} catch (PDOException $e) {
    http_response_code(500);
    exit();
}

$numbers = $db->query('SELECT value FROM prechallenge3'); //DBに保存されている数字を取り出す（下の行も）
while ($number = $numbers->fetchColumn()) { //fetchColumnで一次元配列で取り出す(=fetch(PDO::FETCH_COLUMN))。文字列として出てくるから、この後変換する必要がある。
    $values[] = (int) $number; //values=[1,17,3,13,11,7,19,5]
} //ループ毎に型変換するより一括で変換した方が処理の回数減っていいのだろうけれど、配列の値を一括で型変換するやり方が見つけられず。。

//組み合わせを作る関数makeCombinationを定義する
function makeCombination($array, $pick)
{
    $numOfElem = count($array);
    if ($numOfElem < $pick) {
        return;
    }
    if ($pick === 1) {
        for ($i = 0; $i < $numOfElem; $i++) {
            $combination[] = [$array[$i]]; //組み合わせをいれる配列$combination, 二次元配列
        }
    } elseif ($pick > 1) {
        for ($i = 0; $i < $numOfElem - $pick + 1; $i++) {
            $ts = makeCombination(array_slice($array, $i + 1), $pick - 1);
            foreach ($ts as $t) {
                array_unshift($t, $array[$i]); //この返り値は$tに入る
                $combination[] = $t; //ここも二次元配列、組み合わせ。
            }
        }
    }
    return $combination;
}
//定義終わり

//データベースにある整数の組み合わせを作る
$length = count($values);
for ($i = 1; $i < $length + 1; $i++) {
    echo "<pre>";
    $comb[] = makeCombination($values, $i); //変数$combに組み合わせ結果全てを代入した(二次元配列での作成)
    echo "<pre>";
}
//組み合わせ結果毎に値を合計し、それを配列とする=>配列とせずにいけた
foreach ($comb as $key => $arr) { //$key:外側の配列の添字、$arr:内側の配列
    $secLength = count($arr); //各内側配列の要素の数
    for ($j = 0; $j < $secLength; $j++) { //$j: 内側配列$arrの添字
        $sum = 0;
        foreach ($arr[$j] as $value) { //$value: 内側配列の各値
            $sum += $value;
        }
        //$sumが合計値なら、ここで$limitと比較して出力できるかな？ =>できた
        if ($limit === $sum) {
            $json[] = $comb[$key][$j]; //$comb[$key][$j]で該当の組み合わせ
        }
    }
}
if (empty($json)) { //該当した組み合わせがなかった場合
    $json = [];
}
echo json_encode($json);
