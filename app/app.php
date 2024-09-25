<?php
namespace app;


use gcgov\framework\config;
use OpenApi\Attributes as OA;

#[OA\Info( version: '1.0.0', title: '{app_title}',contact: new OA\Contact(email:'itstaff@garrettcountymd.gov') )]
#[OA\Server(url:'{prod_app_root_url}{prod_app_base_path}')]
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


	public function registerFrameworkServiceNamespaces(): array {
		//uncomment to auto create new user entries if the user does not have one in the user collection
		//$msAuthConfig = \gcgov\framework\services\authmsfront\msAuthConfig::getInstance();
		//$msAuthConfig->setBlockNewUsers( false, constants::DEFAULT_ROLES );

		return [
			'\gcgov\framework\services\documentation',
			'\gcgov\framework\services\cronMonitor',
			'\gcgov\framework\services\usercrud',
			'\gcgov\framework\services\authmsfront',
		];
	}


}
