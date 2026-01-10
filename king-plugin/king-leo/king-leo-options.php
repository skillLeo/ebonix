<?php

class king_leo_op {

	function allow_template($template)
	{
		return ($template!='admin');
	}

	
	function admin_form(&$qa_content)
	{

		$ok = null;
		if (qa_clicked('king_leo_save_button')) {

			qa_opt('kingh_title', qa_post_text('kingh_title'));
			qa_opt('kingh_desc', qa_post_text('kingh_desc'));
			qa_opt('hsubmit',(bool)qa_post_text('hsubmit'));

			$ok = qa_lang('admin/options_saved');
		}

		$fields = array();

		$fields[] = array(
			'type' => 'custom',
			'html' => '<div class="king-form-desc " >All options moved to Admin > AI </div>'
		);
		
		$fields[] = array(
			'id'    => 'kingh_title',
			'label' => 'Homepage Title',
			'tags'  => 'NAME="kingh_title"',
			'value' => qa_opt('kingh_title'),
			'type'  => 'text',	
		);
		$fields[] = array(
			'id'    => 'kingh_desc',
			'label' => 'Homepage Description',
			'tags'  => 'NAME="kingh_desc"',
			'value' => qa_opt('kingh_desc'),
			'type'  => 'text',	
		);	
		$fields[] = array(
			'label' => 'Hide Submit Button',
			'id' => 'hsubmit',
			'tags' => 'NAME="hsubmit"',
			'value' => qa_opt('hsubmit'),
			'type' => 'checkbox',
		); 
		

		return array(
			'ok' => ($ok && !isset($error)) ? $ok : null,
			
			'fields' => $fields,
			
			'buttons' => array(
				array(
				'label' => qa_lang_html('main/save_button'),
				'tags' => 'NAME="king_leo_save_button"',
				),
			),
		);
	}

}