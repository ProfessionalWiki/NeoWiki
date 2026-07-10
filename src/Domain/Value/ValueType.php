<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

enum ValueType: string {

	case String = 'string';
	case Number = 'number';
	case Boolean = 'boolean';
	case Relation = 'relation';

	/**
	 * The Value's Property Type is not registered, so its structure is unknown.
	 *
	 * @see UnregisteredTypeValue
	 */
	case UnregisteredType = 'unregisteredType';

}
