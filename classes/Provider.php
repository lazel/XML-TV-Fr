<?php

abstract class Provider {
    abstract public function __construct();
    abstract public function constructEPG($channel, $date, $xmlSave);
    abstract static function getPriority();

    // $data :: startTime, endTime, channel, title, subTitle, description, season, episode, genre, genreDetailed, icon, year, csa
    protected static function generateXmltvProgram($data, $extra = false) {
		$xml  = '  <programme start="' . date('YmdHis O', $data['startTime']) . '" stop="' . date('YmdHis O', $data['endTime']) . '" channel="' . $data['channel'] . '">' . "\n";
		$xml .= '    <title lang="fr">' . htmlspecialchars($data['title'], ENT_XML1) . '</title>' . "\n";

		if(!empty($data['subTitle'])) $xml .= '    <sub-title lang="fr">' . htmlspecialchars($data['subTitle'], ENT_XML1) . '</sub-title>' . "\n";
		if(isset($data['season']) && isset($data['episode'])) $xml .= '    <episode-num system="xmltv_ns">' . ($data['season'] - 1) . '.' . ($data['episode'] - 1) . '.</episode-num>' . "\n";

		if(!empty($data['description'])) {
			$description = str_ireplace(['<p>', '</p>', '<i>', '</i>'], '', $data['description']);
            $description = str_ireplace('&nbsp;', ' ', $description);
			$description = htmlspecialchars($description, ENT_XML1);
		} else $description = '';
		
		if($extra) {
			$before = '';
			$after = '';

			if(isset($data['season']) || isset($data['episode']) || !empty($data['subTitle'])) {
				if(isset($data['season'])) $before .= 'Saison ' . $data['season'];
				if(isset($data['season']) && isset($data['episode'])) $before .= ' ';
				if(isset($data['episode'])) $before .= 'Episode ' . $data['episode'];
				if(isset($data['season']) || isset($data['episode'])) $before .=  ' : ';
				if(isset($data['subTitle'])) $before .= htmlspecialchars($data['subTitle'], ENT_XML1);
				if(isset($data['description'])) $before .= "\n";
			}

			if(isset($data['year'])) $after = "\n" . 'Année de réalisation : ' . $data['year'];

			$description = $before . $description . $after;
		}

		if(empty($description)) $description = 'Aucune description';

		$description = preg_replace(array("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "/\n$/"), array("\n", ""), $description);

		$xml .= '    <desc lang="fr">' .  $description . '</desc>' . "\n";
		
		$xml .= '    <category lang="fr">' . (isset($data['genre']) ? htmlspecialchars($data['genre'], ENT_XML1) : 'Inconnu') . '</category>' . "\n";
		
		if(!empty($data['genreDetailed'])) $xml .= '    <category lang="fr">' . htmlspecialchars($data['genreDetailed'], ENT_XML1) . '</category>' . "\n";
		
		if(!empty($data['icon'])) $xml .= '    <icon src="' . htmlspecialchars($data['icon'], ENT_XML1) . '"/>' . "\n";
		
		if(!empty($data['year'])) $xml .= '    <year>' . htmlspecialchars($data['year'], ENT_XML1) . '</year>' . "\n";
		
		if(!empty($data['csa'])) {
			$xml .= '    <rating system="csa">' . "\n";
	    	$xml .= '      <value>' . htmlspecialchars($data['csa'], ENT_XML1) . '</value>' . "\n";
	    	$xml .= '    </rating>' . "\n";
		}
	    
	    $xml .= '  </programme>' . "\n";
		
	    return str_replace(array("\0", "\x14"), '', $xml);
	}
}