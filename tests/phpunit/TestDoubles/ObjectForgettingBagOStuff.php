<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * A cache that stores PHP-serialized blobs and cannot reconstruct the classes in them, so every
 * cached object comes back as an instance of __PHP_Incomplete_Class.
 *
 * Stands in for a persistent cache (Redis, memcached, APCu) read by a build whose classes no longer
 * match the ones an entry was written under. Refusing every class is stricter than such a shape
 * change, which yields a live object with uninitialized properties, so a value that survives this
 * holds no objects at all.
 */
class ObjectForgettingBagOStuff extends HashBagOStuff {

	protected function doGet( $key, $flags = 0, &$casToken = null ): mixed {
		$blob = parent::doGet( $key, $flags, $casToken );

		return is_string( $blob ) ? unserialize( $blob, [ 'allowed_classes' => false ] ) : $blob;
	}

	protected function doSet( $key, $value, $exptime = 0, $flags = 0 ): bool {
		return parent::doSet( $key, serialize( $value ), $exptime, $flags );
	}

}
