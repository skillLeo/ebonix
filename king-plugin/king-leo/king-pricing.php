<?php
	class king_pricing{
		private $directory;
		private $urltoroot;
		public function load_module($directory, $urltoroot)
		{
			$this->directory=$directory;
			$this->urltoroot=$urltoroot;
		}
		public function suggest_requests() // for display in admin interface
		{
			return array(
				array(
					'title' => 'Pricing',
					'request' => 'pricing',
						'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
						),
				);
		}
		public function match_request($request)
		{
			return $request == 'pricing';
		}
		
		
		public function process_request($request)
		{
			$qa_content = qa_content_prepare();

			$qa_content['title'] = ''; // page title
			if (qa_is_logged_in()) {
				qa_redirect( 'membership' );
				exit;
			} else {

			$output = '<div class="membership-plans">';
			if (qa_opt('plan_1')) {
				$output .= '<input type="radio" id="ms1" name="mperiod" value="1" onclick="memClick(this);" />
				<label for="ms1" class="membership-plan" data-toggle="modal" data-target="#loginmodal" role="button"><h3>'.qa_opt('plan_1_title').'</h3><span>'.( '0' !== qa_opt('plan_n_1') ? qa_opt('plan_n_1') : '' ).' '.qa_opt('plan_t_1').'</span><span>'.qa_opt('plan_1_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_1').'</div></label>';
			}
			if (qa_opt('plan_2')) {
				$output .= '<input type="radio" id="ms2" name="mperiod" value="2" onclick="memClick(this);" />
				<label for="ms2" class="membership-plan" data-toggle="modal" data-target="#loginmodal" role="button"><h3>'.qa_opt('plan_2_title').'</h3><span>'.( '0' !== qa_opt('plan_n_2') ? qa_opt('plan_n_2') : '' ).' '.qa_opt('plan_t_2').'</span><span>'.qa_opt('plan_2_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_2').'</div></label>';
			}
			if (qa_opt('plan_3')) {
				$output .= '<input type="radio" id="ms3" name="mperiod" value="3" onclick="memClick(this);" />
				<label for="ms3" class="membership-plan" data-toggle="modal" data-target="#loginmodal" role="button"><h3>'.qa_opt('plan_3_title').'</h3><span>'.( '0' !== qa_opt('plan_n_3') ? qa_opt('plan_n_3') : '' ).' '.qa_opt('plan_t_3').'</span><span>'.qa_opt('plan_3_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_3').'</div></label>';
			}
			if (qa_opt('plan_4')) {
				$output .= '<input type="radio" id="ms4" name="mperiod" value="4" onclick="memClick(this);" />
				<label for="ms4" class="membership-plan unl" data-toggle="modal" data-target="#loginmodal" role="button"><h3>'.qa_opt('plan_4_title').'</h3><span>'.( '0' !== qa_opt('plan_n_4') ? qa_opt('plan_n_4') : '' ).' '.qa_opt('plan_t_4').'</span><span>'.qa_opt('plan_4_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_4').'</div></label>';
			}
			$output .= '</div>';


			$qa_content['custom'] = $output;
			}
			return $qa_content;
		}
	}