<?php

include '../config.php';
include '../simple_html_dom.php';

$base_url = 'https://www.troutman.com';
$spider_name = 'troutman';

$q = $pdo->prepare('DELETE FROM `queue` WHERE `spider_name`=?');
$q->execute(array($spider_name));


$values = array();

for ($i=0; $i <= 51; $i++) {

    $data = fetch($base_url.'/professionals/index.html?s=25&f=0&_ajax=true&v=attorney&page='.$i);

    $values = json_decode($data, 1)['viewState']['results'];

    foreach ($values as $row) {

        $q = $pdo->prepare('INSERT INTO `queue` VALUES (?, ?, ?, ?, ?, ?)');
        $q->execute(array(
            $spider_name,
            $base_url.$row['url'],
            json_encode($row),
            'pending',
            time(),
            NULL
        ));

    }
}

echo count($values);

?><br/>