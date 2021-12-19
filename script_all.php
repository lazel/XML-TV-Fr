<?php
header('Content-type: text/plain');
date_default_timezone_set('Europe/Paris');
set_time_limit(0);
ini_set('memory_limit', '-1');

function compare_classe($a, $b) {
    if(class_exists($a) && class_exists($b)) {
        if(call_user_func($a . '::getPriority') > call_user_func($b . '::getPriority')) return -1;
        return 1;
    } else return 0;
}


/*
if(file_exists('xmltv/xmltv.xml')) {
    if(date('Y-m-d', filemtime('xmltv/xmltv.xml')) == date('Y-m-d')) exit('Déja fait'."\n");
}
*/

$classesPriotity = [];
echo "\e[0;95m[CHARGEMENT] \e[39mOrganisation des classes de Provider \n";
$classes = glob('classes/*.php');
foreach($classes as $classe) {
    require_once $classe;
    $className = basename($classe, '.php');
    if(class_exists($className) && is_subclass_of($className, 'Provider')) $classesPriotity[] = $className;
}

usort($classesPriotity, 'compare_classe');

if(!file_exists('config.json')) {
    $dayLimit = 8;
} else {
    $json = json_decode(file_get_contents('config.json'), true);
    $dayLimit = $json['days'] ?? 8;
}

/*
$xmltv = glob('xmltv/xmltv*');

foreach($xmltv as $file) {
    if(time() - filemtime($file) > 86400 * 5) unlink($file);
}
*/
// if(file_exists('xmltv/xmltv.xml'))    rename('xmltv/xmltv.xml',    'xmltv/xmltv_' . date('Y-m-d H-i-s', filemtime('xmltv/xmltv.xml'))    . '.xml');
// if(file_exists('xmltv/xmltv.zip'))    rename('xmltv/xmltv.zip',    'xmltv/xmltv_' . date('Y-m-d H-i-s', filemtime('xmltv/xmltv.zip'))    . '.zip');
// if(file_exists('xmltv/xmltv.xml.gz')) rename('xmltv/xmltv.xml.gz', 'xmltv/xmltv_' . date('Y-m-d H-i-s', filemtime('xmltv/xmltv.xml.gz')) . '.xml.gz');

$xmlPath = 'channels/';
if($channels = json_decode(@file_get_contents('channels.json'), true)) {
    $xmltv = new Xmltv($classesPriotity, $channels, $dayLimit, $xmlPath, 'xmltv');
    $xmltv->generate();
    $xmltv->isValid();
    $xmltv->zipCompress();
    $xmltv->gzCompress();
}

else echo "\e[0;91m[ERROR] \e[39mchannels.json not found!\n";