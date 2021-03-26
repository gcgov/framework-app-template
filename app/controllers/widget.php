<?php

namespace app\controllers;


use gcgov\framework\exceptions\controllerException;
use gcgov\framework\exceptions\modelException;
use gcgov\framework\interfaces\controller;
use gcgov\framework\models\controllerDataResponse;


/**
 * Class widget
 * @OA\Tag(
 *     name="widget",
 *     description="Widget object"
 * )
 */
class widget
	implements
	controller {

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
	 * @OA\Get(
	 *     path="/widget/",
	 *     tags={"Widget"},
	 *     description="Fetch all widgets",
	 *     @OA\Response(
	 *      response="200",
	 *      description="Successfully fetched",
	 *      @OA\JsonContent(
	 *          type="array",
	 *          @OA\Items(ref="#/components/schemas/widget")
	 *      )
	 *    )
	 * )
	 * @return \gcgov\framework\models\controllerDataResponse
	 * @throws \gcgov\framework\exceptions\controllerException
	 */
	public function getAll() : controllerDataResponse {
		try {
			$widgets = \app\models\widget::getAll();
		}
		catch( modelException $e ) {
			throw new controllerException( $e->getMessage(), $e->getCode(), $e );
		}

		return new controllerDataResponse( $widgets );
	}


	/**
	 * @OA\Get(
	 *     path="/widget/{_id}",
	 *     tags={"Widget"},
	 *      description="Fetch an widget object",
	 *     @OA\Parameter(in="path", name="_id", required=true, @OA\Schema(type="string")),
	 *     @OA\Response(
	 *      response="200",
	 *      description="Successfully fetched",
	 *      @OA\JsonContent(ref="#/components/schemas/widget")
	 *    )
	 * )
	 * @param  string  $_id
	 *
	 * @return \gcgov\framework\models\controllerDataResponse
	 * @throws \gcgov\framework\exceptions\controllerException
	 */
	public function getOne( string $_id ) : controllerDataResponse {
		if( $_id === 'new' ) {
			$widget = new \app\models\widget();
		}
		else {
			try {
				$widget = \app\models\widget::getOne( $_id );
			}
			catch( modelException $e ) {
				throw new controllerException( $e->getMessage(), $e->getCode(), $e );
			}
		}

		return new controllerDataResponse( $widget );
	}

	/**
	 * @OA\Post(
	 *     path="/widget/{_id}",
	 *     tags={"Widget"},
	 *      description="Create or update an widget object",
	 *     @OA\Parameter(in="path", name="_id", required=true, @OA\Schema(type="string")),
	 *     @OA\RequestBody(
	 *      description="widget object to save",
	 *      required=true,
	 *      @OA\JsonContent(ref="#/components/schemas/widget")
	 *     ),
	 *     @OA\Response(
	 *      response="200",
	 *      description="Successfully saved",
	 *      @OA\JsonContent(ref="#/components/schemas/widget")
	 *    )
	 * )
	 * @param  string  $_id
	 *
	 * @return \gcgov\framework\models\controllerDataResponse
	 * @throws \gcgov\framework\exceptions\controllerException
	 */
	public function save( string $_id ) : controllerDataResponse {
		$widgetJSON = file_get_contents( 'php://input' );

		try {
			$widget   = \app\models\widget::jsonDeserialize( $widgetJSON );
			$updateResult = \app\models\widget::save( $widget );
		}
		catch( modelException $e ) {
			throw new controllerException( $e->getMessage(), $e->getCode(), $e );
		}

		return new controllerDataResponse( $widget );
	}


	/**
	 * @OA\Delete(
	 *     path="/widget/{_id}",
	 *     tags={"Widget"},
	 *     @OA\Parameter(in="path", name="_id", required=true, @OA\Schema(type="string")),
	 *     @OA\Response(
	 *      response="204",
	 *      description="Successfully deleted"
	 *    )
	 * )
	 * @param  string  $_id
	 *
	 * @return \gcgov\framework\models\controllerDataResponse
	 * @throws \gcgov\framework\exceptions\controllerException
	 */
	public function delete( string $_id ) : controllerDataResponse {
		try {
			$deleteResult = \app\models\widget::delete( $_id );
		}
		catch( modelException $e ) {
			throw new controllerException( $e->getMessage(), $e->getCode(), $e );
		}

		$response = new controllerDataResponse( null );
		$response->setHttpStatus( 204 );

		return $response;
	}

}