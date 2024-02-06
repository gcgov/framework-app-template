<?php

namespace app;


use gcgov\framework\models\route;


class router
	implements
	\gcgov\framework\interfaces\router {

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

		//if your app will not run at the root of the domain, add the relative url to the app: ie: if your site will serve from http://example.com/api, $routePrepend="/api";
		$routePrepend = '{app_base_path}';

		//WIDGETS
		$routes[] = new route( 'GET', $routePrepend.'widgets', '\app\controllers\widget', 'getAll', true, [ 'Widget.Read' ] );
		$routes[] = new route( 'GET', $routePrepend.'widgets/{_id}', '\app\controllers\widget', 'getOne', true, [ 'Widget.Read' ] );
		$routes[] = new route( 'POST', $routePrepend.'widgets/{_id}', '\app\controllers\widget', 'save', true, [ 'Widget.Read', 'Widget.Write' ] );
		$routes[] = new route( 'DELETE', $routePrepend.'widgets/{_id}', '\app\controllers\widget', 'delete', true, [ 'Widget.Read', 'Widget.Write' ] );

		//CLI example
		//to run in command line: `/app/cli/local.bat /cli/widgets`
		$routes[] = new route( 'CLI', '/cli/widgets', '\app\controllers\widget', 'getAll', false );

		return $routes;
	}


	/**
	 * This method is automagically called when a route is matched that has authentication set to true
	 *
	 * @param  \gcgov\framework\models\routeHandler  $routeHandler
	 *
	 * @return bool
	 *
	 * @throws \gcgov\framework\exceptions\routeException
	 */
	public function authentication( \gcgov\framework\models\routeHandler $routeHandler ) : bool {


		//check if user is authenticated

		//if user is not authenticated, throw a routeException with a message clearly stating why authentication failed
		//throw new \gcgov\framework\exceptions\routeException( 'Missing Authorization', 401 );

		//to validate route roles, compare user roles with the route roles in $routeHandler->requiredRoles
		//$routeHandler->requiredRoles


		//user has been authenticated
		return true;
	}

}
