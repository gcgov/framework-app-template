<?php

namespace app\models;

use andrewsauder\jsonDeserialize\attributes\excludeJsonSerialize;
use gcgov\framework\exceptions\modelException;
use gcgov\framework\services\mongodb\attributes\label;


/**
 * Class user
 * @OA\Schema()
 */
final class user extends \gcgov\framework\services\mongodb\model {

	const _COLLECTION = 'user';

	const _HUMAN = 'user';

	const _HUMAN_PLURAL = 'users';

	#[label( 'Id' )]
	/** @OA\Property(type="string") */
	public \MongoDB\BSON\ObjectId $_id;

	#[label( 'Name' )]
	/** @OA\Property() */
	public string                 $name     = '';

	#[label( 'Username' )]
	/** @OA\Property() */
	public string                 $username = '';

	/** @OA\Property() */
	public string $oauthId = '';

	/** @OA\Property() */
	public string $oauthProvider = '';

	#[label( 'Email' )]
	/** @OA\Property() */
	public string $email         = '';

	#[label( 'Password' )]
	#[excludeJsonSerialize]
	/** @OA\Property() */
	public string $password      = '';

	#[label( 'Authorization Roles' )]
	/**
	 * @OA\Property()
	 * @var string[]
	 */
	public array  $roles         = [];

	#[label( 'Active' )]
	/** @OA\Property() */
	public bool   $active        = true;


	public function __construct() {
		parent::__construct();
		$this->_id = new \MongoDB\BSON\ObjectId();
	}


	protected function _beforeBsonSerialize() : void {
		if( empty( $this->oauthId ) ) {
			//clear oauth provider
			if( !empty( $this->password ) ) {
				$this->oauthProvider = '';
			}

			//hash password or remove it from the object if it's not being updated
			if( empty( $this->password ) ) {
				unset( $this->password );
			}
			else {
				$this->password = password_hash( $this->password, PASSWORD_DEFAULT );
			}
		}
		else {
			//clear the password to limit sign in to oauth
			$this->password = '';
		}
	}


	/**
	 * @throws \gcgov\framework\exceptions\modelException
	 */
	public static function checkForOauthUser( string $email, string $oauthId, string $oauthProvider, ?string $firstName = '', ?string $lastName = '' ) : \app\models\user {
		/** @var \app\models\user $user */
		try {
			$filter =  [
				'$or' => [
					[
						'oauth_id' => $oauthId,
						'oauthProvider' => $oauthProvider
					],
					[ 'email' => $email ]
				]
			];
			$user = self::getOneBy( $filter );
		}
		catch( modelException $e ) {
			throw new \gcgov\framework\exceptions\modelException( $email. ' is not set up as a user. Please contact your supervisor to have this account enabled.', 401 );
		}

		$user->oauthId = $oauthId;
		$user->oauthProvider = $oauthProvider;
		$user->email = $email;
		$user->name = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

		self::save($user);

		return $user;
	}


	/**
	 * @throws \gcgov\framework\exceptions\modelException
	 */
	public static function verifyUserLogin( string $username, string $password ) : \app\models\user {
		/** @var \app\models\user $user */
		$user = self::getOneBy([ '$or'=> [ [ 'username'=> $username ], [ 'email'=> $username ] ] ]);

		//verify user password
		if( !empty( $user->password ) ) {
			$passwordValid = password_verify( $password, $user->password );
			if( $passwordValid ) {
				return $user;
			}
		}

		throw new \gcgov\framework\exceptions\modelException( 'Incorrect username or password', 401 );

	}

}
