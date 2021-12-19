<?php
require_once 'Provider.php';

class Afrique extends Provider {
    private static $channelsList;

    public static function getPriority() {
        return 0.2;
    }

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_afrique.json'), true) ?? [];
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        $day = (strtotime($date) - strtotime(date('Y-m-d'))) / 86400;

        $curl = curl_init('https://service.canal-overseas.com/ott-frontend/vector/83001/channel/' . $channelId . '/events?filter.day=' . $day);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $get = curl_exec($curl);
        curl_close($curl);

        $json = json_decode($get, true);

        if(!isset($json['timeSlices']) || empty($json['timeSlices'])) return false;

        $xmlPrograms = [];

        foreach($json['timeSlices'] as $section) {
            foreach($section['contents'] as $program) {
                $data = [
                    'startTime' => $program['startTime'],
                    'endTime'   => $program['endTime'],
                    'channel'   => $channel,
                    'title'     => $program['title'],
                    'subTitle'  => @$program['subtitle'],
                    'icon'      => @$program['URLImage']
                ];
                $xmlPrograms[] = self::generateXmltvProgram($data);
            }
        }
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}