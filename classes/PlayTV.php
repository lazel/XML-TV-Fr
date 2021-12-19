<?php
require_once 'Provider.php';

class PlayTV extends Provider {
    private static $channelsList;

    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_playtv.json'), true) ?? [];
    }

    public static function getPriority() {
        return 0.65;
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        
        $curl = curl_init('http://m.playtv.fr/api/programmes/?channel_id=' . $channelId . '&date=' . $date . '&preset=daily');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $get = curl_exec($curl);
        curl_close($curl);
        $get = str_replace('assets\/images\/tv-default.svg', 'http://img.src.ca/ouglo/emission/480x270/findesemissions.jpg', $get);
        
        $json = json_decode($get, true);
        
        if(empty($json)) return false;
        
        $xmlPrograms = [];

        foreach($json as $program) {

            switch ($program['program']['csa_id']) {
                case '2': $csa = '-10'; break;
                case '3': $csa = '-12'; break;
                case '4': $csa = '-16'; break;
                case '5': $csa = '-18'; break;
                default: $csa = 'TP';  break;
            }

            $data = [
                'startTime'     => $program['start'],
                'endTime'       => $program['end'],
                'channel'       => $channel,
                'title'         => $program['program']['title'],
                'subTitle'      => @$program['program']['subtitle'],
                'description'   => $program['program']['summary_long'],
                'genre'         => $program['program']['gender'],
                'genreDetailed' => @$program['program']['subgender'],
                'year'          => @$program['program']['year'],
                'csa'           => $csa
            ];


            $data['icon'] = $program['program']['images']['xlarge'] ?? 
                            $program['program']['images']['large'] ?? 
                            $program['program']['images']['medium'] ?? 
                            @$program['program']['images']['small'];

            if(isset($program['program']['episode'])) {
                if(empty($program['program']['season'])) $program['program']['season'] = '1';

                $data['season']  = $program['program']['season'];
                $data['episode'] = $program['program']['episode'];
            }

            $xmlPrograms[] = self::generateXmltvProgram($data);
        }

        return file_put_contents($xmlSave, $xmlPrograms);
    }
}