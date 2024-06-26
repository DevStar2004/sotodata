<?php
include '../config.php';
include '../simple_html_dom.php';
include '../../vCard.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$spider_name = 'kslaw';
$firm_name = 'King & Spalding';
$base_url = 'https://www.kslaw.com';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    $data = fetch_quick($row['url']);
    $html = str_get_html($data);

    

    $pData = json_decode($row['data'], 1);

    foreach($html->find('a') as $item)
    {
        if(strpos($item->href, '.vcf') !== false)
        {
            $vCard_link = $base_url.$item->href;
            break;
        }
    }

    if(!empty($vCard_link))
    {

        foreach($html->find('a') as $link)
        {
            if(strpos(strtolower($link->href), 'linkedin') !== false)
            {
                $linkedIn = $link->href;
                break;
            }
        }

        if(empty($linkedIn)) { $linkedIn = ''; }

        $values['names'] = json_encode(explode(' ', $pData[0]));
        $values['email'] = $pData[4];
        $values['vCard'] = $vCard_link;

        foreach($html->find('a') as $link)
        {
            if(strpos(strtolower($link->href), 'tel:') !== false)
            {
                $values['phone_numbers'] = json_encode(array(str_replace('tel:', '', $link->href)));
                break;
            }
        }

        $primaryAddress = $html->find('.contacts a', 0)->plaintext;

        $education = array();
        if($html->find('.cred.education', 0))
        {
            $list = $html->find('.cred.education', 0);
            foreach($list->find('p') as $item)
            {
                $education[] = trim($item->plaintext);
            }
        }

        $bar_admissions = array();
        $court_admissions = array();

        foreach($html->find('.cred.admissions p') as $item)
        {
            if(strpos(strtolower($item->plaintext), 'court') !== false)
            {
                $court_admissions[] = trim($item->plaintext);
            }
            else
            {
                $bar_admissions[] = trim($item->plaintext);
            }
        }

        $bar_admissions = array_unique($bar_admissions);
        $court_admissions = array_unique($court_admissions);

        $memberships = array();

        $languages = array();
        $languages[] = 'N.A.';

        $practice_areas = array();

        if($html->find('.tags.width_narrow', 0))
        {
            $list = $html->find('.tags.width_narrow', 0);
            foreach($list->find('.smart_tag') as $item)
            {
                $practice_areas[] = trim($item->plaintext);
            }
        }

        $positions = array();
        $positions[] = $pData[3];

        $values['description'] = trim($html->find('.bio_hed h2', 0)->plaintext);

        foreach($education as $value)
        {
            $school = strtolower(preg_replace('/[^A-Za-z0-9\-]/', ' ', $value));
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

        $q = $pdo->prepare('DELETE FROM `people` WHERE `names`=? LIMIT 1');
        $q->execute(array($values['names']));

        $q = $pdo->prepare('INSERT INTO `people` VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute(array(
            $values['names'],
            $values['email'],
            $values['vCard'],
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
            json_encode($memberships),
            json_encode($positions),
            json_encode($languages),
            $row['url'],
            $values['description'],
            time(),
            $pData[2],
            $pData[2],
            $spider_name,
            $firm_name,
            $law_school,
            str_replace('-', '', $jd_year),
            0,
            NULL
        ));
    }

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