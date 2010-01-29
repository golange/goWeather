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

$modulePath = $juri->base() . '/modules/mod_goweather/';

$document = &JFactory::getDocument(); 

if( $my->useDefaultCSS ) {
	$document->addStyleSheet( $modulePath . 'css/mod_goweather.css' );
}

if( $my->otherCSS ) {
	$document->addStyleSheet( $my->otherCSS );
}

// Local overrides
$localStyle = '';
$localClass = '';

if( $my->useBorders ){
	$localClass .= 'goWeatherBorder ';
}

$backgroundImage = modGoWeatherHelper::getBackgroundImage( &$params, &$my, &$weather );

if ( $backgroundImage ) {
	$localClass .= 'goWeatherImage ';
	
	$localStyle .= 'background-image : url("' . $backgroundImage . '");';
}

if( $my->backgroundColor != 'inherit' ){
	$localStyle .= 'background-color : ' . $my->backgroundColor . ';';
}

// We need to keep old query string
// FIXME There must be a smarter way to do this?

// Kill current module settings (if any)

$path = $juri->getPath();

$oldQuery = $juri->getQuery( true );

if ( $my->scroll ) {
	unset( $oldQuery[ modGoWeatherHelper::QUERYDAY . $module->id ] );

	$oldQueryDate = $juri->buildQuery( $oldQuery );

	$oldQueryDate = modGoWeatherHelper::fixQuery( &$path, $oldQueryDate );
}

unset( $oldQuery[ modGoWeatherHelper::QUERYID . $module->id ] );

$oldQueryId = $juri->buildQuery( $oldQuery );

$oldQueryId = modGoWeatherHelper::fixQuery( &$path, $oldQueryId );?>

<!-- BEGIN goWeather
     Data from yr.no at <?php echo $weather['header']['fetchedAt'] ?> -->
<center>
<div class="goWeather goWeatherWrapper <?php echo $localClass;?>" style='<?php echo $localStyle;?>'>
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
		$action = "var newLoc='" . $oldQueryId . modGoWeatherHelper::QUERYID . $module->id  
		. "=';with (this){ with (weatherLoc){  top.location=newLoc.concat(value);}}";

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
$downArrow = false;
$skipToDate = $my->firstDay;

if ( $weather ) { 
	do { 
		$upArrow = false;
		
		foreach ( $weather[ 'dates' ] as $currentDay ) {
			$headerDone = false;
			
			if( $skipToDate ) {
		if ( $skipToDate != (int)$currentDay->dayOfMonth ) {
			$upArrow = true;
			continue;
		}
		else {
			$skipToDate = NULL;
		}
	}

	if( $dateCount >= $my->days ) {
		// We have more days in data
		$downArrow = true;
		break;
	}
	
	foreach ( $currentDay->periods as $period ) { 
		if( $my->showPeriod[ $period[ 'period' ] ]){
			if( !$headerDone ) {
				$dateCount++;
				// Date header
				$headerDone = true;
				
				$date = ucfirst( JText::_( $currentDay->dayOfWeek )) . ' ' 
					. $currentDay->dayOfMonth . ' '
					. ucfirst( JText::_($currentDay->month . '_short'));
				
				?><div class="goWeatherDate"><table><tr><?php
					   if ( $upArrow ) {
						   $upArrow = false;
						   $previousDay = date( 'j', 
												strtotime( '-' . $my->days . ' day' ,
														   strtotime( $currentDay->dayOfMonth . ' ' . $currentDay->month)));?>
						   <td class="goWeatherArrow hasTip" title="<?php echo JText::_('Scroll')?>"> <a href="<?php echo $oldQueryDate . modGoWeatherHelper::QUERYDAY . $module->id . '=' . $previousDay;?>"><img src="<?php echo $modulePath;?>images/arrow_up.png" alt="Scroll up"/></a></td><?php
					   }
					   else {
						   ?><td class="goWeatherBlank"></td><?php
							   }
				?><td class="goWeatherDate"><?php echo $date;
				?></td><td class="goWeatherBlank"></td>
						</tr></table></div><table><?php
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
			
			?><td class="goWeatherSymbol hasTip" title="<?php echo $title;?>"><img src="http://fil.nrk.no/yr/grafikk/sym/b38/<?php echo $period['symbolFile']?>.png" alt="<?php echo $title;?>"/></td><?php
				   if ( $period[ 'temperatureChillC' ] === NULL ) {
					   ?><td class="goWeatherTemp"><?php 
				   }
				   else {?>
					   <td class="goWeatherTemp hasTip" title=<?php echo '"' . JText::_("Feels like") 
						   . ' ' . ($period[ 'temperatureChill' . $my->celsius ]) . '&deg;' . $my->celsius . '">';
				   }
			?><font style="color:<?php echo $period['temperatureColor']?>;"><?php
				   echo $period[ 'temperature' . $my->celsius ]
				   ?>&deg;<font style="font-size:smaller;"><?php 
							   echo $my->celsius;
			?></font></font></td>
					<td class="goWeatherWind hasTip" title="<?php echo $period[ 'windSpeed' . $my->speed ];?>" style='background-image : url("<?php echo $period['windMeter']?>");'><img src="<?php echo $period['windArrow'];?>" alt="<?php echo $period[ 'windSpeed' . $my->speed ];?>"/></td>
					</tr><?php	
					}
	}
	if ( $tableOpen ) {
		?></table><?php
			$tableOpen = false;
	}
		} // foreach

		if ( !$skipToDate ) {
			break;
		}
		// Date not found. Could happen if new forecast is loaded while we watch. 
		// Try again from start this time
		$skipToDate = NULL;
	} while (true);
} // if

?>

<div class="goWeatherFooter">
	<table><tr><?php
	if ( $my->scroll and $downArrow ) {
		?><td class="goWeatherArrow hasTip" title="<?php echo JText::_('Scroll')?>"> <a href="<?php 
echo $oldQueryDate . modGoWeatherHelper::QUERYDAY . $module->id . '=' . $currentDay->dayOfMonth;?>"><img src="<?php echo $modulePath;?>images/arrow_down.png" alt="Scroll down"/></a></td>
		<?php 
	}
	else {?>
		<td class="goWeatherBlank"></td><?php	
			}
?>
<!-- Required by yr.no for use -->
<td class="goWeatherLink"><a href="http://www.yr.no/place/<?php echo $my->currentTarget->location . '/' . $my->yrLink;?>" target="_blank">
	<span class="hasTip" title="<?php echo JText::_('Forecast source')?>"><?php 
	echo JText::_( 'Go to' )?> yr.no</span></a></td>
	
    <td class="goWeatherUser"></td>
    </tr></table>
    </div>
	</div>
	</div>
	</center>
<!-- END goWeather -->
