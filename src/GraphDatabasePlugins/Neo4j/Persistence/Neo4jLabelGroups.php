<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

/**
 * Groups subjects by the set of labels to apply to them.
 *
 * Cypher cannot parameterize labels, so a label change needs one query per distinct set of labels.
 * Grouping keeps that count proportional to the number of Schemas involved rather than to the number
 * of Subjects. Subjects with no labels to apply are dropped, since they need no query at all.
 */
class Neo4jLabelGroups {

	/**
	 * @param array<string, string[]> $labelsBySubjectId
	 * @return list<array{labels: string[], subjectIds: string[]}>
	 */
	public static function build( array $labelsBySubjectId ): array {
		$groups = [];

		foreach ( $labelsBySubjectId as $subjectId => $labels ) {
			if ( $labels === [] ) {
				continue;
			}

			$labels = array_values( array_unique( $labels ) );
			sort( $labels );

			$key = implode( ':', $labels );

			$groups[$key] ??= [ 'labels' => $labels, 'subjectIds' => [] ];
			$groups[$key]['subjectIds'][] = (string)$subjectId;
		}

		return array_values( $groups );
	}

}
