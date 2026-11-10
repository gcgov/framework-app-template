<?php

namespace app;


use gcgov\framework\models\route;


class router implements \gcgov\framework\interfaces\appRouter {

	public function __construct() {
	}


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


	/**
	 * @return \gcgov\framework\models\route[]
	 */
	public function getRoutes() : array {
		/** @var \gcgov\framework\models\route[] $routes */
		$routes = [];

		//the base path comes from config.json (env var APP_BASE_PATH), so it can differ per
		//environment with no code change; getBasePath() returns '/api' style ('/' at domain root)
		$routePrepend = rtrim( \gcgov\framework\config::getBasePath(), '/' );

		//WIDGETS
		$routes[] = new route( 'GET', $routePrepend.'/widgets', '\app\controllers\widget', 'getAll', true, [ constants::ROLE_WIDGET_READ ] );
		$routes[] = new route( 'GET', $routePrepend.'/widgets/{_id}', '\app\controllers\widget', 'getOne', true, [ constants::ROLE_WIDGET_READ ] );
		$routes[] = new route( 'POST', $routePrepend.'/widgets/{_id}', '\app\controllers\widget', 'save', true, [ constants::ROLE_WIDGET_READ, constants::ROLE_WIDGET_WRITE ] );
		$routes[] = new route( 'DELETE', $routePrepend.'/widgets/{_id}', '\app\controllers\widget', 'delete', true, [ constants::ROLE_WIDGET_READ, constants::ROLE_WIDGET_WRITE ] );

		//CLI example
		//to run in command line: `vendor/bin/gf cli /cli/widgets`
		$routes[] = new route( 'CLI', '/cli/widgets', '\app\controllers\widget', 'getAll', false );

		return $routes;
	}


	/**
	 * Does this application authenticate its own routes?
	 *
	 * No — config.json enables `services.auth`, and that service's guard establishes and
	 * verifies the caller's identity on every route marked authentication: true.
	 *
	 * Answer true ONLY if authentication() below genuinely authenticates. The framework
	 * refuses to boot when authenticated routes exist with neither an auth service nor an
	 * application claiming this, because such routes are reachable by anyone while looking
	 * protected. Returning true without doing the work re-opens exactly that hole.
	 */
	public function providesAuthentication() : bool {
		return false;
	}


	/**
	 * This method is automagically called when a route is matched that has authentication set to true.
	 * It runs BEFORE the enabled Framework Service guards, so it is where an application adds
	 * checks of its own — not where the identity is established.
	 *
	 * @param  \gcgov\framework\models\routeHandler  $routeHandler
	 *
	 * @return bool
	 *
	 * @throws \gcgov\framework\exceptions\routeException
	 */
	public function authentication( \gcgov\framework\models\routeHandler $routeHandler ) : bool {
		//add custom authentication checks here; they run prior to the services.auth guard
		//
		//  return false, or throw a routeException describing why, to deny the request:
		//    throw new \gcgov\framework\exceptions\routeException( 'Authentication failed because XYZ', 401 );
		//
		//  to skip the services.auth guard for particular routes, implement
		//  \gcgov\framework\interfaces\router\skipsServiceAuthentication on this class and
		//  return false from getRunFrameworkServiceRouteAuthentication() for them

		return true;
	}

}
