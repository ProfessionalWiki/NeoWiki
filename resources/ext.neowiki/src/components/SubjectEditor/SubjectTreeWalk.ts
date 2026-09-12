// Nothing is fetched here. The walk names what it needed and did not have; the caller resolves
// those, remembers which fetches failed, and walks again.

import { relationTargetsFrom, relationTargetsOf } from './SubjectTreeModel.ts';
import type { Subject } from '@/domain/Subject.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import type { Schema, SchemaName } from '@/domain/Schema.ts';

/**
 * Why a node holds fewer children than its Subject has relations.
 *
 * Absent where the walk followed them, a leaf included: `frontier === undefined` is the only
 * thing that means `children` is the whole truth about a node.
 */
export type WalkFrontier =
	// Nobody has opened it. The one frontier a reader can do something about.
	| 'collapsed'
	// The target already sits on this node's own path, so opening it would repeat a row above.
	| 'repeated'
	// Its Subject is not held, so which of its statements are relations is unknown.
	| 'subjectUnresolved'
	// Its Subject is held and its Schema is not, which leaves the same question open.
	| 'schemaUnresolved';

// One Subject as the walk reached it, by one path. The same Subject reached by a second path is
// a second node, with its own key.
export interface WalkNode {
	key: string;
	subjectId: string;
	label: string;
	// The relation property this node hangs under; the root hangs under none. One property's
	// children are contiguous, in the Schema's order.
	propertyName?: string;
	children: WalkNode[];
	frontier?: WalkFrontier;
	// Set only where the walk holds the Subject, which is how the caller tells a Schema still
	// being fetched from one that could not be read.
	schemaName?: SchemaName;
	// What the Subject points at, drawn or not. Zero also for a Subject the walk does not hold,
	// whose relations it cannot know: `frontier` is what tells those two apart.
	relationTargetCount: number;
}

/**
 * A key carries its whole path, because one Subject referenced from two places is two rows and
 * keying off its id alone would collapse them into one.
 *
 * Spelled here alone: these keys are matched against each other across the walk, the path search
 * and the reader's recorded gestures, and a divergence opens nothing and says nothing.
 */
export function rootKey( rootId: string ): string {
	return `root:${ rootId }`;
}

export function childKey( pathKey: string, propertyName: string, targetId: string ): string {
	return `${ pathKey }:${ propertyName }:${ targetId }`;
}

// A Subject the caller may not hold yet; an unresolved one shows its raw id.
export function nodeFor( key: string, subjectId: string, subject: Subject | undefined ): WalkNode {
	return {
		key,
		subjectId,
		label: subject === undefined ? subjectId : subjectDisplayName( subject ),
		children: [],
		relationTargetCount: subject === undefined ? 0 : relationTargetsFrom( subject ).length,
	};
}

// The lookups answer from what the caller already holds; they resolve nothing themselves.
export interface SubjectLookups {
	rootSubject: Subject;
	rootSchema: Schema;
	// Preferred over the fetched Subject, so a relation picked but not yet saved has a node.
	editedSubject: ( id: string ) => Subject | undefined;
	fetchedSubject: ( id: string ) => Subject | undefined;
	fetchedSchema: ( name: SchemaName ) => Schema | undefined;
}

export interface SubjectTreeWalkInput extends SubjectLookups {
	// The whole bound on the tree: an unopened node is not walked into, so nothing behind it is
	// drawn, counted or asked for. The root is never asked about, since it never closes.
	isExpanded: ( key: string ) => boolean;
}

export interface SubjectTreeWalkResult {
	root: WalkNode;
	missingSubjectIds: string[];
	missingSchemaNames: SchemaName[];
}

export function walkSubjectTree( input: SubjectTreeWalkInput ): SubjectTreeWalkResult {
	const missingSubjectIds = new Set<string>();
	const missingSchemaNames = new Set<SchemaName>();

	function subjectFor( id: string ): Subject | undefined {
		return input.editedSubject( id ) ?? input.fetchedSubject( id );
	}

	function childrenOf(
		subject: Subject,
		schema: Schema,
		visited: ReadonlySet<string>,
		pathKey: string,
	): WalkNode[] {
		const children: WalkNode[] = [];

		for ( const { propertyName, targetId } of relationTargetsOf( subject, schema ) ) {
			const targetSubject = subjectFor( targetId );
			const node: WalkNode = {
				...nodeFor( childKey( pathKey, propertyName, targetId ), targetId, targetSubject ),
				propertyName,
			};

			node.schemaName = targetSubject?.getSchemaName();
			children.push( node );

			if ( targetSubject === undefined ) {
				missingSubjectIds.add( targetId );
				node.frontier = 'subjectUnresolved';
				continue;
			}

			// Ahead of the closed check where both apply: a repeat says something permanent about
			// the graph, being closed only that nobody has opened it yet.
			if ( visited.has( targetId ) ) {
				node.frontier = 'repeated';
				continue;
			}

			// A Schema can only narrow what the Statements hold, so this is a leaf under any of
			// them. Settled before the Schema read below, or every leaf fetches a Schema it
			// cannot use and draws a disclosure control until the answer lands.
			if ( node.relationTargetCount === 0 ) {
				continue;
			}

			// Nothing below here is drawn, and nothing below here is asked for: the Schema read
			// and the targets' own reads both hang off this guard.
			if ( !input.isExpanded( node.key ) ) {
				node.frontier = 'collapsed';
				continue;
			}

			const targetSchema = input.fetchedSchema( targetSubject.getSchemaName() );

			if ( targetSchema === undefined ) {
				missingSchemaNames.add( targetSubject.getSchemaName() );
				node.frontier = 'schemaUnresolved';
				continue;
			}

			node.children = childrenOf(
				targetSubject,
				targetSchema,
				new Set( [ ...visited, targetId ] ),
				node.key,
			);
		}

		return children;
	}

	// The root as the editor holds it, so a relation picked in the root's own form has a node
	// before it is saved.
	const rootSubject = input.editedSubject( input.rootSubject.getId().text ) ?? input.rootSubject;
	const rootId = rootSubject.getId().text;

	return {
		root: {
			...nodeFor( rootKey( rootId ), rootId, rootSubject ),
			children: childrenOf( rootSubject, input.rootSchema, new Set( [ rootId ] ), rootKey( rootId ) ),
		},
		missingSubjectIds: [ ...missingSubjectIds ],
		missingSchemaNames: [ ...missingSchemaNames ],
	};
}

/**
 * Every Subject the tree could draw a row for, if every row in it were open.
 *
 * One shared visited set, where the walk keeps one per path: a Subject reachable by two paths is
 * reachable, which is what keeps this linear over a converging graph.
 *
 * Through the Schemas, as the walk goes, so a link the Schemas no longer declare counts as no
 * link at all.
 */
export function reachableSubjectIds( input: SubjectLookups ): Set<string> {
	const rootId = input.rootSubject.getId().text;
	const reached = new Set<string>();

	function visit( subject: Subject, schema: Schema | undefined ): void {
		// The walk stops at a node whose Schema is not held, so this has to stop there too.
		if ( schema === undefined ) {
			return;
		}

		for ( const { targetId } of relationTargetsOf( subject, schema ) ) {
			if ( reached.has( targetId ) ) {
				continue;
			}

			reached.add( targetId );
			const targetSubject = input.editedSubject( targetId ) ?? input.fetchedSubject( targetId );

			if ( targetSubject !== undefined ) {
				visit( targetSubject, input.fetchedSchema( targetSubject.getSchemaName() ) );
			}
		}
	}

	visit( input.editedSubject( rootId ) ?? input.rootSubject, input.rootSchema );

	return reached;
}

/**
 * The keys of the nodes from the root down to the first one holding this Subject, that node
 * included — or nothing, where the Subject is not reachable from the root at all.
 *
 * Expansion is ignored: this is what a caller opens a path WITH, so it cannot depend on the path
 * already being open. It reads only what the caller holds, so it can run on the first render.
 *
 * The first occurrence, in the order the walk would draw them: one Subject can sit at several
 * places in the tree, and nothing here says which of them the reader means.
 */
export function pathToSubject( input: SubjectLookups, subjectId: string ): string[] {
	const rootSubject = input.editedSubject( input.rootSubject.getId().text ) ?? input.rootSubject;
	const rootId = rootSubject.getId().text;

	if ( subjectId === rootId ) {
		return [ rootKey( rootId ) ];
	}

	// Through the Schema wherever one is held, since a Statement it no longer declares is not a
	// hop the walk can make, and a key for one matches nothing. Falling back to the Statements
	// where none is held — every node, on the first render — because a path that could not be
	// found until its own branch was open would never open it.
	function descend(
		subject: Subject,
		schema: Schema | undefined,
		visited: ReadonlySet<string>,
		pathKey: string,
		above: string[],
	): string[] | null {
		const targets = schema === undefined ?
			relationTargetsFrom( subject ) :
			relationTargetsOf( subject, schema );

		for ( const { propertyName, targetId } of targets ) {
			const key = childKey( pathKey, propertyName, targetId );

			if ( targetId === subjectId ) {
				return [ ...above, key ];
			}

			const targetSubject = input.editedSubject( targetId ) ?? input.fetchedSubject( targetId );

			if ( targetSubject === undefined || visited.has( targetId ) ) {
				continue;
			}

			const found = descend(
				targetSubject,
				input.fetchedSchema( targetSubject.getSchemaName() ),
				new Set( [ ...visited, targetId ] ),
				key,
				[ ...above, key ],
			);

			if ( found !== null ) {
				return found;
			}
		}

		return null;
	}

	return descend( rootSubject, input.rootSchema, new Set( [ rootId ] ), rootKey( rootId ), [] ) ?? [];
}
