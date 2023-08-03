<?php

namespace app\models;

/**
 * Class auth
 * Singleton to store authenticated user globally
 * @OA\Schema()
 */
final class authUser extends \gcgov\framework\models\authUser {


	/**
	 * @param \app\models\user $user
	 *
	 * @return \app\models\authUser
	 */
	public function setFromUser( \app\models\user $user ): authUser {
		$this->userId           = '';
		$this->externalId       = $user->oauthId;
		$this->externalProvider = $user->oauthProvider;
		$this->name             = $user->name;
		$this->username         = $user->username;
		$this->email            = $user->email;
		$this->roles            = $user->roles;
		return self::getInstance();
	}


	public function hasRole( string $role ): bool {
		return in_array( $role, $this->roles );
	}


	/**
	 * @return array
	 */
	public function getEmails(): array {
		$emails = [];
		if( !empty($this->email) ) {
			$emails[] = $this->email;
		}
		return array_unique( array_merge( $emails, $this->altEmails ) );
	}

}
