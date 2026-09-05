// Nothing is fetched here. An unresolved target still gets a node, showing its raw id, and is
// named in `missingSubjectIds`; a resolved target whose Schema is missing gets a node that does
// not expand and is named in `missingSchemaNames`. The caller resolves those and walks again,
// and remembering which of those fetches failed is the caller's job too.

import { relationTargetsOf } from './SubjectTreeModel.ts';
import type { Subject } from '@/domain/Subject.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import type { Schema, SchemaName } from '@/domain/Schema.ts';

// Levels of relation targets to walk from the root: person -> birth event -> time span is 2.
export const TREE_DEPTH = 3;

// One Subject as the walk reached it, by one path. The same Subject reached by a second path
// is a second node, with its own key.
export interface WalkNode {
	key: string;
	subjectId: string;
	label: string;
	schemaName: string;
	// Whether `label` is the marked stand-in rather than a name anyone chose, which already names
	// the Schema and so makes a second Schema label on the row a repeat.
	nameIsGenerated: boolean;
	// The relation property this node hangs under; the root hangs under none. The children
	// of one property are contiguous, in the Schema's order.
	propertyName?: string;
	// Empty for a node that does not expand: a leaf, one closing a cycle, one at the depth cap,
	// or one still resolving.
	children: WalkNode[];
}

// A Subject the caller may not hold yet: an unresolved one shows its raw id.
export function nodeFor( key: string, subjectId: string, subject: Subject | undefined ): WalkNode {
	return {
		key,
		subjectId,
		label: subject === undefined ? subjectId : subjectDisplayName( subject ),
		schemaName: subject?.getSchemaName() ?? '',
		nameIsGenerated: subject?.hasGeneratedDisplayName() ?? false,
		children: [],
	};
}

// The lookups answer from what the caller already holds; they resolve nothing themselves.
export interface SubjectTreeWalkInput {
	rootSubject: Subject;
	rootSchema: Schema;
	// Preferred over the fetched Subject, so a relation picked but not yet saved has a node.
	editedSubject: ( id: string ) => Subject | undefined;
	fetchedSubject: ( id: string ) => Subject | undefined;
	fetchedSchema: ( name: SchemaName ) => Schema | undefined;
}

export interface SubjectTreeWalkResult {
	root: WalkNode;
	// Below the root, once per Subject however many nodes it got.
	reachedIds: Set<string>;
	// What the walk needed and did not have, in the order it ran into them.
	missingSubjectIds: string[];
	missingSchemaNames: SchemaName[];
}

export function walkSubjectTree( input: SubjectTreeWalkInput ): SubjectTreeWalkResult {
	const reachedIds = new Set<string>();
	const missingSubjectIds = new Set<string>();
	const missingSchemaNames = new Set<SchemaName>();

	function subjectFor( id: string ): Subject | undefined {
		return input.editedSubject( id ) ?? input.fetchedSubject( id );
	}

	// `pathKey` is the key of the node this expands, so each key carries its whole path. A
	// converging graph is ordinary, not a cycle — one place referenced from two events — and
	// keying off the Subject id alone would collide its two occurrences and all their
	// descendants.
	function childrenOf(
		subject: Subject,
		schema: Schema,
		depth: number,
		visited: ReadonlySet<string>,
		pathKey: string,
	): WalkNode[] {
		const children: WalkNode[] = [];

		for ( const { propertyName, targetId } of relationTargetsOf( subject, schema ) ) {
			const targetSubject = subjectFor( targetId );
			const node: WalkNode = {
				...nodeFor( `${ pathKey }:${ propertyName }:${ targetId }`, targetId, targetSubject ),
				propertyName,
			};

			reachedIds.add( targetId );
			children.push( node );

			if ( targetSubject === undefined ) {
				missingSubjectIds.add( targetId );
				continue;
			}

			// The node above stands; it just does not expand.
			if ( depth >= TREE_DEPTH || visited.has( targetId ) ) {
				continue;
			}

			const targetSchema = input.fetchedSchema( targetSubject.getSchemaName() );

			if ( targetSchema === undefined ) {
				missingSchemaNames.add( targetSubject.getSchemaName() );
				continue;
			}

			node.children = childrenOf(
				targetSubject,
				targetSchema,
				depth + 1,
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
			...nodeFor( `root:${ rootId }`, rootId, rootSubject ),
			children: childrenOf( rootSubject, input.rootSchema, 1, new Set( [ rootId ] ), rootId ),
		},
		reachedIds,
		missingSubjectIds: [ ...missingSubjectIds ],
		missingSchemaNames: [ ...missingSchemaNames ],
	};
}
