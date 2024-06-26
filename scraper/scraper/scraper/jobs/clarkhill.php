<?php
include '../config.php';
include '../simple_html_dom.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$base_url = 'https://www.clarkhill.com';
$spider_name = 'clarkhill';
$firm_name = 'Clark Hill PLC';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    if(strpos($row['url'], 'asset-360'))
    {
        $data = fetch_quick(str_replace('https://www.clarkhill.com', '', $row['url']));
    }
    else
    {
        $data = fetch_quick($row['url']);
    }

    $html = str_get_html($data);

    $pData = json_decode($row['data'], 1);

    $values = array();

    foreach($html->find('a') as $link)
    {
        if(strpos(strtolower($link->href), 'linkedin') !== false)
        {
            $linkedIn = $link->href;
            break;
        }
    }

    if(empty($linkedIn)) { $linkedIn = ''; }

    $values['names'] = json_encode(explode(' ', $pData['name']));

    $values['email'] = $pData['email'];

    if(!empty($pData['phone']))
    {
        $values['phone_numbers'] = json_encode(array($pData['phone']));
    }
    else
    {
        $values['phone_numbers'] = '';
    }

    $primaryAddress = $pData['offices'];

    $fullAddress = '';

    $education = array();
    foreach($html->find('.Accordion__content div') as $item)
    {
        if(strpos($item->innertext, '<h4 class="Accordion__heading">Education</h4>') !== false)
        {
            foreach($item->find('.RichText__container') as $item)
            {
                $education[] = trim(preg_replace('/\s+/', ' ', $item->plaintext));
            }
        }
    }

    $bar_admissions = array();
    $court_admissions = array();

    $practice_areas = array();
    foreach($html->find('.RelatedCrossLinks__container') as $item)
    {
        if(strpos($item->innertext, '<h2 class="RelatedCrossLinks__title">Practice Areas</h2>') !== false)
        {
            foreach($item->find('a') as $item)
            {
                $practice_areas[] = trim(preg_replace('/\s+/', ' ', $item->plaintext));
            }
        }
    }

    $positions = json_encode(array($pData['position']));

    if($html->find('.SectionBlock p', 0))
    {
        $values['description'] = $html->find('.SectionBlock p', 0)->plaintext;
    }

    if(empty($pData['image']['url']))
    {
        $pData['image']['url'] = $root.'/img/vecteezy_crossed-image-icon-picture-not-available-delete-picture_5720408.jpg';
    }

    $photo = $pData['image']['url'];
    $thumb = $pData['image']['url'];

    foreach($education as $value)
    {
        $school = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', $value));
        if(strpos($school, 'jd') !== false || strpos($school, 'doctor') !== false)
        {
            $law_school = $value;
            break;
        }
    }

    if(empty($law_school))
    {
        $law_school = '';
    }

    $jd_year = (int) @filter_var($law_school, FILTER_SANITIZE_NUMBER_INT);

    if($html->find('.PeopleHero__links--offices a', 0))
    {
        $primaryAddress = $html->find('.PeopleHero__links--offices a', 0)->plaintext;
    }

    if(empty($values['description'])) { $values['description'] = ''; }

    $q = $pdo->prepare('DELETE FROM `people` WHERE `names`=? LIMIT 1');
    $q->execute(array($values['names']));

    $q = $pdo->prepare('INSERT INTO `people` VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute(array(
        $values['names'],
        $values['email'],
        '',
        $fullAddress,
        $primaryAddress,
        $linkedIn,
        $values['phone_numbers'],
        '',
        json_encode($education),
        json_encode($bar_admissions), //bar admissions
        json_encode($court_admissions), //court admissions
        json_encode($practice_areas),
        '[]',
        '[]',
        $positions,
        json_encode(array('N.A.')),
        $row['url'],
        $values['description'],
        time(),
        $thumb,
        $photo,
        $spider_name,
        $firm_name,
        $law_school,
        $jd_year,
        0,
        NULL
    ));

    $q = $pdo->prepare('UPDATE `queue` SET `status`=\'complete\' WHERE `id`=?');
    $q->execute(array($row['id']));

    unset($values);
    unset($law_school);
    unset($jd_year);
    unset($fullAddress);
    unset($primaryAddress);
    unset($linkedIn);

}

@unlink($spider_name.'_temp.vcf');

?>