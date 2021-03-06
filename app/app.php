<?php
namespace app;


use gcgov\framework\config;


/**
 * @OA\Info(
 *     title="{app_title}",
 *     version="1.0.0",
 *     @OA\Contact(
 *          email="{app_smtp_sendmail_from_address}"
 *     )
 * )
 *
 * @OA\Server(url="{app_url}")
 *
 */
final class app implements \gcgov\framework\interfaces\app {


	/**
	 * Processed after lifecycle is complete with this instance
	 */
	public static function _after() : void {
	}


	/**
	 * Processed prior to __constructor() being called
	 */
	public static function _before() : void {
	}


}