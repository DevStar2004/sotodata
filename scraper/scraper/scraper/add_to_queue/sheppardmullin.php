<?php

include '../config.php';
include '../simple_html_dom.php';

$base_url = 'https://www.sheppardmullin.com';
$spider_name = 'sheppardmullin';

$q = $pdo->prepare('DELETE FROM `queue` WHERE `spider_name`=?');
$q->execute(array($spider_name));


$values = array();

$data = fetch($base_url.'/people-directory#form-search-results');
$html = str_get_html($data);

foreach($html->find('.bioItem') as $item)
{
    if($item->find('.office a', 0))
    {
        $values[] = array(
            'url' => $base_url.'/'.$item->find('a', 0)->href,
            'name' => $item->find('.title a', 0)->plaintext,
            'position' => $item->find('.bioListPosition', 0)->plaintext,
            'phone' => $item->find('.phone a', 0)->plaintext,
            'email' => str_replace('mailto:', '', html_entity_decode($item->find('.email a', 0)->href)),
            'location' => $item->find('.office a', 0)->plaintext,
        );
    }
}

foreach ($values as $row) {

    $q = $pdo->prepare('INSERT INTO `queue` VALUES (?, ?, ?, ?, ?, ?)');
    $q->execute(array(
        $spider_name,
        $row['url'],
        json_encode($row),
        'pending',
        time(),
        NULL
    ));

}

echo count($values);

?><br/>