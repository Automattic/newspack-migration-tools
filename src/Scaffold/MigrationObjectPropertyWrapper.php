<?php

namespace Newspack\MigrationTools\Scaffold;

use ArrayAccess;
use ArrayIterator;
use Exception;
use IteratorAggregate;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject;
use Traversable;

/**
 * Class MigrationObjectPropertyWrapper.
 */
class MigrationObjectPropertyWrapper implements ArrayAccess, IteratorAggregate {

	/**
	 * Stack containing the different attributes used to reach the current property.
	 *
	 * @var int[]|string[] $path Stack containing the different attributes used to reach the current property.
	 */
	private array $path = [];

	/**
	 * Current property name.
	 *
	 * @var string $current Current property name.
	 */
	private string $current = '';

	/**
	 * Property to be wrapped.
	 *
	 * @var mixed $property Property to be wrapped.
	 */
	private mixed $property;

	/**
	 * MigrationObjectPropertyWrapper constructor.
	 *
	 * @param mixed $property Property to be wrapped.
	 * @param array $established_path Stack containing the different attributes used to reach the current property.
	 */
	public function __construct( mixed $property, array $established_path ) {
		$this->path    = $established_path;
		$this->current = array_pop( $this->path );
		$current       = $this->current;

		if ( is_array( $property ) ) {
			$this->property = [ $current => $property[ $current ] ];
		} elseif ( is_object( $property ) ) {
			$this->property = (object) [ $current => $property->$current ];
		} else {
			$this->property = $property;
		}
	}

	/**
	 * Returns the dot-separated (.) path to the current property.
	 *
	 * @return string
	 */
	public function get_path(): string {
		return implode( '.', array_merge( $this->path, [ $this->current ] ) );
	}

	/**
	 * Returns the current property value.
	 *
	 * @return mixed
	 */
	public function get_value(): mixed {
		if ( is_array( $this->property ) ) {
			$prop = $this->current;

			return $this->property[ $prop ];
		} elseif ( is_object( $this->property ) ) {
			$prop = $this->current;

			return $this->property->$prop;
		}

		return $this->property;
	}

	/**
	 * Whether a offset exists
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @inheritDoc
	 *
	 * @param mixed $offset The offset to check for.
	 *
	 * @return bool The return value will be cast to boolean if non-boolean was returned.
	 */
	public function offsetExists( mixed $offset ): bool {
		return $this->__isset( $offset );
	}

	/**
	 * Offset to retrieve
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @inheritDoc
	 *
	 * @param mixed $offset The offset to retrieve.
	 *
	 * @return MigrationObjectPropertyWrapper|null
	 */
	public function offsetGet( mixed $offset ): ?MigrationObjectPropertyWrapper {
		if ( is_int( $offset ) ) {
			$offset = (string) $offset;
		}

		if ( $offset === $this->current ) {
			$current_value      = $this->property[ $this->current ];
			$established_path   = $this->path;
			$established_path[] = $this->current;
			if ( ! $this->is_associative( $current_value ) ) {
				$established_path[] = $offset;
				return new MigrationObjectPropertyWrapper( $current_value, $established_path );
			}
			return new MigrationObjectPropertyWrapper( $this->property, $established_path );
		} elseif ( $this->offsetExists( $offset ) ) {
			return $this->__get( $offset );
		}

		return null;
	}

	/**
	 * Offset to set
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @inheritDoc
	 *
	 * @param mixed $offset Offset to set.
	 * @param mixed $value Value to set.
	 *
	 * @return void
	 * @throws Exception Cannot set values directly on a MigrationObjectPropertyWrapper.
	 */
	public function offsetSet( mixed $offset, mixed $value ): void {
		$this->__set( $offset, $value );
	}

	/**
	 * Offset to unset
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @inheritDoc
	 *
	 * @param mixed $offset The offset to unset.
	 *
	 * @return void
	 * @throws Exception Cannot unset values directly on a MigrationObjectPropertyWrapper.
	 */
	public function offsetUnset( mixed $offset ): void {
		$this->__unset( $offset );
	}

	/**
	 * Retrieve an external iterator
	 *
	 * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
	 * @return Traversable An instance of an object implementing Iterator or Traversable
	 *
	 * @throws Exception On failure.
	 */
	public function getIterator(): Traversable {
		if ( ! is_array( $this->property ) & ! is_object( $this->property ) ) {
			throw new Exception( 'Cannot iterate over a non-array or non-object.' );
		}

		$values = $this->get_value();

		foreach ( $values as $key => $value ) {
			$established_path   = $this->path;
			$established_path[] = $this->current;
			$established_path[] = $key;

			if ( is_array( $value ) ) {
				$value = [ $key => $value ];
			} elseif ( is_object( $value ) ) {
				$value = (object) [ $key => $value ];
			}

			$values[ $key ] = new MigrationObjectPropertyWrapper( $value, $established_path );
		}

		return new ArrayIterator( $values );
	}

	/**
	 * Magic method to get properties.
	 *
	 * @param string $name Property name.
	 *
	 * @return ?MigrationObjectPropertyWrapper
	 */
	public function __get( string $name ): ?MigrationObjectPropertyWrapper {
		if ( $this->__isset( $name ) ) {
			$current_value      = $this->get_value();
			$established_path   = $this->path;
			$established_path[] = $this->current;
			$established_path[] = $name;

			return new MigrationObjectPropertyWrapper( $current_value, $established_path );
		}

		return null;
	}

	/**
	 * Magic method to set properties. Here, we have explicitly disabled setting values directly on a MigrationObjectPropertyWrapper.
	 *
	 * @param string $key Property name.
	 * @param mixed  $value Property value.
	 *
	 * @return void
	 * @throws Exception Cannot set values directly on a MigrationObjectPropertyWrapper.
	 */
	public function __set( string $key, $value ): void {
		throw new Exception( 'Cannot set values directly on a MigrationObjectPropertyWrapper.' );
	}

	/**
	 * Magic method to check if a property is set.
	 *
	 * @param int|string $key Property name.
	 *
	 * @return bool
	 */
	public function __isset( int|string $key ): bool {
		if ( is_int( $key ) ) {
			$key = (string) $key;
		}

		$current_value = $this->get_value();

		if ( $key === $this->current ) {
			return true;
		} elseif ( is_array( $current_value ) ) {
			return isset( $current_value[ $key ] );
		} elseif ( is_object( $current_value ) ) {
			return isset( $current_value->$key );
		}

		return false;
	}

	/**
	 * Magic method to unset properties. Here, we have explicitly disabled unsetting values directly on a MigrationObjectPropertyWrapper.
	 *
	 * @param int|string $key Property name.
	 *
	 * @return void
	 * @throws Exception Cannot unset values directly on a MigrationObjectPropertyWrapper.
	 */
	public function __unset( int|string $key ): void {
		throw new Exception( 'Cannot unset values directly on a MigrationObjectPropertyWrapper.' );
	}

	/**
	 * Magic method to serialize the object.
	 *
	 * @return array
	 */
	public function __serialize(): array {
		return [
			'path'     => $this->path,
			'current'  => $this->current,
			'property' => $this->property,
		];
	}

	/**
	 * Magic method to unserialize the object.
	 *
	 * @param array $data Serialized data.
	 *
	 * @return void
	 */
	public function __unserialize( array $data ): void {
		$this->path     = $data['path'];
		$this->current  = $data['current'];
		$this->property = $data['property'];
	}

	/**
	 * Magic method to convert the object to a string.
	 *
	 * @return string
	 */
	public function __toString() {
		$current_value = $this->get_value();

		if ( is_string( $current_value ) ) {
			return $current_value;
		}

		if ( is_bool( $current_value ) ) {
			return $current_value ? 'true' : 'false';
		}

		if ( is_int( $current_value ) ) {
			return (string) $current_value;
		}

		return '<mig_scaf><property path="' . $this->get_path() . '">' . wp_json_encode( $current_value ) . '</property></mig_scaf>';
	}

	/**
	 * Checks if an array is associative.
	 *
	 * @param array $arr Array to check.
	 *
	 * @return bool
	 */
	private function is_associative( array $arr ): bool {
		foreach ( array_keys( $arr ) as $key ) {
			if ( ! is_int( $key ) ) {
				return true;
			}
		}

		return false;
	}
}
