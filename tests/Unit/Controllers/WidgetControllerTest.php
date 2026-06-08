<?php

declare(strict_types=1);

namespace app\tests\Unit\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use app\controllers\widget;

#[CoversClass(widget::class)]
final class WidgetControllerTest extends TestCase {

	public function testControllerImplementsFrameworkInterface(): void {
		$this->assertContains(
			\gcgov\framework\interfaces\controller::class,
			class_implements( widget::class ) ?: []
		);
	}

	public function testConstructorRequiresNoArguments(): void {
		$reflection = new \ReflectionClass( widget::class );
		$constructor = $reflection->getConstructor();
		$this->assertNotNull( $constructor );
		$this->assertSame( 0, $constructor->getNumberOfRequiredParameters() );
		$this->assertInstanceOf( widget::class, new widget() );
	}

	public function testGetAllSignatureReturnsControllerDataResponse(): void {
		$reflection = new \ReflectionMethod( widget::class, 'getAll' );
		$this->assertSame(
			\gcgov\framework\models\controllerDataResponse::class,
			(string) $reflection->getReturnType()
		);
	}

	public function testGetOneSignatureRequiresStringId(): void {
		$reflection = new \ReflectionMethod( widget::class, 'getOne' );
		$params = $reflection->getParameters();
		$this->assertCount( 1, $params );
		$this->assertSame( '_id', $params[0]->getName() );
		$this->assertSame( 'string', (string) $params[0]->getType() );
	}

	public function testSaveSignatureRequiresStringId(): void {
		$reflection = new \ReflectionMethod( widget::class, 'save' );
		$params = $reflection->getParameters();
		$this->assertCount( 1, $params );
		$this->assertSame( 'string', (string) $params[0]->getType() );
	}

	public function testDeleteSignatureRequiresStringId(): void {
		$reflection = new \ReflectionMethod( widget::class, 'delete' );
		$params = $reflection->getParameters();
		$this->assertCount( 1, $params );
		$this->assertSame( 'string', (string) $params[0]->getType() );
	}

	public function testClassHasOpenApiTagAttribute(): void {
		$reflection = new \ReflectionClass( widget::class );
		$attributeNames = array_map(
			fn( \ReflectionAttribute $a ) => $a->getName(),
			$reflection->getAttributes()
		);
		$this->assertContains( \OpenApi\Attributes\Tag::class, $attributeNames );
	}

	public function testLifecycleHooksReturnVoid(): void {
		widget::_before();
		widget::_after();

		$reflection = new \ReflectionClass( widget::class );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_before' )->getReturnType() );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_after' )->getReturnType() );
	}

}
