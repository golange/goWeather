<?php
  /**
   * @package	Joomla 1.5
   * @copyright	Copyright (C) 2010 Thomas Lange. All rights reserved.
   * @license	GNU/GPLv2
   * Parse and display yr.no weather data
   */

defined('_JEXEC') or die('Restricted access');

// Include the helper functions only once
require_once ( dirname(__FILE__) . DS . 'helper.php' );

$my = modGoWeatherHelper::init( $params, $module );

if ( $my ) {
	if ( !$my->debug ){
		if (!is_writable( JPATH_BASE . DS . 'cache' )) {
			JError::raiseWarning( '', JText::_( 'Cache directory unwritable' ));
		}
		
		$cache = & JFactory::getCache( 'mod_goweather' );
		$cache->setCaching( 1 ); // Always enable caching of network data!
		$cache->setLifeTime( $params->get( 'cache_time', 1200 ));

		$weather = $cache->call( array( 'modGoWeatherHelper', 'getWeather' ), &$my );

		// Restore cache setting 
		$config = &JFactory::getConfig(); 
		$cache->setCaching( $config->getValue( 'config.caching' ));
	}
	else {
		// Bypass short lived cache when debugging!
		$weather = modGoWeatherHelper::getWeather( &$my );
	}
}

if ( !$weather and $my->currentTarget ){
	JError::raiseNotice( '', JText::_( 'Weather forecast for' ) . ' ' 
						 . $my->currentTarget->name . ' ' 
						 . strtolower( JText::_('currently unavailable' )));
}

if ( $my->currentTarget ) {
	$path = JModuleHelper::getLayoutPath( 'mod_goweather' );
	require( $path );
}
?>