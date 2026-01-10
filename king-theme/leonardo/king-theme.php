<?php
class qa_html_theme extends qa_html_theme_base {
	public function html()
	{
		$this->output(
			'<html lang="en-US" class="king-lnight">',
			'<!-- Created by KingMedia -->'
		);
		$this->head();
		$this->body();
		$this->output(
			'<!-- Created by KingMedia with love <3 -->',
			'</html>'
		);
	}
	public function head_script()
	{
		if (isset($this->content['script'])) {
			foreach ($this->content['script'] as $scriptline)
				$this->output_raw($scriptline);
		}
		$this->output( '<script src="' . $this->rooturl . 'js/night.js"></script>' );
	}
	public function body_footer()
	{
		if (isset($this->content['body_footer'])) {
			$this->output_raw($this->content['body_footer']);
		}
		$this->output( '<link rel="preconnect" href="https://fonts.googleapis.com">' );
		$this->output( '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' );
		$this->output( '<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600&display=swap" rel="stylesheet">' );

		$this->king_js_codes();
		
	}
	public function king_js() {
		$this->output( '<script src="' . $this->rooturl . 'js/main.min.js"></script>' );
		$this->output( '<script src="' . $this->rooturl . 'js/bootstrap.min.js"></script>' );

		if ( 'home' == $this->template || 'hot' == $this->template || 'search' == $this->template || 'updates' == $this->template || 'user-questions' == $this->template || 'favorites' == $this->template || 'qa' == $this->template || 'tag' == $this->template || 'type' == $this->template || 'reactions' == $this->template || 'aifavs' == $this->template || 'pposts' == $this->template || 'private-posts' == $this->template ) {
			$this->output( '<script src="' . $this->rooturl . 'js/jquery-ias.min.js"></script>' );
			$this->output( '<script src="' . $this->rooturl . 'js/masonry.pkgd.min.js"></script>' );
		}

	}

	public function body_content() {
		$this->body_prefix();
		$this->notices();
		$this->body_header();
		

		$this->output( '<DIV class="king-body" id="lmenu">' );
		$this->header();
		if ('home' == $this->template) {
		$this->output( '<div class="king-body-search-up">' );
		$this->output( '<div class="king-body-search">' );
		$this->output( '<h1>' . qa_opt('kingh_title') . '</h1>' );
		$this->output( '<h3>' . qa_opt('kingh_desc') . '</h3>' );
		$this->nav_user_search();
		$this->output( '</div>' );
		$this->featured();
		$this->output( '</div>' );
		} else {
			$this->nav_user_search();
		}
		if ( isset( $this->content['profile'] ) ) {
			$this->profile_page();
		}
		$this->h_title();


		$this->output( '<DIV id="king-body-wrapper" class="king-body-in">' );
		$this->widgets( 'full', 'top' );
		
		$this->widgets( 'full', 'high' );
		$this->output( '<div class="leo-nav">' );
		$this->nav( 'sub' );
		if ( 'home' == $this->template || 'hot' == $this->template || 'search' == $this->template || 'updates' == $this->template || 'user-questions' == $this->template || 'favorites' == $this->template || 'qa' == $this->template || 'tag' == $this->template || 'type' == $this->template || 'reactions' == $this->template || 'aifavs' == $this->template || 'pposts' == $this->template ) {
			$this->output( '<div class="leo-range">' );
			$this->output( '<span id="range-value">4</span><i class="fa-solid fa-square"></i>' );
			$this->output( '<input id="myRange" class="leo-slider" type="range" min="2" max="10" step="1" value="4" >' );
			$this->output( '<i class="fa-solid fa-grip"></i></div>' );
		}
		$this->output( '</div>' );
		$this->nav( 'kingsub' );
		$this->widgets( 'full', 'low' );
		$this->output( '<div id="container">' );
		$this->main();
		$this->output( '</div>' );
		$this->footer();
		$this->output( '</DIV>' );
		
		$this->body_suffix();
		$this->output( '</DIV>' );
	}


	public function main() {
		$content = $this->content;
		$hidden = isset( $content['hidden'] ) ? ' king-main-hidden' : '';
		$class = isset( $content['class'] ) ? $content['class'] : ' one-page';
		$q_view = isset($content['q_view']) ? $content['q_view'] : '';
		$this->widgets( 'main', 'top' );
		$this->output( '<DIV CLASS="king-main' . $class . $hidden.'">' );
		if ( $q_view ) {
			$this->main_up($q_view);

		} else {
			$this->output( '<DIV CLASS="king-main-in">' );
			$this->widgets( 'main', 'high' );
			$this->main_parts( $content );
			$this->page_links();
			$this->output( '</div> <!-- king-main-in -->' );
			if ( isset($content['sside']) ) {
				$this->sidepanel();
			}
		}
		

		$this->output( '</DIV> <!-- king-main -->' );
		
		$this->suggest_next();
		$this->widgets('main', 'bottom');
	}


	public function main_up($q_view) {
			$content = $this->content;
			$text2 = $q_view['raw']['postformat'];
			$nsfw  = $q_view['raw']['nsfw'];
			$class= ' king-naip';
			if ( null !== $nsfw && ! qa_is_logged_in() ) {
				$this->output( '<DIV CLASS="king-video">' );
				$this->output( '<span class="king-nsfw-post"><p><i class="fas fa-mask fa-2x"></i></p>' . qa_lang_html( 'misc/nsfw_post' ) . '</span>' );
				$this->output( '</DIV>' );
				$class= ' king-aip';
			} elseif ( 'V' == $text2 ) {
				$this->output( '<DIV CLASS="king-video-in">' );
				$this->output( '<DIV CLASS="king-video">' );
				$this->q_view_extra( $q_view );
				$this->output( '</DIV>' );
				$this->output( '</DIV>' );
				$class= ' king-aip';
			} elseif ( 'music' == $text2 ) {
				$this->output( '<DIV CLASS="king-video-in">' );
				$this->output( '<DIV CLASS="king-video">' );
				$this->music_view( $q_view );
				$this->output( '</DIV>' );
				$this->output( '</DIV>' );
				$class= ' king-aip';
			} elseif ( 'I' == $text2 ) {
				$this->output( '<DIV CLASS="king-video-in">' );
				$this->output( '<DIV CLASS="king-video">' );
				$this->q_view_extra( $q_view );
				$this->output( '</DIV>' );
				$this->output( '</DIV>' );
				$class= ' king-aip';
			}
			$this->output( '<DIV CLASS="king-main-leo'.qa_html($class).'">' );
			$this->output( '<DIV CLASS="king-main-in">' );
			$this->widgets( 'main', 'high' );
			$this->main_parts( $content );
			$this->page_links();
			$this->output( '</div> <!-- king-main-in -->' );
			if ( isset($content['sside']) ) {
				$this->sidepanel();
			}
			$this->output( '</DIV>' );
			$this->viewtop();

	}

	public function q_view( $q_view ) {

		$nsfw  = $q_view['raw']['nsfw'];

		if ( ! empty( $q_view ) ) {

			
			if ( null == $nsfw || qa_is_logged_in() ) {
				$this->output( '<DIV CLASS="king-q-view' . ( @$q_view['hidden'] ? ' king-q-view-hidden' : '' ) . rtrim( ' ' . @$q_view['classes'] ) . '"' . rtrim( ' ' . @$q_view['tags'] ) . '>' );
				$this->a_count( $q_view );
				$this->output( '<DIV CLASS="rightview">' );
				$this->post_title($q_view);
				$this->postcontent($q_view);
				$this->post_tags( $q_view, 'king-q-view' );
				$this->output( '<DIV CLASS="postmeta">' );
				$this->view_count( $q_view );
				$this->q_view_buttons($q_view);
				$this->post_meta_when( $q_view, 'meta' );
				$this->output( '</DIV>' );
				if ( qa_opt( 'show_ad_post_below' ) && king_add_free_mode() ) {
					$this->output( '<div class="ad-below">' );
					$this->output( '' . qa_opt( 'ad_post_below' ) . '' );
					$this->output( '</div>' );
				}

				$this->output( '</DIV>' );

				$this->output( '</DIV> <!-- END king-q-view -->', '' );
				$this->output( '<div class="prev-next">' );
				$this->get_next_q($q_view);
				$this->get_prev_q($q_view);
				$this->output( '</div>' );
			}
			$this->socialshare($q_view);

			$this->pboxes( $q_view );
			$this->output( '<div id="commentmodal" class="king-modal-login">' );
			$this->output( '<div class="king-modal-content">' );
			$this->maincom( $q_view );
			$this->output( '</div>' );
			$this->output( '</div>' );

		}
	}
	public function q_view_extra( $q_view ) {
		if ( ! empty( $q_view['extra'] ) ) {
			require_once QA_INCLUDE_DIR . 'king-app-video.php';
			$extraz = $q_view['extra']['content'];
			$extras = @unserialize( $extraz );

			if ( $extras ) {
				$this->output('<div class="king-gallery owl-carousel">');
				foreach ( $extras as $extra ) {
					$text2 = king_get_uploads( $extra );
					$this->output('<div class="king-gallery-img">');
					$this->output('<div class="king-gallery-imgs">');
					$this->output( '<a href="' . $text2['furl'] . '">');
					$this->output( '<img class="gallery-img king-lazy" width="' . $text2['width'] . '" height="' . $text2['height'] . '" data-king-img-src="' . $text2['furl'] . '" alt=""/>' );
					
					$this->output( '</a>');
					$this->output('</div>');
					if (qa_opt('eidown')) {
						$this->output( '<a href="' . $text2['furl'] . '" class="aidown" download><button><i class="fa-solid fa-download"></i></button></a>');
					}
					
					$this->output('</div>');
				}
				$this->output('</div>');
			} elseif ( is_numeric( $extraz ) ) {
				$vidurl = king_get_uploads( $extraz );
				$thumb  = $this->content['description'];
				$poster = king_get_uploads( $thumb );
				$this->output('<video id="my-video" class="video-js vjs-theme-forest" controls preload="auto" autoplay width="960" height="540" data-setup="{}" poster="' . $poster['furl'] . '" >');
				$this->output('<source src="' . $vidurl['furl'] . '" type="video/mp4" />');
				$this->output('</video>');
			} else {
				if ( $extraz ) {
					$this->output_raw( $extraz = embed_replace( $extraz ) );
				}
			}
		}
	}
	public function viewtop()
	{
		$q_view   = @$this->content['q_view'];
		$favorite = @$this->content['favorite'];

		if ($this->template == 'question') {
			$this->output('<DIV CLASS="share-bar scrolled-up" id="share-bar">');

			if (isset($q_view['main_form_tags'])) {
				$this->output('<FORM ' . $q_view['main_form_tags'] . '>');
			}

			$this->voting($q_view);
			if (isset($q_view['main_form_tags'])) {
				$this->form_hidden_elements(@$q_view['voting_form_hidden']);
				$this->output('</FORM>');
			}
			if (isset($favorite)) {
				$this->output('<FORM ' . $favorite['form_tags'] . '>');
			}

			$this->favorite();
			if (isset($favorite)) {
				$this->form_hidden_elements(@$favorite['form_hidden']);
				$this->output('</FORM>');
			}

			$this->output('<div class="share-link" data-toggle="modal" data-target="#sharemodal" role="button" ><i data-toggle="tooltip" data-placement="top" class="fa-regular fa-paper-plane" title="' . qa_lang_html('misc/king_share') . '"></i></div>');
			if (qa_get_logged_in_level()>=QA_USER_LEVEL_ADMIN) {
				if ($q_view['raw']['featured']) {
					$fclass=' selected';
				} else {
					$fclass=' not-selected';
				}
				$this->output('<div class="share-link addfeatured'.qa_html($fclass).'" onclick="return featuredclick(this);" data-pid="'.qa_html($q_view['raw']['postid']).'" data-toggle="tooltip" data-placement="top" title="' . qa_lang_html('misc/featured') . '"><i class="fas fa-star"></i></div>');
			}
			if (qa_opt('enable_bookmark')) {
				$this->output( post_bookmark( $q_view['raw']['postid'], 'share-link' ) );
			}
			
			$this->output('<div class="share-link" data-toggle="modal" data-target="#commentmodal" role="button" ><i data-toggle="tooltip" data-placement="top" class="fa-regular fa-comment" title="' . qa_lang_html( 'misc/postcomments' ) . '"></i></div>');
			$this->output('</DIV>');

		}


	}
	public function header() {
		$this->output( '<header CLASS="king-headerf" id="king-header">' );
		$this->output( '<DIV CLASS="king-header">' );
		$this->header_left();
		$this->header_middle();
		$this->header_right();
		$this->output( '</DIV>' );
		$this->leftmenu();

		$this->output( '</header>' );
				



		if ( isset( $this->content['error'] ) ) {
			$this->error( @$this->content['error'] );
		}
	}
	public function search()
	{
		$search = $this->content['search'];

		$this->output('<div class="king-search">');
		$this->output('<div class="king-search-in">');
		$this->output('<form ' . qa_sanitize_html($search['form_tags']) . '>',
			qa_sanitize_html($search['form_extra'])
		);

		$this->search_field($search);
		$this->search_button($search);

		$this->output('</form>');
		$populartags=qa_db_single_select(qa_db_popular_tags_selectspec(0, 5));
		$this->output('<div class="search-disc">');
		$this->output('<h3>'.qa_lang_html('misc/discover').'</h3>');
		foreach ($populartags as $tag => $count) {
			$this->output('<a class="disc-tags" href="'.qa_path_html('tag/'.$tag).'" >'.qa_html($tag).'</a>');
		}
		$this->output('</div>');
		$this->output('<div id="king_live_results" class="liveresults">');
		$this->output('</div>');
		$this->output('</div>');
		$this->output('</div>');
	}
	public function search_field($search)
	{
		$this->output('<input type="text" '.$search['field_tags'].' value="'.@$search['value'].'" class="king-search-field" placeholder="'.qa_lang_html('misc/search').'" autocomplete="off"/>');
	}
	public function h_title() {
		if (isset( $this->content['header'] ) ) {
			$this->output( '<div class="head-title">' );
			$this->output( $this->content['header'] );
			$this->output( '</div>' );
		}
	}
	public function header_left() {
		$this->output( '<div class="header-left">' );
		$this->output( '<button id="ltoggle"></button>' );
		$this->logo();
		$this->output( '</div>' );
	}

	public function header_middle() {
		$this->output( '<div class="header-middle">' );
		$this->nav( 'head' );

		$this->output('</div>');
	}

	public function header_right() {
		$this->output( '<DIV CLASS="header-right">' );
		$this->output( '<ul>' );

		if ( qa_is_logged_in() ) {
			$this->userpanel();
		}

		if (  ( qa_user_maximum_permit_error( 'permit_post_q' ) != 'level' ) && !qa_opt('hsubmit') ) {
			$this->kingsubmit();
		} else {
			$this->kingsubmitai();		
		}
		
			
		$this->output( '<li class="search-button"><span data-toggle="dropdown" data-target=".king-search" aria-expanded="false" class="search-toggle"><i class="fas fa-search fa-lg"></i></span></li>' );
		$this->output( '</ul>' );
		$this->output( '</DIV>' );
	}

public function kingsubmitai() {
		if ( qa_opt( 'king_leo_enable' ) && qa_opt( 'enable_aivideo' ) ) {
			$this->output( '<li>' );
			$this->output( '<div class="king-submit">' );

			$this->output( '<span class="aisubmit" data-toggle="dropdown" data-target=".king-submit" aria-expanded="false" role="button"><i class="fa-solid fa-feather-pointed"></i>'.qa_lang('kingai_lang/aisubmit').'</span>' );
			$this->output( '<div class="king-dropdown2">' );
			if ( qa_opt( 'king_leo_enable' ) ) {
				$this->output( '<a href="' . qa_path_html( 'submitai' ) . '" class="kingaddai"><i class="fa-solid fa-atom"></i> ' . qa_lang_html( 'misc/king_ai' ) . '</a>' );
			}
			if ( qa_opt( 'enable_aivideo' ) ) {
				$this->output( '<a href="' . qa_path_html( 'videoai' ) . '" class="kingaddai"><i class="fa-solid fa-atom"></i> ' . qa_lang_html( 'misc/king_aivid' ) . '</a>' );
			}	
			$this->output( '</div>' );
			$this->output( '</div>' );
			$this->output( '</li>' );
		} else {
			$this->output( '<li>' );
			$this->output( '<a class="aisubmit" href="' . ( qa_opt( 'enable_aivideo' ) ? qa_path_html( 'videoai' ) : qa_path_html( 'submitai' ) ) . '"><i class="fa-solid fa-feather-pointed"></i> ' . qa_lang_html( 'kingai_lang/aisubmit' ) . '</a>' );
			$this->output( '</li>' );
		}
	}


	public function userpanel() {
		$userid = qa_get_logged_in_userid();
		if (qa_opt('enable_bookmark')) {
			require_once QA_INCLUDE_DIR . 'king-db/metas.php';
			
			$rlposts  = qa_db_usermeta_get( $userid, 'bookmarks' );
			$result = $rlposts ? unserialize( $rlposts ) : '';
			$count   = ! empty( $result ) ? count( $result ) : 0;
		}


		if (qa_opt('enable_bookmark')) {
			$this->output( '<li>' );
			$this->output( '<div class="king-rlater" data-toggle="modal" data-target="#rlatermodal" onclick="return bookmodal();">
						<i class="fa-solid fa-bookmark"></i>
						<input type="hidden" class="king-bmcountin" id="bcount" value="' . qa_html( $count ) . '" />
						<span class="king-bmcount" id="bcounter">' . qa_html( $count ) . '</span>
					</div>' );
			$this->output( '</li>' );
		}
	}
	public function leftmenu() {
		$this->output( '<div class="leftmenu kingscroll">' );

		$this->output('<button type="button" class="king-left-close" data-target=".leftcats, .leftmenu" data-toggle="dropdown"  aria-expanded="false"></button>');
		$this->nav_main_sub();
		$this->output( '<div class="usrleft">' );
		if ( ! qa_is_logged_in() ) {
			
			$this->output( '<div class="reglink" data-toggle="modal" data-target="#loginmodal" role="button" title="' . qa_lang_html( 'main/nav_login' ) . '"><i class="fa-solid fa-user"></i></div>' );
			
		} else {
			$this->output( '<div class="king-havatar" data-toggle="dropdown" data-target=".king-dropdown, .leftmenu" aria-expanded="false" >' );
			$this->output( get_avatar( qa_get_logged_in_user_field('avatarblobid'), 40 ) );
			$this->output( '</div>' );
		}
		$this->output( '</div>' );
		$this->output( '<input type="checkbox" id="king-lnight" class="hide" /><label for="king-lnight" class="king-nightb"><i class="fa-solid fa-sun"></i><i class="fa-solid fa-moon"></i></label>' );
		$this->output( '</div>' );
		$this->output( '<div class="king-mega-menu leftcats">' );
		if ( qa_using_categories() ) {
			$this->king_cats();
		}
		$this->nav('headmenu');
		$this->output('</div>');
		if ( qa_is_logged_in() ) {
		$userid = qa_get_logged_in_userid();
		$this->output( '<div class="king-dropdown king-mega-menu">' );
		$this->output( '<div>' );
		$this->output( '<a href="' . qa_path_html('user/'.qa_get_logged_in_user_field('handle')) . '" ><h3>' . qa_get_logged_in_user_field('handle') . '</h3></a>' );
		$this->output( '<span class="user-box-point"><strong>' . qa_html( number_format( qa_get_logged_in_user_field('points') ) ) . '</strong> ' . qa_lang_html( 'admin/points_title' ) . '</span>' );
		$this->output(membership_badge($userid));

		$this->nav( 'user' );
		$this->output( '</div>' );
		if ( qa_opt('ailimits') || qa_opt('ulimits') ) {
			require_once QA_INCLUDE_DIR.'king-db/metas.php';
			$this->output( '<div class="ailimit">' );
			$mp  = qa_db_usermeta_get( $userid, 'membership_plan' );
			$pl = null;
			if ($mp) {
				$pl = (INT)qa_opt('plan_'.$mp.'_lmt');
			} elseif (qa_opt('ulimits')) {
				$pl = (INT)qa_opt('ulimit');
			}
			$alm = (INT)qa_db_usermeta_get( $userid, 'ailmt' );
			if ($pl) {			
				$perc = ( $alm*100 ) / $pl;
				$this->output( '<h5>'.qa_lang('kingai_lang/credits').'</h5>' );
				$this->output( '<h4>'.$alm.' / '.$pl.'</h4>' );
				$this->output( '<div class="ailimits"><span style="width:'.$perc.'%;"></span></div>' );
			}
			$this->output( '</div>' );
		}
		$this->output( '</div>' );
		}

	}

	public function nav_main_sub() {
		$this->output( '<DIV CLASS="king-nav-main">' );
		$this->nav( 'main' );
		$this->output( '</DIV>' );
	}

	public function profile_page() {
		$handle = qa_request_part( 1 );

		if ( ! strlen( (string)$handle ) ) {
			$handle = qa_get_logged_in_handle();
		}

		$user = qa_db_select_with_pending(
			qa_db_user_account_selectspec( $handle, false )
		);

		$this->output( get_user_html( $user, '1200', 'king-profile', '140' ) );
	}



	/**
	 * @param $q_items
	 */
	public function q_list_items( $q_items ) {

		$this->output( '<div class="container">' );
		foreach ( $q_items as $q_item ) {
			$this->q_list_item( $q_item );
		}
		$this->output( '</div>' );

	}

	/**
	 * @param $q_item
	 */
	public function q_list_item( $q_item ) {
		$format     = $q_item['raw']['postformat'];
		$postformat = '';
		$postc      = '';
		$shomag     = true;

		if ( 'V' == $format ) {
			$postformat = '<a class="king-post-format" href="' . qa_path_html( 'type' ) . '"><i class="fa-solid fa-play"></i> ' . qa_lang_html( 'main/video' ) . '</a>';
			$postc      = ' king-class-video';
		} elseif ( 'I' == $format ) {
			$postformat = '<a class="king-post-format" href="' . qa_path_html( 'type', array( 'by' => 'images' ) ) . '"><i class="fas fa-image"></i> ' . qa_lang_html( 'main/image' ) . '</a>';
			$postc      = ' king-class-image';
		} elseif ( 'N' == $format ) {
			$postformat = '<a class="king-post-format" href="' . qa_path_html( 'type', array( 'by' => 'news' ) ) . '"><i class="fas fa-newspaper"></i> ' . qa_lang_html( 'main/news' ) . '</a>';
			$postc      = ' king-class-news';
		} elseif ( 'poll' == $format ) {
			$postformat = '<a class="king-post-format" href="' . qa_path_html( 'type', array( 'by' => 'poll' ) ) . '"><i class="fas fa-align-left"></i> ' . qa_lang_html( 'main/poll' ) . '</a>';
			$postc      = ' king-class-poll';
		} elseif ( 'list' == $format ) {
			$postformat = '<a class="king-post-format" href="' . qa_path_html( 'type', array( 'by' => 'list' ) ) . '"><i class="fas fa-bars"></i> ' . qa_lang_html( 'main/list' ) . '</a>';
			$postc      = ' king-class-list';
		} elseif ( 'trivia' == $format ) {
			$postformat = '<a class="king-post-format" href="' . qa_path_html( 'type', array( 'by' => 'trivia' ) ) . '"><i class="fas fa-times"></i> ' . qa_lang_html( 'main/trivia' ) . '</a>';
			$postc      = ' king-class-trivia';
		} elseif ( 'music' == $format ) {
			$postformat = '<a class="king-post-format" href="' . qa_path_html( 'type', array( 'by' => 'music' ) ) . '"><i class="fas fa-headphones-alt"></i> ' . qa_lang_html( 'main/music' ) . '</a>';
			$shomag     = false;
			$postc      = ' king-class-music';

			if ( $q_item['ext'] ) {
				$shomag = true;
			}
		}

		$this->output( '<div class="box king-q-list-item' . rtrim( ' ' . @$q_item['classes'] ) . '' . $postc . '" ' . @$q_item['tags'] . '>' );
		
		$this->output( '<div class="king-post-upbtn">' );
		$this->output( $postformat );
		if ( $shomag ) {
			$this->output( '<a href="' . $q_item['url'] . '" class="ajax-popup-link magnefic-button mgbutton" data-toggle="tooltip" data-placement="bottom" title="' . qa_lang_html( 'misc/king_qview' ) . '"><i class="fas fa-search"></i></a>' );
		} else {
			$this->output( '<a href="' . $q_item['url'] . '" class="king-listen magnefic-button mgbutton" data-toggle="tooltip" data-placement="bottom" title="' . qa_lang_html( 'main/listen' ) . '"><i class="fa-solid fa-headphones"></i></a>' );
		}	
		if (qa_opt('enable_bookmark')) {
			$this->output( post_bookmark( $q_item['raw']['postid'] ) );
		}
		$this->output( '<a href="' . $q_item['url'] . '" class="ajax-popup-share magnefic-button" data-toggle="tooltip" data-placement="bottom" title="' . qa_lang_html( 'misc/king_share' ) . '"><i class="fas fa-share-alt"></i></a>' );
		$this->output( '</div>' );

		$this->q_item_main( $q_item, $format );
		$this->output( '</div>' );
	}

	/**
	 * @param $q_item
	 */
	public function q_item_main( $q_item, $postformat = null ) {
		$this->output( '<div class="king-q-item-main">' );
		$this->q_item_content( $q_item, $postformat );
		$this->output( '</div>' );
	}

	/**
	 * @param $q_item
	 */
	public function q_item_content( $q_item, $postformat = null ) {
		$text = $q_item['raw']['content'];
		$nsfw = $q_item['raw']['nsfw'];
		if ( $postformat === 'V') {
			$extra  = qa_db_postmeta_get( $q_item['raw']['postid'], 'qa_q_extra' );
		} else {
			$extra = null;
		}
		if ( null !== $nsfw && ! qa_is_logged_in() ) {
			$this->output( '<a href="' . $q_item['url'] . '" class="item-a"><span class="king-nsfw-post"><p><i class="fas fa-mask fa-2x"></i></p>' . qa_lang_html( 'misc/nsfw_post' ) . '</span></a>' );
		} elseif ( ! empty( $text ) ) {
			$text2 = king_get_uploads( $text );
			$this->output( '<A class="item-a" HREF="' . $q_item['url'] . '">');
			if ( $postformat === 'V' && is_numeric( $extra ) ) {
				$this->output( '<A class="item-a king-pvideo" HREF="' . $q_item['url'] . '">');
				$this->output_raw( '<span class="post-featured-img"><img class="item-img king-lazy" width="' . $text2['width'] . '" height="' . $text2['height'] . '" data-king-img-src="' . $text2['furl'] . '" alt=""/></span>' );
				$vidurl = king_get_uploads( $extra );
				$this->output( '<video class="king-avideo" autoplay loop muted playsinline width="'. qa_html($text2['width']) . '"
            height="'. qa_html($text2['height']) . '">
            <source data-src="' . $vidurl['furl'] . '" type="video/mp4">
            </source>
        </video>' );

			} else {
				$this->output( '<A class="item-a" HREF="' . $q_item['url'] . '">');
				$this->output_raw( '<span class="post-featured-img"><img class="item-img king-lazy" width="' . $text2['width'] . '" height="' . $text2['height'] . '" data-king-img-src="' . $text2['furl'] . '" alt=""/></span>' );

			}
			
			
			$this->output( '</A>' );
		} else {
			$this->output( '<a href="' . $q_item['url'] . '" class="king-nothumb"></a>' );
		}
	}

	public function post_title( $q_view ) {
		$this->post_meta_where( $q_view, 'metah' );
		$this->output( '<DIV CLASS="pheader">' );
		$this->output( '<H1>' );
		$this->title();
		$this->output( '</H1>' );
		$this->output( '</DIV>' );
	}

	public function get_prev_q($q_view) {

		$myurl = $q_view['raw']['postid'];
		$query_p = "SELECT *
				FROM ^posts
				WHERE postid < $myurl
				AND type='Q'
				ORDER BY postid DESC
				LIMIT 1";

		$next_link = qa_db_read_one_assoc( qa_db_query_sub( $query_p ), true );
		if ($next_link) {
		$title = $next_link['title'];
		$pid   = $next_link['postid'];
		$cont = king_get_uploads( $next_link['content'] );
		$this->output( '<A HREF="' . qa_q_path_html( $pid, $title ) . '" CLASS="king-prev-q"><div class="pnimg" style="background-image:url(' . qa_html( isset( $cont['furl'] ) ? $cont['furl'] : '' ) . ');" ></div><span>' . $title . ' <i class="fas fa-angle-right"></i></span></A>' );
		}

	}

	public function get_next_q($q_view) {

		$myurl = $q_view['raw']['postid'];
		$query_n = "SELECT *
				FROM ^posts
				WHERE postid > $myurl
				AND type='Q'
				ORDER BY postid ASC
				LIMIT 1";

		$next_link = qa_db_read_one_assoc( qa_db_query_sub( $query_n ), true );
		if ($next_link) {
		$cont = king_get_uploads( $next_link['content'] );
		$title = $next_link['title'];
		$pid   = $next_link['postid'];
		$this->output( '<A HREF="' . qa_q_path_html( $pid, $title ) . '" CLASS="king-next-q"><div class="pnimg" style="background-image:url(' . qa_html( isset( $cont['furl'] ) ? $cont['furl'] : '' ) . ');" ></div><span><i class="fas fa-angle-left"></i> ' . $title . '</span></A>' );
		}

	}
}
