<?php
include '../config.php';
include '../simple_html_dom.php';
include '../../vCard.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$base_url = 'http://www.hinckleyallen.com';
$spider_name = 'hinckleyallen';
$firm_name = 'Hinckley Allen';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    $data = file_get_contents('http://137.184.158.149:3000/?api=get&url='.urlencode($row['url']));
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

    foreach($html->find('a') as $link)
    {
        if(strpos(strtolower($link->href), 'vcard') !== false)
        {
            $values['vCard'] = $link->href;
            break;
        }
    }

    foreach($html->find('a') as $link)
    {
        if(strpos(strtolower($link->href), '@') !== false)
        {
            $values['email'] = str_replace('mailto:', '', $link->href);
            break;
        }
    }

    $arr = array();
    foreach($html->find('li a') as $item)
    {
        if(strpos($item->href, '/cdn-cgi/l/email-protection#') !== false)
        {
            $values['email'] = cfDecodeEmail(str_replace('/cdn-cgi/l/email-protection#', '', $item->href));
        }
    }

    if(empty($linkedIn)) { $linkedIn = ''; }

    $values['names'] = json_encode(explode(' ', $pData['name']));

    $values['phone_numbers'] = json_encode(array($pData['phone']));

    $fullAddress = '';

    $education = array();
    foreach($html->find('.sidebar-item') as $item)
    {
        if(strpos($item->innertext, 'Education') !== false)
        {
            foreach($item->find('p') as $item)
            {
                $education[] = trim(preg_replace('/\s+/', ' ', $item->plaintext));
            }
        }
    }

    $bar_admissions = array();
    $court_admissions = array();

    $practice_areas = array();
    foreach($html->find('.sidebar__services.sidebar-item.services_col') as $item)
    {
        if(strpos($item->innertext, 'Practices') !== false)
        {
            foreach($item->find('li a') as $item)
            {
                $practice_areas[] = trim(preg_replace('/\s+/', ' ', $item->plaintext));
            }
        }
    }

    $positions = json_encode(array($pData['position']));

    if($html->find('.o-grid.u-mb30 p', 0))
    {
        $values['description'] = $html->find('.o-grid.u-mb30 p', 0)->plaintext;
    }
    else
    {
        $values['description'] = '';
    }

    if(empty($values['email']))
    {
        $values['email'] = '';
    }

    $photo = $pData['image'];
    $thumb = $pData['image'];

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

    foreach($html->find('a') as $link)
    {
        if(strpos(strtolower($link), 'vcard') !== false)
        {
            $values['vCard'] = $link->href;
        }
    }

    $values['vCard'] = '';
    $primaryAddress = $html->find('.professional-info__locations a', 0)->plaintext;

    $q = $pdo->prepare('DELETE FROM `people` WHERE `names`=? LIMIT 1');
    $q->execute(array($values['names']));

    $q = $pdo->prepare('INSERT INTO `people` VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute(array(
        $values['names'],
        $values['email'],
        @$values['vCard'],
        @sEncode($fullAddress),
        @sEncode($primaryAddress),
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
        sEncode(trim(strip_tags($values['description']))),
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