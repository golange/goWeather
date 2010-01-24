<?php
/**
* @package	Joomla 1.5
* @copyright Copyright (C) 2010 Thomas Lange. All rights reserved.
* @license	GNU/GPLv2,
* Parse and display yr.no  weather data
*/

defined('_JEXEC') or die('Restricted access');

class modGoWeatherTarget {
	public $location;
	public $name;
	public $id;
}

class modGoWeatherDate {
	public $dayOfWeek;
	public $dayOfMonth;
	public $month;
	public $periods = array();
}

class modGoWeatherHelper {
	const MAXLOCATIONS = 10;
	const QUERYID = 'modgowid';
	const QUERYDAY = 'modgowday';

	public $debug = false;
	public $showName;
	public $currentTarget;
	public $targets = array();
	public $yrLink;
	public $days;
	public $timeout;
	public $useBorders;
	public $backgroundColor;
	public $useBackgroundImage;
	public $backgroundImage;
	public $celsius;
	public $dynamicColors;
	public $showPeriod = array();
	public $useDefaultCSS;
	public $otherCSS;
	public $time24h;
	public $firstDay;

	public function fixQuery( $path, $query ) {
		if ( $path[strlen($path) - 1] != '/' ) {
			$path .= '/';
		}
		
		if( $query ) {
			$newQuery .= "?$query" . '&';
			return preg_replace( '/&/', '&amp;', $newQuery );
		}
		else {
			return $path . '?';
		}
	}

	private function getImages( $dir ) {
		$files	= array();
		if ( $handle = opendir( $dir )) {
			while ( false !== ( $file = readdir( $handle ))) {
				if ( $file != '.' && $file != '..' && $file != 'index.html' ) {
					$files[] = $file;
				}
			}
		}
		closedir( $handle );
		
		return $files;
	}

	private function selectRandom( $data ) {
		$i = count( $data );
		return $data[ mt_rand(0, $i - 1) ];
	}

	private function getTempColor( $temperatureC, $dynamic ) {
		if( !$dynamic ){
			if ( $temperatureC < 0 ) {
				return 'blue';
			}
			else {
				return 'red';
			}
		}

		// -30C #0000ff
		//   0C #800080
		// +30C #ff0000
		
		if ( $temperatureC >= 30 ){
			$red = 255;
		}
		elseif ( $temperatureC <= -30 ) {
			$red = 0;
		}
		else {
			$red =  (int)round((( $temperatureC + 30 ) * 255 / 60));
		}
		
		$blue = 255 - $red;
		
		return 'rgb(' . $red . ',0,' . $blue . ')';
	}

	// Find max temp in two first days
	private function maxTemp( $dates ) {

		if ( !$dates ) {
			return NULL;
		}

		$max = -100;

		for ( $i=0; $i < 2; $i++ ) {
			foreach ( $dates[ $i ] as $period ) {
				if ( $period[ 'temperatureC' ] > $max ) {
					$max = $period[ 'temperatureC' ];
				}
			}
		}
		return $max;
	}


	private function windArrow( $windDirection ){
		$dir = round( (float)$windDirection / 10) * 10;
		
		if( $dir >= 360 ){
			$dir = 0;
		}
		
		return 'arrow_' . $dir . '.png';

	}

	private function windMeter( $windSpeed ){
		if ( $windSpeed <= 25 ) {
			$meter = $windSpeed;
		}
		elseif ( $windSpeed >= 35 ) {
			$meter = 35;
		}
		else {
			// 2 m/s per pixel on red scale
			if ( $windSpeed & 1 ) {
				$meter = $windSpeed;
			}
			else {
				$meter = $windSpeed + 1;
			}
		}

		return 'speed_' . $meter . '.png';
	}

	// Get current moon phase in percent (0 == new moon)
	private function moonPhase ( $time ) {
		$time -= 588900 ; // 7 January 1970 20:35, first new moon in Unix existance

		$days = $time / 86400; // Days since that first new moon

		$phase = $days / 29.5305882; // Divide with moon cycle

		$phase -= (int)$phase; // Get remainder

		$phase = (int)round( $phase * 100 );

		if ($phase >= 100 ){
			$phase = 0;
		}
		return $phase;
	}

	private function chillTemp( $temperatureC, $windSpeedKph ) {
		// http://en.wikipedia.org/wiki/Wind_chill
		
		$chill = 13.12 
			+ ( 0.6215 * $temperatureC ) 
			+ ( 0.3965 * $temperatureC * pow( $windSpeedKph, 0.16 )) 
			- ( 11.37 * pow( $windSpeedKph, 0.16 ));
		
		if ( $chill > $temperatureC ){
			// Not enough wind to create chill effect and then formula actually gives higher temp!
			return $temperatureC;
		}
		return $chill;
	}

	private function curlGetXML($url, $timeout) {			
		if ( !function_exists( curl_init )) {
			JError::raiseWarning( '', 'PHP CURL does not seem to be installed. Required for goWeather.' );
			return;
		}
		
		$ch = curl_init();
		
		$options = array(
						 CURLOPT_POSTFIELDS => '',
						 CURLOPT_URL => $url,
						 CURLOPT_HEADER => 0,
						 CURLOPT_RETURNTRANSFER => true,
						 CURLOPT_CONNECTTIMEOUT => $timeout,
						 CURLOPT_TIMEOUT => $timeout
						 );
		
		curl_setopt_array($ch, $options);
		
		$res = curl_exec($ch);
		curl_close($ch);
		
		return $res;		
	}	
	
	// Sort per date and prepare most of the data now to save resources (will be cached)
	private function buildDates( $my, $xmlData ) {
		$dates = array();
		$thisDate = new modGoWeatherDate();
		$lastDay = '';
		
		foreach ( $xmlData->forecast->tabular->time as $item ) {
			$fromSecs = strtotime( $item[from] );
			$toSecs = strtotime( $item[to] );
			$middleSecs = ( $toSecs + $fromSecs ) / 2;

			$period = (string)$item[period];
			
			$dayOfMonth = date( 'j', $middleSecs );
				
			if ( !$lastDay or $lastDay != $dayOfMonth or $period == '0' ){
				$lastDay = $dayOfMonth;
				
				if ( $thisDate->periods ) {
					$dates[] = $thisDate;
					$thisDate = new modGoWeatherDate();
				}
				$thisDate->dayOfWeek = date( 'l', $middleSecs );
				$thisDate->month = date( 'F', $middleSecs );  
				$thisDate->dayOfMonth = $dayOfMonth;
			}

			$symbolNumber = (int)$item->symbol[number];
			
			// FIXME YR Says 'fair' for symbol 1??? 
			// In norweigan, it is correct however!
			if ($symbolNumber == 1){
				// Workaround
				$symbolName = 'Clear sky';
			}
			else{
				$symbolName = (string)$item->symbol[name];
			}
			
			if ( $item->temperature[unit] != 'celcius' ){
				// Just to be sure we notice
				JError::raiseNotice( 'Not C', 'Not celsius temps from YR!' );
			}
			
			$temperatureC = (int)$item->temperature[value];
			
			$temperatureF = (int)round( $temperatureC * 9/5 + 32 ); 
			
			$windSpeedMps = (float)$item->windSpeed[mps];
			
			$windSpeedKph = $windSpeedMps * 3.6;

			$windSpeedMph = 2.2369 * $windSpeedMps;

			$temperatureChillC = modGoWeatherHelper::chillTemp( $temperatureC, $windSpeedKph );
			
			$temperatureChillF = (int)round( $temperatureChillC * 9/5 + 32 ); 
			
			$temperatureChillC = (int)round( $temperatureChillC ); 
			
			$temperatureColor = modGoWeatherHelper::getTempColor( $temperatureC, $my->dynamicColors );
			
			$windDeg = (float)$item->windDirection[deg];

			$windArrow = modGoWeatherHelper::windArrow( $windDeg );
			$windMeter = modGoWeatherHelper::windMeter( (int)round( $windSpeedMps ));

			$juri = &JURI::getInstance();
			$windArrow = $juri->base() . 'modules/mod_goweather/images/wind/' . $windArrow;
			$windMeter = $juri->base() . 'modules/mod_goweather/images/wind/' . $windMeter;

			$symbolImg = $symbolNumber;
			if( $symbolImg < 10) {
				$symbolImg = '0' . $symbolImg;
			}
			
			switch ( $symbolImg ){
			case '01': 
			case '02': 
			case '03': 
			case '05': 
			case '06': 
			case '07': 
			case '08':
				// Sun or moon is showing
				switch($period["period"]){
				case '0':
				case '3':
					// Treat as night
					$moonPhase =  modGoWeatherHelper::moonphase( $middleSecs );
					
					$symbolImg = 'mf/' . $symbolImg . 'n.' 
						. sprintf( '%02d', $moonPhase);
					break;
					
				default:
					// Day
					$symbolImg .= 'd';
					break;
				}
				break;
				
			default:
				// Same image for day and night
				break;
			}

			$windSpeedMps = (int)round($windSpeedMps) . ' ' . strtolower( JText::_('Mps'));
			$windSpeedMph = (int)round($windSpeedMph) . ' mph';
			$windSpeedKph = (int)round($windSpeedKph) . ' km/h';

			if( (float)$item->precipitation[value] > 0 ) {
				$precipitationMm = (string)$item->precipitation[value] . ' mm'; 
				$precipitationInch = round( (float)$item->precipitation[value] * 0.03937, 2 ) . ' inch'; 
			}
			else {
				$precipitationMm = $precipitationInch = NULL;
			}

			$middle24h = date( "H:i", $middleSecs );
			$middle12h = date( "g A", $middleSecs );

			$period24h = date( "H:i", $fromSecs ) . ' - ' . date("H:i", $toSecs ); 
			$period12h = date( "g A", $fromSecs ) . ' - ' . date("g A", $toSecs ); 

			$thisDate->periods[] = array( 'period' => $period,
										  'symbolFile' => $symbolImg,
										  'symbolName' => $symbolName, 
										  'precipitationMm' => $precipitationMm,
										  'precipitationInch' => $precipitationInch,
										  'windDirectionDeg' => $windDeg, 
										  'windSpeedMps' => $windSpeedMps,
										  'windSpeedMph' => $windSpeedMph,
										  'windSpeedKph' => $windSpeedKph,
										  'windArrow' => $windArrow,
										  'windMeter' => $windMeter,
										  'temperatureC' => $temperatureC,
										  'temperatureF' => $temperatureF,
										  'temperatureChillC' => $temperatureChillC,
										  'temperatureChillF' => $temperatureChillF,
										  'temperatureColor' =>  $temperatureColor,
										  'middle24h' => $middle24h,
										  'middle12h' => $middle12h, 
										  'period24h' => $period24h,
										  'period12h' => $period12h, 
										  );
		}
		return $dates;
	}

	private function encodeUrl( $url ) {
		if ( strpos( $url, '%' )){
			// Already encoded
			return $url;
		}
		// Convert / back for readability
		return str_replace( "%2F", "/", urlencode( $url ));
	}
	
	public function getWeather( $my ) {
		$url = 'http://www.yr.no/place/' . modGoWeatherHelper::encodeUrl( $my->currentTarget->location ) . '/forecast.xml';
		
		if ( $my->debug ){
			JError::raiseNotice( '', 'Using ' . $url );
		}

		$weatherXML = modGoWeatherHelper::curlGetXML( $url, $my->timeout );

		if ( !$weatherXML ){
			if ( $my->debug ){
				JError::raiseWarning( '', 'CURL could not fetch any data from URL' );
			}
		}
		else {
			// Don't expose errors on page
			$weather = @simplexml_load_string( $weatherXML );
		}

		$cache= & JFactory::getCache( 'mod_goweather' );
		
		$cache->setCaching( true ); // Always enable caching

		if ( !$weatherXML or !$weather->location->name ){
			if ( $my->debug ){
				JError::raiseWarning( '', 'Forecast XML loading failed' );
				JError::raiseWarning( '', 'Not valid XML forecast data. Could be bad URL, network failure, internal failure at yr.no etc' );
				JError::raiseWarning( '', 'Writing the source data to cache to allow manual analysis' );
				$cache->store( $weatherXML, $url . 'x'); // Force different id
			}

			// Perhaps we have some old data in cache?
			$callback = array('modGoWeatherHelper', 'getOldXMLData');
			$weatherXML =  $cache->get($callback, $url, $url);
			
			if ( !$weatherXML ){
				if ( $my->debug ){
					JError::raiseWarning( '', 'Long lived cache was empty' );
				}
				return false;
			}
			if ( $my->debug ){
				JError::raiseNotice( '', 'Found old data in Long lived cache' );
			}
			// We only store valid data, so this should be ok
			$weather = simplexml_load_string( &$weatherXML );
		}
		else {
			// Keep correct data in case of failure later on
			$cache->setLifeTime(3600*48); // 48h
			
			// Hack alert!
			$cached = array();
			$cached['output'] = '';
			$cached['result'] = $weatherXML;
			
			if ( !$cache->store(serialize($cached), $url )){
				JError::raiseWarning( $module->id, 'Weather cache write failed' );
			}
		}

		$dates = modGoWeatherHelper::buildDates( &$my, &$weather );

		$out = array();

		$out[ 'header' ] = array( 'fetchedAt' => date( DATE_ISO8601 ),
								  'maxTemp' => modGoWeatherHelper::maxTemp( &$dates )
								  );
		
		$out[ 'dates' ] = $dates;

		return $out;
	}

	public function getOldXMLData( $url) {
		// Dummy handler. Should never be called unless first call fails or a really long time has passed.
		return false;
	}

	public function isSpecialLocation( $location ) {
		return $location == 'group';
	}
	
	public function init( $params, 
						  $module ) {
		$my = new modGoWeatherHelper();
		
		if ( $params->get( 'debug', '0' )) {
			$userData = &JFactory::getuser();
			
			if ( strpos( $userData->usertype, 'Administrator' )) {
				// Always show debug logs for admins
				$my->debug = 1;
			}
			else {
				switch ( $params->get( 'debug_user', 'off' )) {
				case 'normal':
					// Business as usual
					break;
					
				case 'logs':
					$my->debug = 1;
					break;
					
				default:
					// Disable completely 
					return false;
				}
			}
			if ( $my->debug ) {
				JError::raiseNotice( '', 'Debugging ' . $module->title . ', Network caching disabled!' );
			}
		}
		
		$my->showName = $params->get( 'show_name', '1' );
		
		if ( !$my->showName ) {
			// Not possible to choose location. Just find first one.
			for ( $i = 1; $i <= modGoWeatherHelper::MAXLOCATIONS; $i++ ) {
				$location = $params->get( 'location' . $i, NULL );
				
				if ( $location and !modGoWeatherHelper::isSpecialLocation( $location )) {
					$my->currentTarget = new modGoWeatherTarget;
					$my->currentTarget->name = $params->get( 'name' . $i, NULL );
					$my->currentTarget->location = $location;
					$my->currentTarget->id = $i;
					break;
				}
			}
			if ( !$my->currentTarget ) {
				JError::raiseWarning( '', 'No weather location has been set for ' .  $module->title );
				return false;
			}
		}
		else {
			for ( $i = 1; $i <= modGoWeatherHelper::MAXLOCATIONS; $i++ ) {
				$location = $params->get( 'location' . $i, NULL );
				if ( $location ) {
					$name = $params->get( 'name' . $i, NULL );
					if ( $name ) {
						$target = new modGoWeatherTarget();
						$target->location = $location;
						$target->name = $name;
						$target->id = $i;
						$my->targets[] = $target;
					}
				}
			}
			
			if( empty( $my->targets )) {
				JError::raiseWarning( '', 'No weather location has been set for ' . $module->title );
				return false;
			}
			
			$selected = JRequest::getVar( modGoWeatherHelper::QUERYID . $module->id, 1, 'get', 'int' );
			
			if (( $selected < 1 ) or ( $selected > modGoWeatherHelper::MAXLOCATIONS )) {
				// Could be hack attempt. Paranoia.
				$selected = 1;
			}
			
			// Note! If admin makes live changes, we could end up with wrong location or none at all
			foreach ( $my->targets as $target ) {
				if ( $target->id == $selected ) {
					$my->currentTarget = $target;
					break;
				}
			}
			
			if ( !$my->currentTarget or modGoWeatherHelper::isSpecialLocation( $my->currentTarget->location )) {
				if ( $my->debug and !modGoWeatherHelper::isSpecialLocation( $my->currentTarget->location )) {
					JError::raiseWarning( '', 'Bad location data for ' . $selected );
				}
				// Fallback to first real one in list
				foreach( $my->targets as $target ) {
					if ( !modGoWeatherHelper::isSpecialLocation( $target->location )) {
						$my->currentTarget = $target;
						break;
					}
				}
				if ( !$my->currentTarget ) {
					JError::raiseWarning( '', 'No valid weather location has been set for ' . $module->title );
					return false;
				}
			}
		}

		$my->scroll = (int)$params->get('scroll', '1');
		
		if ( $my->scroll ) {
			$my->firstDay = JRequest::getVar( modGoWeatherHelper::QUERYDAY . $module->id, NULL, 'get', 'int' );
			if ( $my->firstDay ) {
				if ( $my->firstDay < 1 or $my->firstDay > 31 ) {
					// Could be hack attempt. Paranoia.
					$my->firstDay = NULL;
				}
			}
		}
		else {
			$my->firstDay = NULL;
		}

		$my->yrLink = $params->get('yrlink', '');
		
		$my->days = (int)$params->get('days', '2');

		if ( $my->days > 9 ) {
			$my->days = 9;
		}
		elseif ( $my->days < 1 ) {
			$my->days = 1;
		}
		
		$my->timeout = (int)$params->get('timeout', 5 );
		
		if ( $my->timeout > 30 ) {
			$my->timeout = 30;
		}
		elseif ( $my->timeout < 1 ){
			// 1 sec is really to short, but... 
			$my->timeout = 1;
		}
		
		$my->useBorders = $params->get( 'borders', '1' );
		
		$my->backgroundColor = $params->get( 'background_color', 'inherit' );

		$type = $params->get( 'background_image_type', 'single' );

		$juri = &JURI::getInstance();
		$defaultImgDir = $juri->base() . 'modules/mod_goweather/images/';

		if ( $type == 'none' ) {
			$my->useBackgroundImage = false;
		}
		else {
			$my->useBackgroundImage = true;
			$backgroundImage = $params->get( 'background_image_path', NULL );
		
			switch ( $type ) {
			case 'random':
			case 'dynamic':
				if ( !$backgroundImage ) {
					$backgroundImage = '/modules/mod_goweather/images/backgrounds';
				} 
				else {
					if ( !is_dir(JPATH_SITE . '/' . $backgroundImage)) {
						if ( $my->debug ) {
							JError::raiseWarning( '', JPATH_SITE . $backgroundImage . ' is not a directory'  );
							
						}
						return;
					}
				}
				$images = modGoWeatherHelper::getImages( JPATH_SITE . '/' . $backgroundImage );

				if ( !$images ) {
					if ( $my->debug ) {
						JError::raiseWarning( '', $backgroundImage . ' is empty'  );
					}
					return;
				}

				if ( $type == 'random' ) {
					$my->backgroundImage = $juri->base() . $backgroundImage . '/'  
						. modGoWeatherHelper::selectRandom( $images );
				}
				else {
					// FIXME dynamic
				}
				break;

			default:
				if ( !$backgroundImage ) {
					$backgroundImage = $defaultImgDir . 'clear_sky.jpg';
				}
				else {
					if ( !is_file(JPATH_SITE . $backgroundImage )) {
						if ( $my->debug ) {
							JError::raiseWarning( '', JPATH_SITE . $backgroundImage . ' is not a file'  );
							
						}
					}
				}
				$my->backgroundImage = $backgroundImage;
				break;
			}
		}

		$my->celsius = $params->get( 'temp', 'C' );

		$my->dynamicColors = $params->get( 'temp_color', '1' );

		$my->time24h = $params->get( '24h', '24h' );

		$my->speed = $params->get( 'speed', 'Mps' );

		$my->height = $params->get( 'height', 'Mm' );
		
		for ( $i=0; $i <= 3; $i++ ) {
			$my->showPeriod[ $i ] = $params->get( 'show_periodq' . $i, '1' );
		}
		
		$my->useDefaultCSS = $params->get( 'default_css', '1' );
		
		$my->otherCSS = $params->get( 'other_css', NULL );
		
		return $my;
	}
}
