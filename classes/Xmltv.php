<?php

class Xmltv {
	private $classesPriotity;
	private $channels;
	private $dayLimit;
	private $xmlPath;
	private $outputPath;
	private $classPrefix;

	public function __construct($classesPriotity, $channels, $dayLimit, $xmlPath, $outputPath, $classPrefix = 'EPG_') {
		$this->classesPriotity = $classesPriotity;
		$this->channels = $channels;
		$this->dayLimit = $dayLimit;
		$this->xmlPath = $xmlPath;
		$this->outputPath = $outputPath;
		$this->$classPrefix = $classPrefix;
	}

    private static function generateXmlFilePath($xmlPath, $channel, $date) {
        return $xmlPath . $channel . '_' . $date . '.xml';
    }
	
	public function getChannelsEPG() {
		echo "\e[0;95m[EPG] \e[0;39mRécupération du guide des programmes\n";
		$logs = array('channels' => [], 'xml' => [], 'failed_providers' => []);
		$channelsKey = array_keys($this->channels);

		foreach($channelsKey as $channel) {
			$priority = (isset($this->channels[$channel]['priority']) && count($this->channels[$channel]['priority']) > 0) ? $this->channels[$channel]['priority'] : $this->classesPriotity;

			for($i = -1; $i < $this->dayLimit; $i++) {
				$date = date('Y-m-d', time() + 86400 * $i);

				$log = $channel . " : " . $date;
				
				$xmlSave = self::generateXmlFilePath($this->xmlPath, $channel, $date);
				
				if(!file_exists($xmlSave)) {
					foreach($priority as $classe) {
						if(!class_exists($classe)) break;

						if(!isset(${$this->classPrefix . $classe})) ${$this->classPrefix . $classe} = new $classe();
						
						if(${$this->classPrefix . $classe}->constructEPG($channel, $date, $xmlSave)) {
							$logs['channels'][$date][$channel]['success'] = true;
							$log =  "\e[0;92m" . $log . " | OK\e[0;39m - " . $classe. "\n";
							$logs['channels'][$date][$channel]['provider'] = $classe;
							break;
						}
						
						$logs['channels'][$date][$channel]['failed_providers'][] = $classe;
						$logs['channels'][$date][$channel]['success'] = false;
						$logs['failed_providers'][$classe] = true;
					}

					if(!$logs['channels'][$date][$channel]['success']) $log = "\e[0;91m" . $log . " | HS\e[0;39m" . "\n";
				} else {
					$logs['channels'][$date][$channel]['provider'] = 'Cache';
					$log = $log . ' : Cache' . "\n";
					$logs['channels'][$date][$channel]['success'] = true;
				}

				echo "\e[0;95m[EPG]\e[39m " . $log;
			}
		}

		echo "\e[0;95m[EXPORT] \e[0;39mLOG...";
		$log = file_put_contents('logs/logs' . date('YmdHis') . '.json', json_encode($logs));
		echo $log ? "\e[1;92mOK\e[0;39m\n" : "\e[1;91mHS\e[0;39m\n";
	}

	public function generate() {
		$this->getChannelsEPG();
		
		echo "\e[0;95m[EXPORT] \e[0;39mCréation du fichier XMLTV...";
		$files = glob($this->xmlPath . '*');

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<tv source-info-url="http://allfrtv.com/" source-info-name="XML TV Fr" generator-info-name="XML TV Fr" generator-info-url="http://allfrtv.com/">' . "\n";

		foreach($this->channels as $key => $channel) {
			$icon = @$channel['icon'];
			$name = $channel['name'] ?? $key;

			$xml .= '  <channel id="' . $key . '">' . "\n";
			$xml .= '    <display-name>' . $name . '</display-name>' . "\n";
			$xml .= '    <icon src="' . $icon . '" />' . "\n";
			$xml .= '  </channel>' . "\n";
		}


		foreach($files as $key => $file) {
			if(time() - filemtime($file) > 864000) unlink($file);
			else $xml .= file_get_contents($file);
		}

		$xml .= '</tv>';

		$generateXml = file_put_contents($this->outputPath . '/xmltv.xml', $xml);
		echo $generateXml ? "\e[1;92mOK\e[0;39m\n" : "\e[1;91mHS\e[0;39m\n";
	}

	public function isValid() {
		echo "\e[0;95m[EXPORT] \e[0;39mVérification du fichier XMLTV...";

		libxml_use_internal_errors(true);
		$xml = @simplexml_load_file($this->outputPath . '/xmltv.xml');
		if($xml === false) {
			echo "\e[1;91mHS\e[0;39m\n";
			echo "Failed loading XML\n";
			foreach(libxml_get_errors() as $error) {
				echo "\t", $error->message;
			}
			libxml_clear_errors();
		} else echo "\e[1;92mOK\e[0;39m\n";
	}

	public function zipCompress() {
		echo "\e[0;95m[EXPORT] \e[0;39mCompression du XMLTV en ZIP...";
		$zip = new ZipArchive();
		$zipFilename = $this->outputPath . '/xmltv.zip';
		$zipXml = $zip->open($zipFilename, ZipArchive::CREATE);
		$zip->addFile($this->outputPath . '/xmltv.xml', 'xmltv.xml');
		$zip->close();
		echo $zipXml ? "\e[1;92mOK\e[0;39m\n" : "\e[1;91mHS\e[0;39m\n";
	}

	public function gzCompress() {
		echo "\e[0;95m[EXPORT] \e[39mCompression du XMLTV en GZ...";
        $xml = file_get_contents($this->outputPath.'/xmltv.xml');
        $gzXml = file_put_contents($this->outputPath.'/xmltv.xml.gz', gzencode($xml, true));
		echo $gzXml ? "\e[1;92mOK\e[0;39m\n" : "\e[1;91mHS\e[0;39m\n";
    }
}