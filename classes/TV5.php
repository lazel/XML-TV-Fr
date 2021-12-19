<?php
require_once 'Provider.php';

class TV5 extends Provider {
    private static $channelsList;

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_tv5.json'), true) ?? [];
    }

    public static function getPriority() {
        return 0.11;
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;

        $channelId = self::$channelsList[$channel];
        $date_start = $date . 'T04:00:00';
        $date_end = date('Y-m-d', strtotime($date . ' + 1 days')) . 'T03:59:59';
        
        $curl = curl_init('https://bo-apac.tv5monde.com/tvschedule/full?start=' . $date_start . '&end=' . $date_end . '&key=' . $channelId . '&timezone=Europe/Paris&language=fr');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $get = curl_exec($curl);
        curl_close($curl);

        $json = json_decode($get, true);
        
        if(!isset($json['data']) || empty($json['data'])) return false;

        $xmlPrograms = [];

        foreach($json['data'] as $program) {
            $data = [
                'startTime'   => strtotime($program['start']),
                'endTime'     => strtotime($program['end']),
                'channel'     => $channel,
                'title'       => $program['title'],
                'subTitle'    => @$program['episode_name'],
                'season'      => @$program['season'],
                'episode'     => @$program['episode'],
                'description' => $program['description'],
                'genre'       => $program['category'],
                'icon'        => $program['image']
            ];
            $xmlPrograms[] = self::generateXmltvProgram($data);
        }
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}