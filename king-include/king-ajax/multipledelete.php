<?php


require_once QA_INCLUDE_DIR . 'king-db/selects.php';

$fileName = qa_post_text('fileid');
$thumbid  = qa_post_text('thumbid');

$done = false;
if( isset( $fileName ) || isset( $thumbid ) ) {
	if (isset($fileName)) {
		$getu = king_get_uploads( $fileName );
		$path1 = QA_INCLUDE_DIR . $getu['content'] ;
		if ( file_exists( $path1 ) ) {
			king_delete_uploads( $fileName );
			unlink( $path1 );
			$done = true;
		}
	}
	if ( isset( $thumbid ) ) {
		$gett = king_get_uploads( $thumbid );
		$path2 = QA_INCLUDE_DIR . $gett['content'] ;
		if ( file_exists( $path2 ) ) {
			king_delete_uploads( $thumbid );
			unlink( $path2 );
			$done = true;
		}
	}

	if ($done) {
		echo "QA_AJAX_RESPONSE\n0\n";
	}
}

?>