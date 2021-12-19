<?php
require_once 'Provider.php';

class Voo extends Provider {
    private static $channelsList;
    
    public function __construct() {
        if(!isset(self::$channelsList)) self::$channelsList = json_decode(@file_get_contents('channels_per_provider/channels_voo.json'), true) ?? [];
    }
    
    public static function getPriority() {
        return 0.55;
    }
    
    public function constructEPG($channel, $date, $xmlSave) {
        if(!isset(self::$channelsList[$channel])) return false;
        
        $channelId = self::$channelsList[$channel];
        $dateStart = $date . 'T04:00:00Z';
        $dateEnd = date('Y-m-d', strtotime($date . ' + 1 days')) . 'T03:59:59Z';
        $end = strtotime('now');
        
        $curl = curl_init('https://publisher.voomotion.be/traxis/web/Channel/' . $channelId . '/Events/Filter/AvailabilityEnd%3C=' . $dateEnd . '%26%26AvailabilityStart%3E=' . $dateStart . '/Sort/AvailabilityStart/Props/IsAvailable,Products,AvailabilityEnd,AvailabilityStart,ChannelId,AspectRatio,DurationInSeconds,Titles,Channels?output=json&Language=fr&Method=PUT');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0');
        $str = '<SubQueryOptions><QueryOption path="Titles">/Props/Name,Pictures,ShortSynopsis,LongSynopsis,Genres,Events,SeriesCount,SeriesCollection</QueryOption><QueryOption path="Titles/Events">/Props/IsAvailable</QueryOption><QueryOption path="Products">/Props/ListPrice,OfferPrice,CouponCount,Name,EntitlementState,IsAvailable</QueryOption><QueryOption path="Channels">/Props/Products</QueryOption><QueryOption path="Channels/Products">/Filter/EntitlementEnd>2018-01-27T14:40:43Z/Props/EntitlementEnd,EntitlementState</QueryOption></SubQueryOptions>';
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "" . $str . "");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        $get = curl_exec($curl);
        curl_close($curl);
        
        $json = json_decode($get, true);
        
        if(!isset($json['Events']['Event']) || empty($json['Events']['Event'])) return false;
        
        $xmlPrograms = [];
        
        foreach($json['Events']['Event'] as $program) {
            $start = strtotime($program['AvailabilityStart']);

            if($start > $end + 1) {
                $data = [
                    'startTime'   => $start,
                    'endTime'     => $end,
                    'channel'     => $channel,
                    'title'       => 'Pas de programme',
                    'description' => 'Pas de programme'
                ];
                $xmlPrograms[] = self::generateXmltvProgram($data);
            }

            $end = strtotime($program['AvailabilityEnd']);
            $data = [
                'startTime'   => $start,
                'endTime'     => $end,
                'channel'     => $channel,
                'title'       => $program['Titles']['Title'][0]['Name'],
                'description' => $program['Titles']['Title'][0]['LongSynopsis'] ?? @$program['Titles']['Title'][0]['ShortSynopsis'],
                'genre'       => $program['Titles']['Title'][0]['Genres']['Genre'][0]['Value'],
                'icon'        => @$program['Titles']['Title'][0]['Pictures']['Picture'][0]['Value']
            ];
            $xmlPrograms[] = self::generateXmltvProgram($data);
        }

        return file_put_contents($xmlSave, $xmlPrograms);
    }
}