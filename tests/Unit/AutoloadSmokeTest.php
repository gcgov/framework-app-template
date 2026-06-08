<?php

declare(strict_types=1);

namespace app\tests\Unit;

use PHPUnit\Framework\TestCase;

final class AutoloadSmokeTest extends TestCase {

	public function testAppClassesAutoload(): void {
		$this->assertTrue( class_exists( \app\app::class ) );
		$this->assertTrue( class_exists( \app\router::class ) );
		$this->assertTrue( class_exists( \app\renderer::class ) );
	}

	public function testAppImplementsFrameworkAppInterface(): void {
		$implements = class_implements( \app\app::class ) ?: [];
		$this->assertContains( \gcgov\framework\interfaces\app::class, $implements );
	}

	public function testWidgetControllerExists(): void {
		$this->assertTrue( class_exists( \app\controllers\widget::class ) );
	}

}
