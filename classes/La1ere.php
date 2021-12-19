<?php
require_once 'Provider.php';

class La1ere extends Provider {
    private static $channelsList;

    public static function getPriority() {
        return 0.993;
    }

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_la1ere.json'), true) ?? [];
    }

    function constructEPG($channel, $date, $xmlSave) {

        if($date != date('Y-m-d'))  return false;
        if(!isset(self::$channelsList[$channel])) return false;

        $old_zone = date_default_timezone_get();

        $channel_id = self::$channelsList[$channel]['id'];
        date_default_timezone_set(self::$channelsList[$channel]["timezone"]);
        
        $dom = new DomDocument();
        @$dom->loadHTMLFile('https://la1ere.francetvinfo.fr/' . $channel_id . '/emissions');

        $xpath = new DOMXpath($dom);
        $guides = $xpath->query("//div[contains(@class, 'guide')]/div[contains(@class, 'programs')]/div[contains(@class, 'programs-list')]");

        if(empty($guides)) return false;

        $xmlPrograms = [];
        $programs = [];
        $lastTime = 0;

        foreach($guides as $key => $guide) {
            $days = $xpath->query(".//ul/li", $guide);

            foreach ($days as $day) {
                $startTime = $xpath->query(".//span[contains(@class, 'program-hour')]", $day)->item(0)->nodeValue;
                $title = $xpath->query(".//span[contains(@class, 'program-name')]", $day)->item(0)->nodeValue;
                $subtitle = @$xpath->query(".//div[contains(@class, 'subtitle')]", $day)->item(0)->nodeValue;

                $startTime = strtotime(date('Ymd', strtotime("now") + 86400 * $key) . ' ' . str_replace('H', ':', $startTime));
                
                $programs[$startTime] = [
                    'startTime'     => $startTime,
                    'channel'       => $channel,
                    'title'         => $title,
                    'subTitle'      => $subtitle
                ];

                if($lastTime > 0) {
                    $programs[$lastTime]['endTime'] = $startTime;
                    $xmlPrograms[] = self::generateXmltvProgram($programs[$lastTime]);
                }
                
                $lastTime = $startTime;
            }
        }
        
        date_default_timezone_set($old_zone);
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}