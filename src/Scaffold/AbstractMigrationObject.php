<?php

namespace Newspack\MigrationTools\Scaffold;

use ArrayAccess;
use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject;

/**
 * AbstractMigrationObject.
 */
abstract class AbstractMigrationObject implements MigrationObject, ArrayAccess {

	/**
	 * The underlying data that needs to be migrated.
	 *
	 * @var object|array $data The underlying data that needs to be migrated.
	 */
	protected object|array $data;

	/**
	 * Pointer to the property which uniquely identifies an object.
	 *
	 * @var string $pointer_to_identifier Pointer to the identifier.
	 */
	protected string $pointer_to_identifier;

	/**
	 * Migration Data Set Container.
	 *
	 * @var MigrationDataChest $data_container Migration Data Set Container.
	 */
	protected MigrationDataChest $data_container;

	/**
	 * Constructor.
	 *
	 * @param object|array       $data The underlying data that needs to be migrated.
	 * @param string             $pointer_to_identifier Pointer to the identifier.
	 * @param MigrationDataChest $data_container Migration Data Set Container.
	 */
	public function __construct( object|array $data, string $pointer_to_identifier, MigrationDataChest $data_container ) {
		$this->data                  = $data;
		$this->data_container        = $data_container;
		$this->pointer_to_identifier = $pointer_to_identifier;
	}

	/**
	 * Gets the underlying data that needs to be migrated.
	 */
	public function get(): array|object {
		return $this->data;
	}

	/**
	 * Pointer to the property which uniquely identifies an object.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string {
		return $this->pointer_to_identifier;
	}

	/**
	 * Returns the ID that uniquely identifies the underlying data object.
	 *
	 * @return string|int
	 */
	public function get_data_id(): string|int {
		return $this->data[ $this->get_pointer_to_identifier() ];
	}

	/**
	 * Returns the Migration Data Set Container this Migration Object belongs to.
	 *
	 * @return MigrationDataChest
	 */
	public function get_container(): MigrationDataChest {
		return $this->data_container;
	}

	/**
	 * Whether a offset exists.
	 *
	 * @param mixed $offset An offset to check for.
	 *
	 * @return bool
	 */
	public function offsetExists( mixed $offset ): bool {
		return $this->__isset( $offset );
	}

	/**
	 * Offset to retrieve.
	 *
	 * @param mixed $offset The offset to retrieve.
	 *
	 * @return MigrationObjectPropertyWrapper|null
	 */
	public function offsetGet( mixed $offset ): ?MigrationObjectPropertyWrapper {
		return $this->__get( $offset );
	}

	/**
	 * Offset to set.
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value The value to set.
	 *
	 * @return void
	 * @throws Exception Cannot set values directly on a MigrationObject.
	 */
	public function offsetSet( mixed $offset, mixed $value ): void {
		$this->__set( $offset, $value );
	}

	/**
	 * Offset to unset.
	 *
	 * @param mixed $offset The offset to unset.
	 *
	 * @return void
	 * @throws Exception Cannot unset values directly on a MigrationObject.
	 */
	public function offsetUnset( mixed $offset ): void {
		$this->__unset( $offset );
	}

	/**
	 * Magic method to get properties.
	 *
	 * @param int|string $name Property name.
	 *
	 * @return MigrationObjectPropertyWrapper|null
	 */
	public function __get( int|string $name ): ?MigrationObjectPropertyWrapper {
		if ( $this->__isset( $name ) ) {
			return new MigrationObjectPropertyWrapper( $this->data, [ $name ], $this );
		}

		return null;
	}

	/**
	 * Magic method to set properties.
	 *
	 * @param int|string $key Property name.
	 * @param mixed      $value Property value.
	 *
	 * @return void
	 * @throws Exception Cannot set values directly on a MigrationObject.
	 */
	public function __set( int|string $key, mixed $value ): void {
		throw new Exception( 'MigrationObject is read-only.' );
	}

	/**
	 * Magic method to check if a property is set.
	 *
	 * @param int|string $key Property name.
	 *
	 * @return bool
	 */
	public function __isset( int|string $key ): bool {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Magic method to unset properties.
	 *
	 * @param int|string $key Property name.
	 *
	 * @return void
	 * @throws Exception Cannot unset values directly on a MigrationObject.
	 */
	public function __unset( int|string $key ): void {
		throw new Exception( 'MigrationObject is read-only.' );
	}

	/**
	 * Magic method to convert the object to a string.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return '<mig_scaf>' . wp_json_encode( $this->data ) . '</mig_scaf>';
	}
}
