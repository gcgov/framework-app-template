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

	/**
	 * Framework Services are enabled in config.json's `services` section. The application
	 * class no longer names them, and ConfigFilesTest asserts what is actually enabled.
	 */
	public function testAppNoLongerRegistersServiceNamespaces(): void {
		$this->assertFalse( method_exists( app::class, 'registerFrameworkServiceNamespaces' ) );
	}

	public function testLifecycleHooksReturnVoid(): void {
		app::_before();
		app::_after();

		$reflection = new \ReflectionClass( app::class );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_before' )->getReturnType() );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_after' )->getReturnType() );
	}

}
