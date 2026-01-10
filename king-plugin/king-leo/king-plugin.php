<?php
/*
	Plugin Name: King LeonardoAI
	Plugin URI: 
	Plugin Description: King AI image creator plugin
	Plugin Version: 3.6
	Plugin Date: 2023-01-24
	Plugin Author: KingThemes
	Plugin Author URI: 
	Plugin License: GPLv2
	Plugin Minimum KingMEDIA Version: 1
	Plugin Update Check URI: 
*/

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../../');
		exit;
	}
qa_register_plugin_layer('king-leo-layer.php', 'KingLeo Layer');

qa_register_plugin_module('page', 'king-pricing.php', 'king_pricing', 'kingpricing');

qa_register_plugin_module('page', 'king-favs.php', 'king_favs', 'kingfavs');

qa_register_plugin_module('module', 'king-leo-options.php', 'king_leo_op', 'King Leo Ai');
qa_register_plugin_phrases('kingai-lang-*.php', 'kingai_lang');


/*
	Omit PHP closing tag to help avoid accidental output
*/