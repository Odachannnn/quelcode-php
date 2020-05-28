<?php
$array = explode(',', $_GET['array']);

// 修正はここから
for ($i = 0; $i < count($array); $i++) {
    for($j = 0; $j < count($array)-1; $j++) {
        if ($array[$j] > $array[$j+1]) {
            $temp = $array[$j];
            $array[$j] = $array[$j+1];
            $array[$j+1] = $temp;
        }
    }
}
// 修正はここまで
echo "<pre>";
print_r($array);
echo "</pre>";
