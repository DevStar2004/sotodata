<?php
include '../config.php';
include '../simple_html_dom.php';
include '../../vCard.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$spider_name = 'allenovery';
$firm_name = 'Allen & Overy';
$base_url = 'https://www.allenovery.com';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    $data = fetch_quick($row['url']);
    $html = str_get_html($data);

    $pData = json_decode($row['data'], 1);

    $values = array();
    
    if($html->find('a.download', 0)->href)
    {

        $values['names'] = json_encode(explode(' ', $pData['fullName']));
        $values['email'] = str_replace('mailto:', '', $html->find('a.mail', 0)->href);
        $values['vCard'] = $base_url.$html->find('a.download', 0)->href;

        file_put_contents($spider_name.'_temp.vcf', file_get_contents($values['vCard']));
        $vCard = new vCard($spider_name.'_temp.vcf', false, array('Collapse' => true));

        if(isset($vCard->tel[0]['Value']))
        {
            $values['phone_numbers'] = json_encode(array(str_replace('tel:', '', $vCard->tel[0]['Value'])));
        }
        else
        {
            $values['phone_numbers'] = json_encode(array($pData[5]));
        }

        if(!empty($vCard->adr['StreetAddress'])) { $fullAddress = $vCard->adr['StreetAddress']; }

        if(!empty($vCard->adr['Locality'])) {
            $fullAddress .= ', '.$vCard->adr['Locality'];
        }

        if(!empty($vCard->adr['Region'])) {
            $fullAddress .= ', '.$vCard->adr['Region'];
        }

        if(!empty($vCard->adr['PostalCode'])) { $fullAddress .= ', '.$vCard->adr['PostalCode']; }

        if(!empty($vCard->adr['Country'])) { $fullAddress .= ', '.$vCard->adr['Country']; }

        $education = array();
        foreach($html->find('.section-right__section') as $item)
        {
            if($item->find('h4', 0)->plaintext == 'Academic')
            {
                foreach($item->find('.rte-content p') as $item)
                {
                    $education[] = $item->plaintext;
                }
            }
        }

        $bar_admissions = array();
        $court_admissions = array();

        $practice_areas = array();

        foreach($html->find('.section-right__section') as $item)
        {
            if($item->find('h4', 0)->plaintext == 'Practices')
            {
                $ex = explode('<h4 class="uppercase-heading">Practices</h4>', $item->innertext);
                $data_ = end($ex);
                $html_2 = str_get_html($data_);
                foreach($html_2->find('p') as $area)
                {
                    $practice_areas[] = trim($area->plaintext);
                }
            }
        }

        $practice_areas = array_unique($practice_areas);

        $positions = array($pData['jobTitle']);

        if(isset($pData['imageUrl']))
        {
            $image = $base_url.$pData['imageUrl'];
        }
        else
        {
            $image = '';
        }

        if($html->find('.intro-text', 0))
        {
            $values['description'] = trim($html->find('.intro-text', 0)->plaintext.' '.$html->find('.content.rte-content', 0)->plaintext);
        }
        else
        {
            $values['description'] = '';
        }

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

        foreach($html->find('.section-right__section') as $item)
        {
            if(strpos($item->innertext, '<h3>Office</h3>') !== false)
            {
                $primaryAddress = trim($item->find('h4', 0)->plaintext);
                break;
            }
        }

        $jd_year = (int) @filter_var($law_school, FILTER_SANITIZE_NUMBER_INT);

        $q = $pdo->prepare('DELETE FROM `people` WHERE `names`=? LIMIT 1');
        $q->execute(array($values['names']));

        $q = $pdo->prepare('INSERT INTO `people` VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute(array(
            $values['names'],
            $values['email'],
            $values['vCard'],
            sEncode($fullAddress),
            $primaryAddress,
            'https://www.linkedin.com/company/allen-&-overy/',
            $values['phone_numbers'],
            '',
            json_encode($education),
            json_encode($bar_admissions), //bar admissions
            json_encode($court_admissions), //court admissions
            json_encode($practice_areas),
            '[]',
            '[]',
            json_encode($positions),
            json_encode(array('N.A.')),
            $row['url'],
            sEncode($values['description']),
            time(),
            $image,
            $image,
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