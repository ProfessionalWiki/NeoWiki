// The two questions the editor asks about the Subjects it is holding but has not written: which of
// them anything still points at, and in what order they may be written. Both are pure walks over
// relation statements, kept out of the dialog so they can be answered without mounting it.

import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { RelationValue } from '@/domain/Value.ts';

/**
 * The Subjects this one points at. The Schema decides what a relation is, so nothing points through
 * a statement under a property it does not declare as one — which is what lets both walks below
 * agree. Targets repeat where the Subject names one twice; both walks carry a visited set already.
 */
function relationTargetIds( subject: Subject, schema: Schema ): string[] {
	const statements = schema.statementsFrom( subject.getStatements() );
	const targetIds: string[] = [];

	for ( const property of schema.getPropertyDefinitions() ) {
		if ( property.type !== RelationType.typeName ) {
			continue;
		}

		const value = statements.get( property.name ).value;

		if ( value instanceof RelationValue ) {
			targetIds.push( ...value.relations.map( ( relation ) => relation.target.text ) );
		}
	}

	return targetIds;
}

/**
 * A Subject the editor is holding. `id` is the Subject's own id, which is what relations name, and
 * `isNew` marks one the wiki does not have yet.
 */
export interface HeldSubject {
	id: string;
	subject: Subject;
	schema: Schema;
	isNew: boolean;
}

/**
 * The ids reachable by relation from the Subjects the save is committed to writing, following
 * relations through the held Subjects as far as they go. A Subject the editor invented earns its
 * place here by being pointed at; one the user has since pointed away from does not, and a chain of
 * them stands or falls together.
 *
 * The Subjects the wiki already holds anchor the walk, because the save reaches them whatever
 * points at them. `committedIds` names the others: a Subject the wiki does not hold that this save
 * exists to write regardless, which is the subject creator's root. Without it a creator's whole
 * tree would be unanchored, since a Subject the editor invented cannot justify itself.
 */
export function reachableTargetIds(
	held: readonly HeldSubject[],
	committedIds: readonly string[] = [],
): Set<string> {
	const byId = new Map( held.map( ( entry ) => [ entry.id, entry ] ) );
	const reached = new Set<string>();
	const queue = held
		.filter( ( entry ) => !entry.isNew || committedIds.includes( entry.id ) )
		.map( ( entry ) => entry.id );

	while ( queue.length > 0 ) {
		const entry = byId.get( queue.pop() as string );

		if ( entry === undefined ) {
			continue;
		}

		for ( const targetId of relationTargetIds( entry.subject, entry.schema ) ) {
			if ( !reached.has( targetId ) ) {
				reached.add( targetId );
				queue.push( targetId );
			}
		}
	}

	return reached;
}

/**
 * The order the held Subjects may be written in: each invented Subject after every invented Subject
 * it points at, so no write ever names a target the wiki does not have yet, and the ones that
 * already exist last — a save that stops part way has then not pointed an existing Subject at a
 * target it failed to create. Depth-first, stopping at the visited check, which is what a cycle
 * between two invented Subjects reduces to; no order satisfies one, and the relation model allows it.
 *
 * `firstIds` names invented Subjects to write ahead of everything, relations notwithstanding: ones
 * the others depend on for something no relation expresses. The subject creator's root is that on
 * every route — the Subjects created alongside it are stored on the page its own write settles, so
 * none of them can be written until it has been. Its relations then name targets the wiki does not
 * have yet for the length of the save, which the Schema permits: a missing relation target is a
 * warning, never a refusal.
 */
export function writeOrder<T extends HeldSubject>(
	held: readonly T[],
	firstIds: readonly string[] = [],
): T[] {
	const byId = new Map( held.map( ( entry ) => [ entry.id, entry ] ) );
	const ordered: T[] = [];
	const visited = new Set<string>();

	// Pushed without walking its targets first, which is the whole point of naming it: those
	// targets follow it instead, through the ordinary walk below.
	function emitFirst( id: string ): void {
		const entry = byId.get( id );

		if ( entry === undefined || !entry.isNew || visited.has( id ) ) {
			return;
		}

		visited.add( id );
		ordered.push( entry );
	}

	function emitNew( id: string ): void {
		const entry = byId.get( id );

		if ( entry === undefined || !entry.isNew || visited.has( id ) ) {
			return;
		}

		visited.add( id );

		for ( const targetId of relationTargetIds( entry.subject, entry.schema ) ) {
			emitNew( targetId );
		}

		ordered.push( entry );
	}

	firstIds.forEach( emitFirst );
	held.forEach( ( entry ) => emitNew( entry.id ) );

	return [ ...ordered, ...held.filter( ( entry ) => !entry.isNew ) ];
}
