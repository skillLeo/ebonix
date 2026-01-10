<?php
	class qa_html_theme_layer extends qa_html_theme_base {
		
	// theme replacement functions
		function king_js() {
			qa_html_theme_base::king_js();
			if ( qa_opt('king_leo_enable') ) {
				$this->output('<script src="'.QA_HTML_THEME_LAYER_URLTOROOT.'king-leo.js"></script>');
			}
		}


	}
