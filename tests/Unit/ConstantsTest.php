<?php

declare(strict_types=1);

namespace app\tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use app\constants;

#[CoversClass(constants::class)]
final class ConstantsTest extends TestCase {

	public function testDefaultRolesConstantExistsAndIsArray(): void {
		$this->assertTrue( defined( constants::class . '::DEFAULT_ROLES' ) );
		$this->assertIsArray( constants::DEFAULT_ROLES );
	}

	public function testDefaultRolesIsEmptyByDefault(): void {
		$this->assertSame( [], constants::DEFAULT_ROLES );
	}

}
