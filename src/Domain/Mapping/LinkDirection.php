<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

/**
 * Which way a {@see NodeMapping}'s link triple runs between the node and its anchor — the parent node's
 * instance, or the Subject when there is no parent.
 */
enum LinkDirection: string {

	/**
	 * `<anchor> <linkPredicate> <node>` — the CIDOC-CRM `person crm:P98i_was_born birth`.
	 */
	case ToNode = 'toNode';

	/**
	 * `<node> <linkPredicate> <anchor>` — the EDM `aggregation edm:aggregatedCHO cho`, where the node
	 * wraps the entity it points at.
	 */
	case FromNode = 'fromNode';

}
