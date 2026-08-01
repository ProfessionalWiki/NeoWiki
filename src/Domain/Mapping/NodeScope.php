<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

/**
 * How many instances of a {@see NodeMapping} a Subject gets.
 */
enum NodeScope: string {

	/**
	 * One instance per Subject, shared by every property that attaches to the node — the CIDOC-CRM
	 * `E67_Birth` that a birth date and a birth place both hang off.
	 */
	case Subject = 'subject';

	/**
	 * One instance per value of the single property that attaches to the node — the `E12_Production`
	 * that each Creator of an Artwork gets.
	 */
	case Value = 'value';

}
