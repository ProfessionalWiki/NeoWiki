// The two questions the editor asks about the Subjects it is holding but has not written: which of
// them anything still points at, and in what order they may be written. Both are pure walks over
// relation statements, kept out of the dialog so they can be answered without mounting it.

import { relationTargetsOf } from './SubjectTreeModel.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';

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
 * The ids reachable by relation from the Subjects the wiki already holds, following relations
 * through the held Subjects as far as they go. A Subject the editor invented earns its place here
 * by being pointed at; one the user has since pointed away from does not, and a chain of them
 * stands or falls together. The Subjects that already exist are the only anchors: a Subject the
 * editor invented cannot justify itself.
 */
export function reachableTargetIds( held: readonly HeldSubject[] ): Set<string> {
	const byId = new Map( held.map( ( entry ) => [ entry.id, entry ] ) );
	const reached = new Set<string>();
	const queue = held.filter( ( entry ) => !entry.isNew ).map( ( entry ) => entry.id );

	while ( queue.length > 0 ) {
		const entry = byId.get( queue.pop() as string );

		if ( entry === undefined ) {
			continue;
		}

		for ( const { targetId } of relationTargetsOf( entry.subject, entry.schema ) ) {
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
 */
export function writeOrder<T extends HeldSubject>( held: readonly T[] ): T[] {
	const byId = new Map( held.map( ( entry ) => [ entry.id, entry ] ) );
	const ordered: T[] = [];
	const visited = new Set<string>();

	function emitNew( id: string ): void {
		const entry = byId.get( id );

		if ( entry === undefined || !entry.isNew || visited.has( id ) ) {
			return;
		}

		visited.add( id );

		for ( const { targetId } of relationTargetsOf( entry.subject, entry.schema ) ) {
			emitNew( targetId );
		}

		ordered.push( entry );
	}

	held.forEach( ( entry ) => emitNew( entry.id ) );

	return [ ...ordered, ...held.filter( ( entry ) => !entry.isNew ) ];
}
