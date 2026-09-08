<?php

declare(strict_types=1);

namespace app\tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use app\constants;
use app\router;
use gcgov\framework\models\route;
use gcgov\framework\models\routeHandler;

#[CoversClass(router::class)]
final class RouterTest extends TestCase {

	public function testRouterImplementsFrameworkAppRouterInterface(): void {
		// appRouter, not router: class_implements reports inherited interfaces, so
		// asserting the parent would still pass if this class stopped being an appRouter.
		$this->assertContains(
			\gcgov\framework\interfaces\appRouter::class,
			class_implements( router::class ) ?: []
		);
	}


	/**
	 * False, and deliberately so. config.json enables services.auth, whose guard
	 * establishes the caller's identity. authentication() below returns true for
	 * everyone, so claiming to provide authentication here would satisfy the framework's
	 * boot check while leaving every widget route open.
	 */
	public function testRouterDoesNotClaimToProvideAuthentication(): void {
		$this->assertFalse( ( new router() )->providesAuthentication() );
	}

	public function testGetRoutesReturnsFiveRoutes(): void {
		$routes = ( new router() )->getRoutes();
		$this->assertCount( 5, $routes );
		foreach ( $routes as $r ) {
			$this->assertInstanceOf( route::class, $r );
		}
	}

	public function testWidgetGetAllRoute(): void {
		// tests/bootstrap.php seeds unifiedConfig with basePath 'api', so the
		// runtime-derived route prefix is '/api'
		$routes = ( new router() )->getRoutes();
		$this->assertSame( 'GET', $routes[0]->httpMethod );
		$this->assertSame( '/api/widgets', $routes[0]->route );
		$this->assertSame( 'getAll', $routes[0]->method );
		$this->assertTrue( $routes[0]->authentication );
		$this->assertSame( [ constants::ROLE_WIDGET_READ ], $routes[0]->requiredRoles );
	}

	public function testWidgetGetOneRoute(): void {
		$routes = ( new router() )->getRoutes();
		$this->assertSame( 'GET', $routes[1]->httpMethod );
		$this->assertSame( '/api/widgets/{_id}', $routes[1]->route );
		$this->assertSame( 'getOne', $routes[1]->method );
	}

	public function testWidgetSaveRouteRequiresWritePermission(): void {
		$routes = ( new router() )->getRoutes();
		$this->assertSame( 'POST', $routes[2]->httpMethod );
		$this->assertSame( 'save', $routes[2]->method );
		$this->assertSame( [ constants::ROLE_WIDGET_READ, constants::ROLE_WIDGET_WRITE ], $routes[2]->requiredRoles );
	}

	public function testWidgetDeleteRoute(): void {
		$routes = ( new router() )->getRoutes();
		$this->assertSame( 'DELETE', $routes[3]->httpMethod );
		$this->assertSame( 'delete', $routes[3]->method );
		$this->assertSame( [ constants::ROLE_WIDGET_READ, constants::ROLE_WIDGET_WRITE ], $routes[3]->requiredRoles );
	}

	public function testCliRouteIsUnauthenticated(): void {
		$routes = ( new router() )->getRoutes();
		$this->assertSame( 'CLI', $routes[4]->httpMethod );
		$this->assertSame( '/cli/widgets', $routes[4]->route );
		$this->assertFalse( $routes[4]->authentication );
	}

	public function testAllAuthenticatedRoutesTargetWidgetController(): void {
		foreach ( ( new router() )->getRoutes() as $route ) {
			$this->assertSame( '\app\controllers\widget', $route->class );
		}
	}

	public function testAuthenticationReturnsTrue(): void {
		$handler = new routeHandler( '\some\controller', 'm' );
		$this->assertTrue( ( new router() )->authentication( $handler ) );
	}

	public function testLifecycleHooksReturnVoid(): void {
		router::_before();
		router::_after();

		$reflection = new \ReflectionClass( router::class );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_before' )->getReturnType() );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_after' )->getReturnType() );
	}

}
