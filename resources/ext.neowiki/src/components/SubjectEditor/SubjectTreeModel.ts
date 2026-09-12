// `relationTargetsOf` is called by both the tree's walk and the editor's navigator gate, so
// what one takes as a relation the other does too.

import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { RelationValue } from '@/domain/Value.ts';

export interface RelationTarget {
	propertyName: string;
	targetId: string;
}

// In the order the Schema declares its properties and each statement holds its relations, which
// is the order the tree prints and groups them in.
export function relationTargetsOf( subject: Subject, schema: Schema ): RelationTarget[] {
	const statements = schema.statementsFrom( subject.getStatements() );
	const targets: RelationTarget[] = [];

	for ( const property of schema.getPropertyDefinitions() ) {
		if ( property.type !== RelationType.typeName ) {
			continue;
		}

		const value = statements.get( property.name ).value;
		const relations = value instanceof RelationValue ? value.relations : [];
		// The form has one slot per relation, so a target can be picked twice under one
		// property; here it is one related Subject, and a second node would share a key.
		const seen = new Set<string>();

		for ( const relation of relations ) {
			if ( seen.has( relation.target.text ) ) {
				continue;
			}
			seen.add( relation.target.text );
			targets.push( {
				propertyName: property.name.toString(),
				targetId: relation.target.text,
			} );
		}
	}

	return targets;
}

/**
 * What a Subject points at, read from its own Statements and so without a Schema — which is what
 * lets a closed row carry a count before anything below it has been fetched. The server derives a
 * Subject's references the same way, from its Relation values alone.
 *
 * The cost: a relation stored under a property the Schema no longer declares is counted here and
 * dropped by `relationTargetsOf`, so such a row counts one more than it can ever show.
 *
 * In statement order, which is the Schema's order for anything the subject editor saved.
 */
export function relationTargetsFrom( subject: Subject ): RelationTarget[] {
	const targets: RelationTarget[] = [];
	const seen = new Set<string>();

	for ( const statement of subject.getStatements() ) {
		if ( !( statement.value instanceof RelationValue ) ) {
			continue;
		}

		for ( const relation of statement.value.relations ) {
			// Deduplicated per property, as the nodes are: one Subject filling two relation
			// properties is two rows, and filling one of them twice is one.
			const key = `${ statement.propertyName.toString() }:${ relation.target.text }`;

			if ( seen.has( key ) ) {
				continue;
			}

			seen.add( key );
			targets.push( {
				propertyName: statement.propertyName.toString(),
				targetId: relation.target.text,
			} );
		}
	}

	return targets;
}
