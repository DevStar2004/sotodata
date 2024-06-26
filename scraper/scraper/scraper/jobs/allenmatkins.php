<?php
include '../config.php';
include '../simple_html_dom.php';
include '../../vCard.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$spider_name = 'allenmatkins';
$firm_name = 'Allen Matkins';
$base_url = 'https://www.allenmatkins.com/';

$q = $pdo->prepare('SELECT * FROM `queue` WHERE `status`=\'pending\' AND `spider_name`=? LIMIT 20');
$q->execute(array($spider_name));

foreach ($q as $row) {

    $fullAddress = '';
    $primaryAddress = '';

    $pData = json_decode($row['data'], 1);

    $data = fetch_quick($row['url']);
    $data = get_string_between($data, '</head>', '</body>').'</body>';
    $html = str_get_html($data);

    

    foreach($html->find('a') as $item)
    {
        if(strpos($item->class, 'styles__contactInfoOffice--') !== false)
        {
            $primaryAddress = trim($item->plaintext);
            break;
        }
    }

    $values = array();

    $LinkedIn = @$html->find('a.icon-linkedin', 0)->href;
    if(empty($LinkedIn)) { $LinkedIn = ''; }

    $education = array();
    if($html->find('ul', 1))
    {
        $list = $html->find('ul', 1);
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
    if($html->find('ul', 2))
    {
        $list = $html->find('ul', 2);
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
    if($html->find('div.bg-teal ul', 0))
    {
        $list = $html->find('div.bg-teal ul', 0);
        foreach($list->find('a') as $item)
        {
            $practice_areas[] = trim($item->plaintext);
        }
    }

    $description = strip_tags($pData['main_content']);

    $photo = str_replace('//', '/', $base_url.$pData['w375_url']);

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

    if(empty($pData['profile']['middle_name']))
    {
        $pData['profile']['middle_name'] = '';
    }

    $q = $pdo->prepare('DELETE FROM `people` WHERE `names`=? LIMIT 1');
    $q->execute(array(json_encode(array($pData['profile']['first_name'], $pData['profile']['middle_name'], $pData['profile']['last_name']))));

    $q = $pdo->prepare('INSERT INTO `people` VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute(array(
        json_encode(array($pData['profile']['first_name'], $pData['profile']['middle_name'], $pData['profile']['last_name'])),
        $pData['email'],
        '',
        $fullAddress,
        $primaryAddress,
        $LinkedIn,
        json_encode(array($pData['offices_info'][0]['office_contact_information']['phone'])),
        '',
        json_encode($education),
        '[]', //bar admissions
        json_encode($admissions), //court admissions
        json_encode($practice_areas),
        json_encode($acknowledgements),
        json_encode($memberships),
        json_encode(array($pData['content_data']['position']['name'])),
        json_encode(array('N.A.')),
        $row['url'],
        $description,
        time(),
        $photo,
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