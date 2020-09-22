<?php

$title = 'Local Lockdown Lookup';
require 'site.inc';
require 'utils.php';

load_areas();
load_special();
$postcodes = explode("\n", file_get_contents('bt-postcodes.txt'));;

$results = [];
$cls = [];

$pc = array_key_exists('pc', $_GET) ? $_GET['pc'] : '';
if ($pc) {
    $pc = canonicalise_postcode($pc);
    $pc2 = substr($pc, 0, 2);
    $pc3 = substr($pc, 0, 3);
    if (array_key_exists($pc, $special_postcodes)) {
        special_result($special_postcodes[$pc]);
    } elseif (array_key_exists($pc2, $special_areas)) {
        special_result($special_areas[$pc2]);
    } elseif ($pc3 == 'RE1') {
        $cls[] = 'ok';
        $results[] = 'The crew of the mining ship Red Dwarf should worry more about holo-viruses and Epideme.';
    } elseif (!validate_postcode($pc)) {
        if (validate_partial_postcode($pc)) {
            $results[] = 'A partial postcode is not enough to provide an accurate result, I&rsquo;m afraid.';
        } else {
            $results[] = 'We did not recognise that postcode, sorry.';
        }
        $cls[] = 'error';
    } elseif (date('Y-m-d H:i') < '2020-09-22 18:00' && (preg_match('#^BT(28|29|43|60)#', $pc) || in_array($pc, $postcodes))) {
        $link = 'https://www.nidirect.gov.uk/articles/coronavirus-covid-19-regulations-and-localised-restrictions';
        $results[] = "The area has local restrictions.<br><small>Source and more info: " . link_wbr($link) . ".</small>";
        $cls[] = 'warn';
    } elseif (date('Y-m-d H:i') < '2020-09-22 18:00' && preg_match('#^BT#', $pc)) {
        $link = 'https://www.nidirect.gov.uk/articles/coronavirus-covid-19-regulations-and-localised-restrictions';
        $results[] = "Northern Ireland will have further restrictions from 6pm today.<br><small>Source and more info: " . link_wbr($link) . ".</small>";
        $cls[] = 'info';
    } else {
        $data = mapit_call('postcode/' . urlencode($pc));
        $council = $data['shortcuts']['council'];
        $ward = $data['shortcuts']['ward'];
        # If two-tier, we want the district, not the county.
        if (!is_int($council)) { $council = $council['district']; }
        if (!is_int($ward)) { $ward = $ward['district']; }
        check_area($data['areas'], $council, $ward);
    }
}

output();
footer();

function matching_area($data, $id) {
    global $areas, $cls;

    $area = $areas[$id];
    $result = $data[$id]['name'];
    if ($area['future'] && time() < $area['future']) {
        $date = date('jS F', $area['future']);
        $hour = date('H:i', $area['future']);
        if ($hour != '00:00') {
            $date = "$hour on $date";
        }
        $result .= " will have local restrictions from <strong>$date</strong>";
        $cls[] = 'info';
    } else {
        $result .= " has local restrictions";
        $cls[] = 'warn';
    }
    $result .= ".<br><small>Source and more info: " . link_wbr($area['link']) . ".</small>";
    if (array_key_exists('extra', $area)) {
        $result .= ' <small>' . $area['extra'] . '</small>';
    }
    return $result;
}

function special_result($r) {
    global $results, $cls;
    $result = $r[2];
    if ($r[1]) {
        $link = $r[1];
        $result .= "<br><small>See the current guidance: " . link_wbr($link) . ".</small>";
    }
    $cls[] = $r[0];
    $results[] = $result;
}

function check_area($data, $council, $ward=null) {
    global $results, $cls, $areas, $pc;

    if (!$data) {
        $result = 'That postcode did not return a result, sorry.';
        $cls[] = 'error';
    } elseif (array_key_exists($ward, $areas)) {
        $result = matching_area($data, $ward);
    } elseif (array_key_exists($council, $areas)) {
        $result = matching_area($data, $council);
    } else {
        $result = preg_match('#^BT#', $pc) ? "That area" : $data[$council]['name'];
        $result .= ' does not currently have additional local restrictions.';
        $link = national_guidance($data[$council]['country']);
        $result .= "<br><small>See the current national guidance: " . link_wbr($link) . ".</small>";
        $cls[] = 'ok';
    }
    $results[] = $result;
}

function national_guidance($country) {
    $guidance = [
        'E' => 'https://www.gov.uk/government/publications/coronavirus-outbreak-faqs-what-you-can-and-cant-do/coronavirus-outbreak-faqs-what-you-can-and-cant-do',
        'W' => 'https://gov.wales/coronavirus',
        'S' => 'https://www.gov.scot/publications/coronavirus-covid-19-what-you-can-and-cannot-do/',
        'N' => 'https://www.nidirect.gov.uk/articles/coronavirus-covid-19-regulations-guidance-what-restrictions-mean-you',
    ];
    return $guidance[$country];
}
