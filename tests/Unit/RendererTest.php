<?php

declare(strict_types=1);

namespace app\tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use app\renderer;
use gcgov\framework\exceptions\controllerException;
use gcgov\framework\exceptions\modelException;
use gcgov\framework\exceptions\routeException;
use gcgov\framework\models\controllerDataResponse;

#[CoversClass(renderer::class)]
final class RendererTest extends TestCase {

	public function testImplementsRenderInterface(): void {
		$this->assertContains(
			\gcgov\framework\interfaces\render::class,
			class_implements( renderer::class ) ?: []
		);
	}

	public function testProcessModelExceptionReturnsErrorPayload(): void {
		$response = renderer::processModelException( new modelException( 'missing widget', 404 ) );
		$this->assertInstanceOf( controllerDataResponse::class, $response );
		$this->assertSame( 404, $response->getHttpStatus() );

		$data = $response->getData();
		$this->assertTrue( $data[ 'error' ] );
		$this->assertSame( 'missing widget', $data[ 'message' ] );
		$this->assertSame( 404, $data[ 'status' ] );
	}

	public function testProcessControllerExceptionReturnsErrorPayload(): void {
		$response = renderer::processControllerException( new controllerException( 'invalid input', 400 ) );
		$this->assertSame( 400, $response->getHttpStatus() );
		$this->assertSame( 'invalid input', $response->getData()[ 'message' ] );
	}

	public function testProcessRouteExceptionReturnsErrorPayload(): void {
		$response = renderer::processRouteException( new routeException( 'unauthorized', 401 ) );
		$this->assertSame( 401, $response->getHttpStatus() );
		$this->assertSame( 'unauthorized', $response->getData()[ 'message' ] );
	}

	public function testProcessSystemErrorExceptionAlwaysReturns500(): void {
		$response = renderer::processSystemErrorException( new \Exception( 'boom', 502 ) );
		$this->assertSame( 500, $response->getHttpStatus() );
		$this->assertSame( 'boom', $response->getData()[ 'message' ] );
		$this->assertSame( 502, $response->getData()[ 'status' ] );
	}

	public function testProcessSystemErrorAcceptsErrorTypes(): void {
		$response = renderer::processSystemErrorException( new \Error( 'fatal' ) );
		$this->assertSame( 500, $response->getHttpStatus() );
		$this->assertSame( 'fatal', $response->getData()[ 'message' ] );

		$response = renderer::processSystemErrorException( new \ErrorException( 'php warning' ) );
		$this->assertSame( 500, $response->getHttpStatus() );
	}

	public function testLifecycleHooksReturnVoid(): void {
		renderer::_before();
		renderer::_after();

		$reflection = new \ReflectionClass( renderer::class );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_before' )->getReturnType() );
		$this->assertSame( 'void', (string) $reflection->getMethod( '_after' )->getReturnType() );
	}

}
