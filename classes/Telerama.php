<?php
require_once 'Provider.php';

class Telerama extends Provider {
    private static $channelsList;
    private static $userAgent = 'okhttp/3.12.3';
    private static $apiCle = 'apitel-g4aatlgif6qzf'; // apitel-5304b49c90511
    private static $hashKey = 'uIF59SZhfrfm5Gb'; // Eufea9cuweuHeif
    private static $appareil = 'android_tablette';
    private static $host = 'http://api.telerama.fr';
    private static $nbParPage = '800000';
    private static $page = 1;

    public static function getPriority() {
        return 0.95;
    }
    
    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_telerama.json'), true) ?? [];
    }

    public static function signature($url) {
        return hash_hmac('sha1', str_replace(['=', '?', '&'], '', $url), self::$hashKey);
    }

    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        $url = '/v1/programmes/grille?appareil=' . self::$appareil . '&date=' . $date . '&id_chaines=' .  $channelId . '&nb_par_page=' .  self::$nbParPage . '&page=' .  self::$page;
        $url .= '&api_cle=' .  self::$apiCle . '&api_signature=' . self::signature($url);
        
        $curl = curl_init(self::$host . $url);
        curl_setopt($curl, CURLOPT_USERAGENT, self::$userAgent);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $get = curl_exec($curl);
        curl_close($curl);

        $json = json_decode($get, true);

        if(!isset($json['donnees']) || empty($json['donnees'])) return false;
        
        $xmlPrograms = [];
        
        foreach($json['donnees'] as $program) {
            $data = [
                'startTime'   => strtotime($program['horaire']['debut']),
                'endTime'     => strtotime($program['horaire']['fin']),
                'channel'     => $channel,
                'title'       => $program['titre'],
                'subTitle'    => @$program['soustitre'],
                'season'      => @$program['serie']['saison'],
                'episode'     => @$program['serie']['numero_episode'],
                'description' => $program['resume'],
                'genre'       => $program['genre_specifique'],
                'icon'        => @$program['vignettes']['grande169'],
                'year'        => @$program['annee_realisation'],
                'csa'         => $program['csa'] != 'TP' ? '-' . $program['csa'] : $program['csa']
            ];
            $xmlPrograms[] = self::generateXmltvProgram($data);
        }

        return file_put_contents($xmlSave, $xmlPrograms);
    }
}