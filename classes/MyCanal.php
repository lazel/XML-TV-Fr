<?php
require_once 'Provider.php';

class MyCanal extends Provider {
    private static $channelsList;
    private static $apiKey = '4ca2e967e4ca296ab18dab5432f906ac';
    
    public static function getPriority() {
        return 0.70;
    }
    
    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_mycanal.json'), true) ?? [];
    }
    
    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        $day = (strtotime($date) - strtotime(date('Y-m-d'))) / 86400;
        
        $curl = curl_init('https://hodor.canalplus.pro/api/v2/mycanal/channels/' . self::$apiKey . '/' . $channelId . '/broadcasts/day/' . $day);
        $curl1 = curl_init('https://hodor.canalplus.pro/api/v2/mycanal/channels/' . self::$apiKey . '/' . $channelId . '/broadcasts/day/' . ($day + 1));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl1, CURLOPT_FOLLOWLOCATION, true);
        $curl_multi = curl_multi_init();
        curl_multi_add_handle($curl_multi, $curl);
        curl_multi_add_handle($curl_multi, $curl1);
        
        do {
            curl_multi_exec($curl_multi, $running);
        } while($running > 0);

        $get = curl_multi_getcontent($curl);
        $get2 = curl_multi_getcontent($curl1);

        curl_multi_remove_handle($curl_multi, $curl);
        curl_close($curl);
        curl_multi_remove_handle($curl_multi, $curl1);
        curl_close($curl1);
        curl_multi_close($curl_multi);

        $json = json_decode($get, true);
        $json2 = json_decode($get2, true);

        if(!isset($json['timeSlices']) || empty($json['timeSlices'])) return false;

        $all = [];
        foreach($json['timeSlices'] as $section) {
            $all = array_merge($all, $section['contents']);
        }

        if(@$nd = $json2['timeSlices'][0]['contents'][0]) $all[] = $nd;
        
        $xmlPrograms = [];
        $programs = [];
        $lastTime = 0;
        
        foreach($all as $program) {
            $curld = curl_init($program['onClick']['URLPage']);
            curl_setopt($curld, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curld, CURLOPT_FOLLOWLOCATION, 1);
            $getd = curl_exec($curld);
            curl_close($curld);

            $detail = json_decode($getd, true);

            $startTime = $program['startTime'] / 1000;

            $parentalRating = $detail['episodes']['contents'][0]['parentalRatings'][0]['value'] ?? @$detail['detail']['informations']['parentalRatings'][0]['value'];

            switch($parentalRating) {
                case '2': $csa = '-10'; break;
                case '3': $csa = '-12'; break;
                case '4': $csa = '-16'; break;
                case '5': $csa = '-18'; break;
                default: $csa = 'TP';  break;
            }

            $icon = $detail['episodes']['contents'][0]['URLImage'] ?? @$detail['detail']['informations']['URLImage'];
            $icon = str_replace(array('{resolutionXY}', '{imageQualityPercentage}'), array('640x360', '80'), $icon);
                       
            $programs[$startTime] = [
                'startTime'     => $startTime,
                'channel'       => $channel,
                'title'         => $detail['tracking']['dataLayer']['content_title'],
                'subTitle'      => @$detail['episodes']['contents'][0]['subtitle'],
                'description'   => $detail['episodes']['contents'][0]['summary'] ?? @$detail['detail']['informations']['summary'],
                'season'        => @$detail['detail']['selectedEpisode']['seasonNumber'],
                'episode'       => @$detail['detail']['selectedEpisode']['episodeNumber'],
                'genre'         => $detail['tracking']['dataLayer']['genre'],
                'genreDetailed' => $detail['tracking']['dataLayer']['subgenre'],
                'icon'          => $icon,
                'year'          => @$detail['detail']['informations']['productionYear'],
                'csa'           => $csa
            ];
            
            if($lastTime > 0) {
                $programs[$lastTime]['endTime'] = $startTime;
                $xmlPrograms[] = self::generateXmltvProgram($programs[$lastTime]);
            }
            
            $lastTime = $startTime;
        }
        
        return file_put_contents($xmlSave, $xmlPrograms);
    }
}