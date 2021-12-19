<?php
require_once 'Provider.php';

class Orange extends Provider {
    private static $channelsList;
    
    public static function getPriority() {
        return 0.80;
    }
    
    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_orange.json'), true) ?? [];
    }
    
    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        
        $curl = curl_init('https://rp-live.orange.fr/live-webapp/v3/applications/STB4PC/programs?period=' . $date . '&epgIds=' . $channelId . '&mco=OFR');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $get = curl_exec($curl);
        curl_close($curl);

        if(preg_match('(Invalid request)', $get) || preg_match('(504 Gateway Time-out)', $get)) return false;

        $programs = json_decode($get, true);
        
        if(!isset($programs) || @$programs['code'] == 60 || empty($programs)) return false;
        
        $xmlPrograms = [];
        
        foreach($programs as $program) {
            if(isset($program['csa'])) {
                switch($program['csa']) {
                    case '2': $csa = '-10'; break;
                    case '3': $csa = '-12'; break;
                    case '4': $csa = '-16'; break;
                    case '5': $csa = '-18'; break;
                    default: $csa = 'TP';  break;
                }
            } else $csa = 'TP';
            
            $data = [
                'startTime'     => $program['diffusionDate'],
                'endTime'       => $program['diffusionDate'] + $program['duration'],
                'channel'       => $channel,
                'description'   => $program['synopsis'],
                'genre'         => $program['genre'],
                'genreDetailed' => $program['genreDetailed'],
                'icon'          => @$program['covers'][0]['format'] == 'RATIO_16_9' ? @$program['covers'][0]['url'] : @$program['covers'][1]['url'],
                'csa'           => $csa
            ];
            
            if(!isset($program['season'])) {
                $data['title'] = $program['title'];
            } else {
                if(empty($program['season']['number'])) $program['season']['number'] = '1';
                if(empty($program['episodeNumber'])) $program['episodeNumber'] = '1';
                
                $data['title']    = $program['season']['serie']['title'];
                $data['subTitle'] = $program['title'];
                $data['season']   = $program['season']['number'];
                $data['episode']  = $program['episodeNumber'];
            }
            $xmlPrograms[] = self::generateXmltvProgram($data);
        }
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}