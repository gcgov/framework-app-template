<?php
namespace app\models;


use gcgov\framework\services\mongodb\attributes\label;


/**
 * Class widget
 * @OA\Schema()
 */
final class widget
	extends
	\gcgov\framework\services\mongodb\model {

	const _COLLECTION = 'widget';

	const _HUMAN = 'widget';

	const _HUMAN_PLURAL = 'widgets';

	#[label( 'Id' )]
	/** @OA\Property(type="string") */
	public \MongoDB\BSON\ObjectId $_id;

	#[label( 'Types' )]
	/**
	 * @OA\Property()
	 * @var string[]
	 */
	public array                  $types       = [];

	#[label( 'Active' )]
	/** @OA\Property() */
	public bool                   $active      = true;

	#[label( 'Name' )]
	/** @OA\Property() */
	public string                 $name        = '';

	#[label( 'Types List' )]
	/**
	 * @OA\Property()
	 * @var string[]
	 */
	public array                  $_validTypes = [
		'toys'     => 'Toys',
		'vehicles' => 'Vehicles',
		'tools'    => 'Tools',
		'food'     => 'Food'
	];


	public function __construct() {
		parent::__construct();
		$this->_id = new \MongoDB\BSON\ObjectId();
	}

}