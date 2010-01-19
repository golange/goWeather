<?php
/*
 * @package	Joomla 1.5
 * @copyright	Copyright (C) 2010 Thomas Lange. All rights reserved.
 * @license	GNU/GPLv2
 * Display weather data
 */

defined('_JEXEC') or die('Restricted access');	

// include mootools tooltip
JHTML::_('behavior.tooltip');

$juri = &JURI::getInstance();

$cssPath = $juri->base() . '/modules/mod_goweather/css/';

$document = &JFactory::getDocument(); 

if( $my->useDefaultCSS ) {
	$document->addStyleSheet( $cssPath . 'mod_goweather.css' );
}

if( $my->otherCSS ) {
	$document->addStyleSheet( $cssPath . $my->otherCSS );
}

// Local overrides
$localStyle = '';
$localClass = '';

if( $my->useBorders ){
	$localClass .= 'goWeatherBorder ';
}

if ( $my->useBackgroundImage ) {
	$localClass .= 'goWeatherImage ';
}

if( $my->backgroundColor != 'inherit' ){
	$localStyle .= "background-color : $my->backgroundColor;";
}?>

<!-- BEGIN goWeather
     Data from yr.no at <?php echo $weather['header']['fetchedAt'] ?> -->
<center>
<div class="goWeather goWeatherWrapper <?php echo $localClass;?>" style="<?php echo $localStyle;?>">
<!-- Main header -->
<?php
if ( $my->showName ) {
	if ( count( $my->targets ) < 2 ) {
		// No choices
		?><div class="goWeatherHead"><p class="goWeatherTitle"><?php echo $my->currentTarget->name;?></p></div><?php
	}
	else {
		?><div class="hasTip goWeatherHead" title="<?php echo JText::_( 'Choose location' );?>">
		<form method="get" action=""><?php
		// We need to keep old query string
		// FIXME There must be a smarter way to do this?
		
		$oldQuery = $juri->getQuery( true );
		
		$thisQuery = modGoWeatherHelper::QUERYID . $module->id;
		
		// Kill current module setting (if any)
		unset( $oldQuery[ $thisQuery ] );
			
		$oldQuery = $juri->buildQuery( $oldQuery );

		if( $oldQuery ) {
			$newQuery = "?$oldQuery" . '&' . "$thisQuery=";
		}
		else {
			$newQuery = "?$thisQuery=";
		}

		$action = "var newLoc='$newQuery';with (this){ with (weatherLoc){  top.location=newLoc.concat(value);}}";

		?><select name="weatherLoc" onchange="<?php echo $action;?>">
		<?php
		$optGroupOpen = false;
		
		foreach ( $my->targets as $target ) {
			if ( $target->location == 'group' ) {
				if ( $optGroupOpen ) {
					?></optgroup><?php
						}
				$optGroupOpen = true;
				?><optgroup label="<?php echo $target->name?>"><?php
					   }
			else {
				?><option value="<?php echo $target->id?>"<?php  
					if ( $target->id  == $my->currentTarget->id) {
						?> selected="selected"<?php
					}
				echo '>' . $target->name;?></option><?php
													 }
		}
		if ( $optGroupOpen ) {
			?></optgroup><?php
		}
		?></select>
		<noscript>
		<center><input type="submit" value="<?php echo JText::_( 'Fetch weather' );?>" /></center>
		</noscript>
		</form>
		</div><?php
	}
}?>

<!-- Main header end -->
<div class="goWeatherBody">
<?php
$dateCount = 0;
$tableOpen = false;

if ( $weather ) foreach ( $weather[ 'dates' ] as $currentDay ) {
	$headerDone = false;
	if( $dateCount >= $my->days ) {
		break;
	}
	
	foreach ( $currentDay as $period ) { 
		if( $my->showPeriod[ $period[ 'period' ] ]){
			if( !$headerDone ) {
				$dateCount++;
				// Date header
				$headerDone = true;
				
				$date = ucfirst( JText::_( $period[ 'dayOfWeek' ])) . ' ' 
					. $period[ 'dayOfMonth' ] . ' '
					. ucfirst( JText::_($period[ 'month' ] . '_short'));
				
				?><p class="goWeatherDate"><?php echo $date;?></p><table><?php
																	$tableOpen = true;
			}?>
				
				<tr><td class="goWeatherTime hasTip" title=<?php echo '"' 
					 . JText::_('Forecast') . ' ' . $period[ 'period' . $my->time24h ] . '">' 
					 . $period[ 'middle' . $my->time24h ] . "</td>\n";
			
			$weatherName = JText::_($period[ 'symbolName' ]);
			
			$title = $weatherName;
			
			$precipitation = $period[ 'precipitation' . $my->height ]; 
			
			if( $precipitation ){
				$title .= ', ' . $precipitation;
			}
			
			?><td class="goWeatherSymbol hasTip" title="<?php echo $title;?>"><img src="http://fil.nrk.no/yr/grafikk/sym/b38/<?php echo $period['symbolFile']?>.png" alt="<?php echo $title;?>"/></td>
				   <td class="goWeatherTemp hasTip" title=<?php echo '"' . JText::_("Feels like") 
				   . ' ' . ($period[ 'temperatureChill' . $my->celsius ]) . '&deg;' . $my->celsius . '">';
			
			?><font style="color:<?php echo $period['temperatureColor']?>;"><?php
				   echo $period[ 'temperature' . $my->celsius ]
				   ?>&deg;<font style="font-size:smaller;"><?php 
							   echo $my->celsius;
			?></font></font></td>
					<td class="goWeatherWind hasTip" title="<?php echo $period[ 'windSpeed' . $my->speed ];?>"><img src="<?php echo $period['windArrow'];?>" alt="<?php echo $period[ 'windSpeed' . $my->speed ];?>"/></td>
					</tr><?php	
					}
	}
	if ( $tableOpen ) {
		?></table><?php
			$tableOpen = false;
	}
}
?>

<!-- Required by yr.no for use -->
<p class="goWeatherFooter">
	<a href="http://www.yr.no/place/<?php echo $my->currentTarget->location . '/' . $my->yrLink;?>" target="_blank">
	<span class="hasTip" title="<?php echo JText::_('Forecast source')?>"><?php 
	echo JText::_( 'Go to' )?> yr.no</span></a></p>
	</div>
	</div>
	</center>
<!-- END goWeather -->
