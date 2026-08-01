<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

/**
 * One Schema's entry within a page-level {@see Mapping}. The Schema it applies to is its key in
 * {@see Mapping::$schemas}, so it is not repeated here. The CURIEs used here are expanded against the
 * Mapping's page-level prefixes by a {@see CurieExpander} at validation and projection time.
 *
 * It covers both structural directions of OntologyMapping.md § What makes this hard:
 *
 *  - **Expansion**, for a target that mediates relationships through event nodes NeoWiki's data does not
 *    have: {@see $nodes} declares those nodes, and a {@see PropertyMapping::$node} attaches a property's
 *    values to one of them instead of to the Subject.
 *  - **Contraction**, for a flat target that does not want structure the data does carry:
 *    {@see $contributions} sends this Schema's own values to the Subjects it points at.
 *
 * An entry with neither is the near-1:1 term substitution the format started out as.
 */
readonly class SchemaMapping {

	/**
	 * @param SubjectMapping|null $subject How the Subject itself projects. Null for an entry that only
	 *   contributes to other Subjects, which then emits no type or label of its own.
	 * @param array<string, NodeMapping> $nodes Keyed by node key.
	 * @param array<string, PropertyMappings> $contributions Keyed by the name of a relation-typed
	 *   property on this Schema; every Subject that relation points at receives the mapped values. A
	 *   {@see PropertyMapping::$node} there has no meaning: contributed triples attach to the target
	 *   Subject, not to a synthesized node.
	 */
	public function __construct(
		public ?SubjectMapping $subject,
		public PropertyMappings $properties = new PropertyMappings(),
		public array $nodes = [],
		public array $contributions = [],
	) {
	}

}
