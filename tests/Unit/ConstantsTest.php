<?php

declare(strict_types=1);

namespace app\tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use app\constants;
use app\router;

#[CoversClass(constants::class)]
final class ConstantsTest extends TestCase {

	public function testWidgetRoleConstantsExist(): void {
		$this->assertSame( 'Widget.Read', constants::ROLE_WIDGET_READ );
		$this->assertSame( 'Widget.Write', constants::ROLE_WIDGET_WRITE );
	}


	/**
	 * The point of naming the roles is that the route table uses the names. A constant no
	 * route references is decoration — which is what DEFAULT_ROLES had become once the
	 * service configuration it fed moved into config.json.
	 */
	public function testEveryRoleRequiredByARouteIsANamedConstant(): void {
		$named = [ constants::ROLE_WIDGET_READ, constants::ROLE_WIDGET_WRITE ];

		$required = [];
		foreach( ( new router() )->getRoutes() as $route ) {
			$required = array_merge( $required, $route->requiredRoles );
		}

		$this->assertNotEmpty( $required );
		foreach( array_unique( $required ) as $role ) {
			$this->assertContains( $role, $named, $role . ' is a literal in the route table' );
		}
	}

}
