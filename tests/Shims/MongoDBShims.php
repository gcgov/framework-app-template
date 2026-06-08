<?php

declare(strict_types=1);

namespace MongoDB\BSON {

	interface Type {}

	interface Serializable extends Type {
		public function bsonSerialize(): array|object;
	}

	interface Unserializable {
		public function bsonUnserialize( array $data ): void;
	}

	interface Persistable extends Serializable, Unserializable {}

	interface ObjectIdInterface extends \Stringable {
		public function __toString(): string;
		public function getTimestamp(): int;
	}

	if ( !class_exists( ObjectId::class, false ) ) {
		final class ObjectId implements Type, ObjectIdInterface, \JsonSerializable {
			private string $hex;

			public function __construct( ?string $id = null ) {
				if ( $id === null ) {
					$this->hex = bin2hex( random_bytes( 12 ) );
				}
				else {
					if ( !preg_match( '/^[0-9a-f]{24}$/i', $id ) ) {
						throw new \InvalidArgumentException( 'Invalid ObjectId hex string' );
					}
					$this->hex = strtolower( $id );
				}
			}

			public function __toString(): string {
				return $this->hex;
			}

			public function jsonSerialize(): array {
				return [ '$oid' => $this->hex ];
			}

			public function getTimestamp(): int {
				return (int) hexdec( substr( $this->hex, 0, 8 ) );
			}
		}
	}
}
