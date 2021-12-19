<?php
require_once 'Provider.php';

class Tele7Jours extends Provider {
    private static $channelsList;

    public static function getPriority() {
        return 0.60;
    }

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_tele7jours.json'), true) ?? [];
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];

        $xmlPrograms = [];
        $programs = [];
        $lastTime = 0;

        $multiCurl = [];
        $curl_multi = curl_multi_init();
        
        for($i = 1; $i < 7; $i++) {
            $multiCurl[$i] = curl_init('https://www.programme-television.org/grid/tranches/' . $channelId . '_' . date('Ymd', strtotime($date)) . '_t' . $i . '.json');
            curl_setopt($multiCurl[$i], CURLOPT_HEADER, 0);
            curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_multi_add_handle($curl_multi, $multiCurl[$i]);
        }

        do {
            curl_multi_exec($curl_multi, $running);
        } while($running > 0);

        foreach($multiCurl as $curl) {
            $get = curl_multi_getcontent($curl);
            curl_multi_remove_handle($curl_multi, $curl);
            curl_close($curl);

            $get = str_replace(['$.la.t7.epg.grid.showDiffusions(', '127,101,', ');'], '', $get);
            $json = json_decode($get, true);

            $dateGrille = @$json['grille']['dateGrille'];

            if(!isset($json['grille']['aDiffusion']) || empty($json['grille']['aDiffusion'])) return false;

            foreach($json['grille']['aDiffusion'] as $program) {
                $startTime = strtotime($dateGrille . ' ' . str_replace('h', ':', $program['heureDif']));
                
                if($startTime != $lastTime) {
                    if($startTime < $lastTime) $startTime += 86400;

                    $programs[$startTime] = [
                        'startTime' => $startTime,
                        'channel'   => $channel,
                        'title'     => $program['titre'],
                        'subTitle'  => @$program['soustitre'],
                        'season'    => (isset($program['saison']) && $program['saison'] > 0) ? $program['saison'] : null,
                        'episode'   => (isset($program['numEpi']) && $program['numEpi'] > 0) ? $program['numEpi'] : null,
                        'genre'     => $program['nature'],
                        'icon'      => isset($program['photo']) ? str_replace(['forcex,center-middle', '_43.'], ['640,360', '_169.'], $program['photo']) : null
                    ];

                    if($lastTime > 0) {
                        $programs[$lastTime]['endTime'] = $startTime;
                        $xmlPrograms[] = self::generateXmltvProgram($programs[$lastTime]);
                    }
                }

                $lastTime = $startTime;
            }
        }

        curl_multi_close($curl_multi);

        if(empty($programs)) return false;
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}