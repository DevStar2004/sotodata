<?php
include '../config.php';
include '../simple_html_dom.php';
include '../../vCard.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$base_url = 'https://www.choate.com';
$spider_name = 'choate';
$firm_name = 'Choate Hall &amp; Stewart LLP';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    $data = fetch_quick($row['url']);
    $html = str_get_html($data);

    $pData = json_decode($row['data'], 1);

    $values = array();

    foreach($html->find('a') as $link)
    {
        if(strpos(strtolower($link), 'vcard') !== false)
        {
            $values['vCard'] = $base_url.$link->href;
        }
    }

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

    foreach($html->find('a') as $item)
    {
        if(strpos($item->href, '@') !== false)
        {
            $values['email'] = str_replace('mailto:', '', $item->href);
            break;
        }
    }

    foreach($html->find('a') as $item)
    {
        if(strpos($item->href, 'tel') !== false)
        {
            $values['phone_numbers'] = json_encode(array(str_replace('tel:', '', $item->href)));
            break;
        }
    }

    $education = array();
    foreach($html->find('.js-accordion.accordion') as $item)
    {
        if(strpos($item->innertext, 'Education') !== false)
        {
            foreach($item->find('.content-description-list') as $item)
            {
                $education[] = trim(preg_replace('/\s+/', ' ', $item->plaintext));
            }
        }
    }

    $bar_admissions = array();
    $court_admissions = array();

    foreach($html->find('#admissions') as $item)
    {
        if(strpos($item->innertext, 'Admissions') !== false)
        {
            foreach($item->find('.accordion__content p') as $item)
            {
                if(strpos(strtolower($item->plaintext), 'court'))
                {
                    $court_admissions[] = trim($item->plaintext);
                }
                else
                {
                    $bar_admissions[] = trim($item->plaintext);
                }
            }
        }
    }

    $practice_areas = array();
    foreach($html->find('.js-accordion.accordion') as $item)
    {
        if(strpos($item->innertext, 'Area') !== false)
        {
            foreach($item->find('a') as $item)
            {
                $practice_areas[] = trim(preg_replace('/\s+/', ' ', $item->plaintext));
            }
        }
    }

    $positions = json_encode(array($pData['position']));

    if($html->find('#bio_content p', 0))
    {
        $values['description'] = $html->find('#bio_content p', 0)->plaintext;
    }
    else
    {
        $values['description'] = trim($html->find('.rte', 0)->plaintext);
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

    $f = @file_get_contents($values['vCard']);
    file_put_contents($spider_name.'_temp.vcf', $f);

    try {

        $vCard = new vCard($spider_name.'_temp.vcf', false, array('Collapse' => true));

        if(!empty($vCard->adr['StreetAddress'])) { $fullAddress = $vCard->adr['StreetAddress']; }
        if(!empty($vCard->adr['Locality'])) { $fullAddress .= ', '.$vCard->adr['Locality']; }
        if(!empty($vCard->adr['Region'])) { $fullAddress .= ', '.$vCard->adr['Region']; }
        if(!empty($vCard->adr['PostalCode'])) { $fullAddress .= ', '.$vCard->adr['PostalCode']; }
        if(!empty($vCard->adr['Country'])) { $fullAddress .= ', '.$vCard->adr['Country']; }
        $primaryAddress = @$vCard->adr['Locality'];

    } catch (Exception $e) {
        
        $primaryAddress = '';

    }

    if(empty($values['email']))
    {
        $values['email'] = '';
    }

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