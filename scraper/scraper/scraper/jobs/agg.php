<?php
include '../config.php';
include '../simple_html_dom.php';
include '../../vCard.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$spider_name = 'agg';
$firm_name = 'Arnall Golden Gregory LLP';
$base_url = 'https://www.agg.com';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    $pData = json_decode($row['data'], 1);

    $data = fetch_quick($row['url']);
    $data = get_string_between($data, '</head>', '</body>').'</body>';
    $html = str_get_html($data);

    

    $values = array();

    if($html->find('.BioHero__social-icon--vcard', 0)->href)
    {
        $vCard = $base_url.$html->find('.BioHero__social-icon--vcard', 0)->href;
        $vCard_link = $vCard;

        $LinkedIn = @$html->find('.BioHero__social-icon--linkedin', 0)->href;
        if(empty($LinkedIn)) { $LinkedIn = ''; }

        file_put_contents($spider_name.'_temp.vcf', file_get_contents($vCard_link));
        $vCard = new vCard($spider_name.'_temp.vcf', false, array('Collapse' => true));

        if(!empty($vCard->adr['StreetAddress'])) { $fullAddress = $vCard->adr['StreetAddress']; }

        if(!empty($vCard->adr['Locality'])) {
            $fullAddress .= ', '.$vCard->adr['Locality'];
            $primaryAddress = $vCard->adr['Locality'];
        }

        if(!empty($vCard->adr['Region'])) {
            $fullAddress .= ', '.$vCard->adr['Region'];
            $primaryAddress .= ', '.$vCard->adr['Region'];
        }

        if(!empty($vCard->adr['PostalCode'])) { $fullAddress .= ', '.$vCard->adr['PostalCode']; }

        if(!empty($vCard->adr['Country'])) { $fullAddress .= ', '.$vCard->adr['Country']; }

        if(empty($pData['photo']))
        {
            $pData['photo'] = '';
        }

        $education = array();
        if($html->find('.ProfessionalCredentials__section', 0))
        {
            $list = $html->find('.ProfessionalCredentials__section', 0);
            foreach($list->find('li') as $item)
            {
                $education[] = trim($item->plaintext);
            }
        }

        if(!empty($education[0]))
        {
            $education[0] = explode('    ', $education[0])[0];
        }

        $admissions = array();
        if($html->find('.ProfessionalCredentials__section', 1))
        {
            $list = $html->find('.ProfessionalCredentials__section', 1);
            foreach($list->find('li') as $item)
            {
                $admissions[] = trim($item->plaintext);
            }
        }

        $memberships = array();
        if($html->find('.ProfessionalCredentials__section', 2))
        {
            $list = $html->find('.ProfessionalCredentials__section', 2);
            foreach($list->find('li') as $item)
            {
                $memberships[] = trim($item->plaintext);
            }
        }

        $acknowledgements = array();
        if($html->find('.ProfessionalRecognition__column', 0))
        {
            $list = $html->find('.ProfessionalRecognition__column', 0);
            foreach($list->find('li') as $item)
            {
                $acknowledgements[] = trim($item->plaintext);
            }
        }

        $practice_areas = array();
        if($html->find('.ProfessionalSpecialtiesList__section', 0))
        {
            $list = $html->find('.ProfessionalSpecialtiesList__section', 0);
            foreach($list->find('a') as $item)
            {
                $practice_areas[] = trim($item->plaintext);
            }
        }

        $description = $html->find('.ReadMore__content', 0)->plaintext;

        $photo = get_string_between($html, 'class="BioHero__image" style="background-image:url(', ')');

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

        $jd_year = (int) @filter_var($admissions[0], FILTER_SANITIZE_NUMBER_INT);
        if($jd_year == 0) { $jd_year = ''; }

        $q = $pdo->prepare('DELETE FROM `people` WHERE `names`=? LIMIT 1');
        $q->execute(array(json_encode(explode(' ', $pData['title']))));
        
        $q = $pdo->prepare('INSERT INTO `people` VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute(array(
            json_encode(explode(' ', $pData['title'])),
            $pData['email'],
            $vCard_link,
            $fullAddress,
            $primaryAddress,
            $LinkedIn,
            json_encode(array($pData['phone'])),
            '',
            json_encode($education),
            '[]', //bar admissions
            json_encode($admissions), //court admissions
            json_encode($practice_areas),
            json_encode($acknowledgements),
            json_encode($memberships),
            json_encode(array($pData['jobTitle'])),
            '["English"]',
            $row['url'],
            $description,
            time(),
            $pData['photo'],
            $photo,
            $spider_name,
            $firm_name,
            $law_school,
            $jd_year,
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