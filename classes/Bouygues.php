<?php
require_once 'Provider.php';

class Bouygues extends Provider {
    private static $channelsList;

    public static function getPriority() {
        return 0.90;
    }

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_bouygues.json'), true) ?? [];
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        
        $date_start = $date . 'T04:00:00Z';
        $date_end = date('Y-m-d', strtotime($date . ' + 1 days')) . 'T03:59:59Z';
        
        $curl = curl_init('http://epg.cms.pfs.bouyguesbox.fr/cms/sne/live/epg/events.json?profile=detailed&epgChannelNumber=' . $channelId . '&eventCount=999&startTime='.$date_start.'&endTime='.$date_end);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $get = curl_exec($curl);
        curl_close($curl);
        
        $json = json_decode($get, true);
        
        if(!isset($json['channel'][0]['event']) || empty($json['channel'][0]['event'])) return false;

        $xmlPrograms = [];
        
        foreach($json['channel'][0]['event'] as $program) {
            $genre = @$program['programInfo']['genre'][0];
            $subGenre = @$program['programInfo']['subGenre'][0];

            if(isset($program['parentalGuidance'])) {
                $csa = explode('.', $program['parentalGuidance']);

                switch((int)end($csa)) {
                    case 2: $csa = '-10'; break;
                    case 3: $csa = '-12'; break;
                    case 4: $csa = '-16'; break;
                    case 5: $csa = '-18'; break;
                    default: $csa = 'TP';  break;
                }
            } else $csa = 'TP';

            if(!is_null($genre) && !is_null($subGenre) && $genre == $subGenre) {
                if(isset($program['programInfo']['genre'][1])) $genre = $program['programInfo']['genre'][1];
                else $subGenre = null;
            }
            
            $data = [
                'startTime'     => strtotime($program['startTime']),
                'endTime'       => strtotime($program['endTime']),
                'channel'       => $channel,
                'title'         => $program['programInfo']['longTitle'],
                'subTitle'      => @$program['programInfo']['secondaryTitle'],
                'description'   => @$program['programInfo']['longSummary'] ?? @$program['programInfo']['shortSummary'],
                'season'        => @$program['programInfo']['seriesInfo']['seasonNumber'],
                'episode'       => @$program['programInfo']['seriesInfo']['episodeNumber'],
                'genre'         => $genre ,
                'genreDetailed' => $subGenre ,
                'icon'          => isset($program['media'][0]['url']) ? 'https://img.bouygtel.fr' . $program['media'][0]['url'] : null,
                'year'          => @$program['programInfo']['productionDate'],
                'csa'           => $csa
            ];
            $xmlPrograms[] = self::generateXmltvProgram($data);
        }
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}