<?php

declare(strict_types=1);

namespace app\tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use app\app;

#[CoversClass(app::class)]
final class AppTest extends TestCase {

	public function testAppImplementsFrameworkAppInterface(): void {
		$this->assertContains(
			\gcgov\framework\interfaces\app::class,
			class_implements( app::class ) ?: []
		);
	}

	public function testAppClassIsFinal(): void {
		$this->assertTrue( ( new \ReflectionClass( app::class ) )->isFinal() );
	}

	public function testRegisterFrameworkServiceNamespacesReturnsArray(): void {
		$namespaces = ( new app() )->registerFrameworkServiceNamespaces();
		$this->assertIsArray( $namespaces );
	}

	public function testRegisteredNamespacesIncludeExpectedServices(): void {
		$namespaces = ( new app() )->registerFrameworkServiceNamespaces();
		$this->assertContains( '\gcgov\framework\services\documentation', $namespaces );
		$this->assertContains( '\gcgov\framework\services\cronMonitor', $namespaces );
		$this->assertContains( '\gcgov\framework\services\usercrud', $namespaces );
		$this->assertContains( '\gcgov\framework\services\authoauth', $namespaces );
	}

	public function testLifecycleHooksReturnVoid(): void {
		app::_before();
		app::_after();

		$reflection = new \ReflectionClass( app::class );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_before' )->getReturnType() );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_after' )->getReturnType() );
	}

}
