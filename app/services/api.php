<?php

namespace app\services;


use JetBrains\PhpStorm\NoReturn;


class api {

	/**
	 * @param  int     $statusCode
	 * @param  string  $message
	 * @param  array   $data
	 */
	#[NoReturn]
	public static function sendError( int $statusCode, string $message, $data = [] ) {
		header( 'Content-Type:application/json' );

		http_response_code( $statusCode );

		$envelope = new \app\models\meta\apiEnvelope( $statusCode, true, $message, $data );

		echo json_encode( $envelope );

		die();
	}

}