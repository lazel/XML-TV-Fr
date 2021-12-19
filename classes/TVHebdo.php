<?php
require_once 'Provider.php';

class TVHebdo extends Provider {
    private static $channelsList;

    public static function getPriority() {
        return 0.15;
    }

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents("channels_per_provider/channels_tvhebdo.json"), true) ?? [];
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        $sportChannel = array("rds/RDS", "rds2/RDS2", "ris/RDSI", "tvas/TVASP", "tvs2/TVS2");

        $old_zone = date_default_timezone_get();
        date_default_timezone_set('America/Montreal');

        $dom = new DomDocument();
        @$dom->loadHTMLFile('http://www.ekamali.com/index.php?q=' . base64_encode('http://www.tvhebdo.com/horaire-tele/' . self::$channelsList[$channel] . '/date/' . $date) . '&hl=3ed');

        $xpath = new DOMXpath($dom);
        $rows = $xpath->query("//table[contains(@class, 'liste_programmation')]/tr[contains(@class,'liste_row')]");

        if(empty($rows)) return false;

        $xmlPrograms = [];
        $programs = [];
        $lastTime = 0;

        foreach($rows as $row) {
            $time = $xpath->query(".//td[contains(@class, 'heure')]", $row)->item(0)->nodeValue;
            $title = $xpath->query(".//td[contains(@class, 'titre')]/a", $row)->item(0)->nodeValue;

            $startTime =  strtotime($date . ' ' . $time);
            
            if($startTime < $lastTime) $startTime += 86400;

            $programs[$startTime] = [
                'startTime' => $startTime,
                'channel'   => $channel,
                'title'     => $title,
                'genre'     => in_array($channelId, $sportChannel) ? 'Sport' : null
            ];

            if($lastTime > 0) {
                $programs[$lastTime]['endTime'] = $startTime;
                $xmlPrograms[] = self::generateXmltvProgram($programs[$lastTime]);
            }
            
            $lastTime = $startTime;
        }
        
        date_default_timezone_set($old_zone);

        return file_put_contents($xmlSave, $xmlPrograms);
    }
}