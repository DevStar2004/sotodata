<?php
include '../config.php';
include '../simple_html_dom.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$spider_name = 'akingump';
$firm_name = 'Akin Gump Strauss Hauer &amp; Feld LLP';
$base_url = 'https://www.akingump.com';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    $data = fetch_quick($row['url']);
    $html = str_get_html($data);

    $pData = json_decode($row['data'], 1);

    $values = array();

    if(isset($pData['name']))
    {

        $values['names'] = json_encode(explode(' ', $pData['name']));
        $values['email'] = $pData['email'];

        $values['phone_numbers'] = json_encode(array($pData['offices_info'][0]['repeater_module_office']['phone']));

        $primaryAddress = $pData['content_data']['offices_info-repeater_module_office-office'][0]['name'];

        $education = array();
        foreach($html->find('.ms-4.me-4 .u-hide-in-print') as $item)
        {
            if(@$item->find('h3 button span', 0)->plaintext == 'Education')
            {
                $list = $item->find('ul', 0);
                foreach($list->find('li') as $item)
                {
                    $education[] = trim($item->plaintext);
                }
            }
        }

        $languages = array();
        foreach($html->find('.ms-4.me-4 .u-hide-in-print') as $item)
        {
            if(@$item->find('h3 button span', 0)->plaintext == 'Languages')
            {
                $list = $item->find('ul', 0);
                foreach($list->find('li') as $item)
                {
                    $languages[] = trim($item->plaintext);
                }
            }
        }

        if(count($languages)<1) { $languages[] = 'N.A.'; }

        $bar_admissions = array();
        $court_admissions = array();

        foreach($html->find('.ms-4.me-4 .u-hide-in-print') as $item)
        {
            if(@$item->find('h3 button span', 0)->plaintext == 'Bar Admissions')
            {
                $list = $item->find('ul', 0);
                foreach($list->find('li') as $item)
                {
                    $bar_admissions[] = trim($item->plaintext);
                }
            }
        }

        $practice_areas = array();
        foreach($html->find('.container') as $item)
        {
            if(@$item->find('h3', 0)->plaintext == 'Areas of Focus')
            {
                if($item->find('ul', 1))
                {
                    $list = $item->find('ul', 1);
                    foreach($list->find('li') as $item)
                    {
                        $practice_areas[] = trim($item->plaintext);
                    }
                }
            }
        }

        $positions = array();
        $positions[] = $pData['content_data']['position']['name'];

        $values['description'] = trim(str_replace('Biography ', '', $html->find('.container.pb-5.pb-print-0', 0)->plaintext));

        $photo = $base_url.$pData['attorney_card_photo_url'];
        $thumb = $base_url.$pData['attorney_card_photo_url'];

        foreach($education as $value)
        {
            $school = strtolower(preg_replace('/[^A-Za-z0-9\-]/', ' ', $value));
            if(strpos($school, 'jd') !== false || strpos($school, 'doctor') !== false)
            {
                $law_school = $value;
                break;
            }
        }

        if(empty($law_school) && isset($education[0]))
        {
            $law_school = @$education[0];
        }
        else
        {
            $law_school = '';
        }

        $jd_year = (int) @filter_var($law_school, FILTER_SANITIZE_NUMBER_INT);

        foreach($html->find('a') as $item)
        {
            if(strpos($item->innertext, 'map-marker') !== false)
            {
                $primaryAddress = trim($item->plaintext);
                break;
            }
        }

        $q = $pdo->prepare('DELETE FROM `people` WHERE `names`=? LIMIT 1');
        $q->execute(array($values['names']));

        $q = $pdo->prepare('INSERT INTO `people` VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute(array(
            $values['names'],
            $values['email'],
            '',
            @$fullAddress,
            $primaryAddress,
            'https://www.linkedin.com/company/akin-gump-strauss-hauer-&-feld-llp/',
            $values['phone_numbers'],
            '',
            json_encode($education),
            json_encode($bar_admissions), //bar admissions
            json_encode($court_admissions), //court admissions
            json_encode($practice_areas),
            '[]',
            '[]',
            json_encode($positions),
            json_encode($languages),
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

    }

    unset($values);
    unset($law_school);
    unset($jd_year);
    unset($fullAddress);
    unset($primaryAddress);
    unset($linkedIn);

}

@unlink($spider_name.'_temp.vcf');

?>