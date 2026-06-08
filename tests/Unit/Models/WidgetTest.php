<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use app\models\widget;
use MongoDB\BSON\ObjectId;

#[CoversClass(widget::class)]
final class WidgetTest extends TestCase {

	public function testConstructorAssignsObjectIdToUnderscoreId(): void {
		$widget = new widget();
		$this->assertInstanceOf( ObjectId::class, $widget->_id );
	}

	public function testTwoWidgetsGetUniqueIds(): void {
		$a = new widget();
		$b = new widget();
		$this->assertNotSame( (string) $a->_id, (string) $b->_id );
	}

	public function testDefaultsArePopulated(): void {
		$widget = new widget();
		$this->assertSame( [], $widget->types );
		$this->assertTrue( $widget->active );
		$this->assertSame( '', $widget->name );
		$this->assertSame(
			[ 'toys' => 'Toys', 'vehicles' => 'Vehicles', 'tools' => 'Tools', 'food' => 'Food' ],
			$widget->_validTypes
		);
	}

	public function testCollectionConstantsAreDefined(): void {
		$this->assertSame( 'widget', widget::_COLLECTION );
		$this->assertSame( 'widget', widget::_HUMAN );
		$this->assertSame( 'widgets', widget::_HUMAN_PLURAL );
	}

	public function testClassIsFinal(): void {
		$this->assertTrue( ( new \ReflectionClass( widget::class ) )->isFinal() );
	}

	public function testExtendsFrameworkMongoModel(): void {
		$this->assertTrue(
			is_subclass_of( widget::class, \gcgov\framework\services\mongodb\model::class )
		);
	}

	public function testValidTypesPropertyHasExcludeSerializeAttributes(): void {
		$reflection = new \ReflectionProperty( widget::class, '_validTypes' );

		$attributeNames = array_map(
			fn( \ReflectionAttribute $a ) => $a->getName(),
			$reflection->getAttributes()
		);

		$this->assertContains(
			\gcgov\framework\services\mongodb\attributes\excludeBsonSerialize::class,
			$attributeNames
		);
		$this->assertContains(
			\gcgov\framework\services\mongodb\attributes\excludeBsonUnserialize::class,
			$attributeNames
		);
	}

}
