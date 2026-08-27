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

		for ( const relation of relations ) {
			targets.push( {
				propertyName: property.name.toString(),
				targetId: relation.target.text,
			} );
		}
	}

	return targets;
}
