<?php
require_once 'Provider.php';

class SFR extends Provider {
    private static $tmpPath = 'epg/sfr/';
    private static $channelsList;
    
    public static function getPriority() {
        return 0.85;
    }

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_sfr.json'), true) ?? [];
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        $tmpJson = self::$tmpPath . $date . '.json';
        
        if(!file_exists($tmpJson)) {
            $curl = curl_init('https://static-cdn.tv.sfr.net/data/epg/gen8/guide_web_' . str_replace('-', '', $date) . '.json');
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            $get = curl_exec($curl);
            curl_close($curl);

            file_put_contents($tmpJson, $get);
        } else {
            $get = file_get_contents($tmpJson);
        }

        $json = json_decode($get, true);
        $programs = @$json['epg'];

        if(!isset($programs[$channelId]) || empty($programs[$channelId])) return false;

        $xmlPrograms = [];

        foreach($programs[$channelId] as $program) {
            if(isset($program['moralityLevel'])) {
                switch($program['moralityLevel']) {
                    case '2': $csa = '-10'; break;
                    case '3': $csa = '-12'; break;
                    case '4': $csa = '-16'; break;
                    case '5': $csa = '-18'; break;
                    default: $csa = 'TP';  break;
                }
            } else $csa = 'TP';
            
            $data = [
                'startTime'     => $program['startDate'] / 1000,
                'endTime'       => $program['endDate'] / 1000,
                'channel'       => $channel,
                'title'         => $program['title'] ?? '',
                'subTitle'      => @$program['subTitle'],
                'season'        => @$program['seasonNumber'],
                'episode'       => @$program['episodeNumber'],
                'description'   => @$program['description'],
                'genre'         => @$program['genre'],
                'icon'          => @$program['images'][0]['url'],
                'csa'           => $csa
            ];
            $xmlPrograms[] = self::generateXmltvProgram($data);
        }
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}