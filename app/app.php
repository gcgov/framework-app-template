<?php
namespace app;


use OpenApi\Attributes as OA;

// OpenAPI metadata for the generated documentation. Attributes are compile-time
// constants, so these are literals to edit once — unlike config.json, they are not
// environment-driven. A relative server url resolves against whatever host serves the
// document, which is what makes one spec correct in every Environment.
#[OA\Info( version: '1.0.0', title: 'Application', contact: new OA\Contact( email: 'itstaff@garrettcountymd.gov' ) )]
#[OA\Server( url: '/' )]
final class app implements \gcgov\framework\interfaces\app {

	// Nothing but the two lifecycle hooks — Framework Services are enabled in the
	// `services` section of config.json rather than registered here.
	//
	// The class is still required. \gcgov\framework\config derives every path in the
	// framework by reflecting on this file's location, so an application without it
	// cannot resolve its own root.


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
