<?php

include '../config.php';
include '../simple_html_dom.php';

$base_url = 'https://www.lathropgpm.com';
$spider_name = 'lathropgpm';

$q = $pdo->prepare('DELETE FROM `queue` WHERE `spider_name`=?');
$q->execute(array($spider_name));


$values = array();

$data = fetch($base_url.'/professionals-directory.html');
$html = str_get_html($data);

foreach($html->find('div.bioItem') as $item)
{
	
	$values[] = array(
		'name' => $item->find('.name a', 0)->plaintext,
		'url' => $base_url.'/'.$item->find('.name a', 0)->href,
		'position' => $item->find('.title', 0)->plaintext,
		'location' => $item->find('.offices a', 0)->plaintext,
		'phone' => trim(str_replace('T:', '', $item->find('.phone', 0)->plaintext)),
		'email' => str_replace('mailto:', '', html_entity_decode($item->find('.email a', 0)->href)),
		'vCard' => $base_url.'/'.$item->find('.vcard a', 0)->href,
	);

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