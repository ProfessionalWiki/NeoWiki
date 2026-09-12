import { describe, it, expect } from 'vitest';
import {
	pathToSubject,
	reachableSubjectIds,
	walkSubjectTree,
	type SubjectTreeWalkResult,
	type WalkNode,
} from '@/components/SubjectEditor/SubjectTreeWalk.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { newRelation, RelationValue } from '@/domain/Value.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';

// SubjectId's format (ADR 14) excludes '0'/'O'/'I'/'l', so these stand in for readable literals.
const A_ID = 'sAnode111111111';
const B_ID = 'sBnode211111111';
const C_ID = 'sCnode311111111';
const D_ID = 'sDnode411111111';
const E_ID = 'sEnode511111111';
const SHARED_ID = 'sShared11111111';

function relationsTo( property: string, ...targetIds: string[] ): Statement {
	return new Statement(
		new PropertyName( property ),
		'relation',
		new RelationValue( targetIds.map( ( id ) => newRelation( undefined, id ) ) ),
	);
}

function subjectWith(
	id: string,
	schemaName: string,
	label: string,
	...statements: Statement[]
): Subject {
	return newSubject( { id, label, schemaName, statements: new StatementList( statements ) } );
}

function relationSchema( name: string, ...properties: [ string, string ][] ): Schema {
	return newSchema( {
		title: name,
		properties: new PropertyDefinitionList( properties.map(
			( [ property, targetSchema ] ) => createPropertyDefinitionFromJson(
				property,
				{ type: 'relation', targetSchema },
			),
		) ),
	} );
}

// One Schema whose single relation property points back at its own kind, so chains and
// cycles need no second Schema.
const linkSchema = relationSchema( 'Link', [ 'Link', 'Link' ] );

// `isExpanded` defaults to expanding everything, which is what the walk did before expansion
// was an input: the traversal tests below are about paths and cycles, not about what is open.
function walk(
	rootSubject: Subject,
	rootSchema: Schema,
	fetched: Subject[],
	schemas: Schema[] = [ rootSchema ],
	edited: Subject[] = [],
	isExpanded: ( key: string ) => boolean = () => true,
): SubjectTreeWalkResult {
	const editedSubjects = new Map( edited.map( ( subject ) => [ subject.getId().text, subject ] ) );
	const fetchedSubjects = new Map( fetched.map( ( subject ) => [ subject.getId().text, subject ] ) );
	const fetchedSchemas = new Map( schemas.map( ( schema ) => [ schema.getName(), schema ] ) );

	return walkSubjectTree( {
		rootSubject,
		rootSchema,
		editedSubject: ( id ) => editedSubjects.get( id ),
		fetchedSubject: ( id ) => fetchedSubjects.get( id ),
		fetchedSchema: ( name ) => fetchedSchemas.get( name ),
		isExpanded,
	} );
}

function childrenOf( node: WalkNode ): WalkNode[] {
	return node.children;
}

function onlyChildOf( node: WalkNode ): WalkNode {
	const children = childrenOf( node );
	expect( children ).toHaveLength( 1 );
	return children[ 0 ];
}

function idsOf( node: WalkNode ): string[] {
	return [ node.subjectId, ...childrenOf( node ).flatMap( ( child ) => idsOf( child ) ) ];
}

function keysOf( node: WalkNode ): string[] {
	return [ node.key, ...childrenOf( node ).flatMap( ( child ) => keysOf( child ) ) ];
}

describe( 'walkSubjectTree', () => {

	describe( 'cycle termination', () => {

		it( 'stops a self-loop at its second occurrence', () => {
			// A --Link--> A.
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', A_ID ) );

			const result = walk( a, linkSchema, [ a ] );

			const child = onlyChildOf( result.root );
			expect( child.subjectId ).toBe( A_ID );
			expect( child.children ).toStrictEqual( [] );
		} );

		it( 'stops a two-hop cycle', () => {
			// A --Link--> B --Link--> A, with everything expanded, so only the visited set can be
			// what stops the walk here.
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', A_ID ) );

			const result = walk( a, linkSchema, [ a, b ] );

			const bNode = onlyChildOf( result.root );
			const closingA = onlyChildOf( bNode );
			expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID, A_ID ] );
			expect( closingA.children ).toStrictEqual( [] );
		} );

	} );

	it( 'gives each path to one Subject its own key', () => {
		// root --Left--> B --Shared--> S and root --Right--> C --Shared--> S: one Subject,
		// two paths, and the same property name on both, so the key has to carry the path.
		const diamondSchema = relationSchema( 'Diamond', [ 'Left', 'Branch' ], [ 'Right', 'Branch' ] );
		const branchSchema = relationSchema( 'Branch', [ 'Shared', 'Leaf' ] );
		const leafSchema = relationSchema( 'Leaf' );

		const root = subjectWith(
			A_ID,
			'Diamond',
			'Root',
			relationsTo( 'Left', B_ID ),
			relationsTo( 'Right', C_ID ),
		);
		const left = subjectWith( B_ID, 'Branch', 'Left branch', relationsTo( 'Shared', SHARED_ID ) );
		const right = subjectWith( C_ID, 'Branch', 'Right branch', relationsTo( 'Shared', SHARED_ID ) );
		const shared = subjectWith( SHARED_ID, 'Leaf', 'Shared' );

		const result = walk(
			root,
			diamondSchema,
			[ root, left, right, shared ],
			[ diamondSchema, branchSchema, leafSchema ],
		);

		const sharedKeys = keysOf( result.root ).filter(
			( key ) => key.endsWith( `Shared:${ SHARED_ID }` ),
		);
		expect( sharedKeys ).toHaveLength( 2 );
		// A `${propertyName}:${targetId}` key would make these two identical, since both
		// occurrences hang under a property named "Shared" and point at the same Subject.
		expect( new Set( sharedKeys ).size ).toBe( 2 );
		expect( new Set( keysOf( result.root ) ).size ).toBe( keysOf( result.root ).length );
	} );

	it( 'expands a Subject reached by a second path even after its first occurrence expanded', () => {
		// A --Knows--> B and A --Likes--> B, with B --Knows--> C: one converged-upon Subject
		// with a descendant of its own. Convergence is ordinary, not a cycle, so B expands on
		// both paths; a walk sharing one visited set across branches would show C under the
		// first B alone.
		const twoWaysSchema = relationSchema( 'TwoWays', [ 'Knows', 'TwoWays' ], [ 'Likes', 'TwoWays' ] );
		const a = subjectWith( A_ID, 'TwoWays', 'A', relationsTo( 'Knows', B_ID ), relationsTo( 'Likes', B_ID ) );
		const b = subjectWith( B_ID, 'TwoWays', 'B', relationsTo( 'Knows', C_ID ) );
		const c = subjectWith( C_ID, 'TwoWays', 'C' );

		const result = walk( a, twoWaysSchema, [ a, b, c ] );

		expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID, C_ID, B_ID, C_ID ] );
	} );

	// The form shows one slot per relation, so the same target can be picked twice under one
	// property; the tree shows related Subjects, and every key has to be unique tree-wide.
	describe( 'a target listed twice under one property', () => {
		const twiceSchema = relationSchema( 'Twice', [ 'Knows', 'Twice' ], [ 'Likes', 'Twice' ] );

		it( 'gets one node', () => {
			const a = subjectWith( A_ID, 'Twice', 'A', relationsTo( 'Knows', B_ID, B_ID ) );
			const b = subjectWith( B_ID, 'Twice', 'B' );

			const result = walk( a, twiceSchema, [ a, b ] );

			expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID ] );
		} );

		it( 'still gets a node under each property that lists it', () => {
			const a = subjectWith( A_ID, 'Twice', 'A', relationsTo( 'Knows', B_ID ), relationsTo( 'Likes', B_ID ) );
			const b = subjectWith( B_ID, 'Twice', 'B' );

			const result = walk( a, twiceSchema, [ a, b ] );

			expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID, B_ID ] );
		} );
	} );

	it( 'prefers the edited copy over the fetched Subject', () => {
		const root = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
		// What the server holds: an older label, and no relation of its own.
		const fetchedB = subjectWith( B_ID, 'Link', 'Stored B' );
		// What the editor holds: a renamed B that has since been given a relation.
		const editedB = subjectWith( B_ID, 'Link', 'Edited B', relationsTo( 'Link', C_ID ) );
		const c = subjectWith( C_ID, 'Link', 'C' );

		const result = walk( root, linkSchema, [ root, fetchedB, c ], [ linkSchema ], [ editedB ] );

		const bNode = onlyChildOf( result.root );
		expect( bNode.label ).toBe( 'Edited B' );
		expect( onlyChildOf( bNode ).subjectId ).toBe( C_ID );
	} );

	it( 'reports the Subjects and Schemas it could not resolve', () => {
		const root = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID, C_ID ) );
		// Resolved, but its Schema is not, so the walk cannot expand it. It holds a relation of
		// its own, or its Schema would be something the walk never needed to ask for.
		const b = subjectWith( B_ID, 'Unfetched', 'B', relationsTo( 'Link', D_ID ) );

		const result = walk( root, linkSchema, [ root, b ] );

		expect( result.missingSubjectIds ).toStrictEqual( [ C_ID ] );
		expect( result.missingSchemaNames ).toStrictEqual( [ 'Unfetched' ] );
		// Both still render: an unresolved target shows its raw id.
		expect( childrenOf( result.root ).map( ( node ) => node.label ) ).toStrictEqual( [ 'B', C_ID ] );
	} );

	// Four of these five draw no children, and a reader has to be able to tell them from a Subject
	// with nothing to show. A row's control, its mark and its aria-expanded all hang off this.
	describe( 'why a node has no children', () => {

		// A chain of five, so a test can leave a link unopened however far down it reaches.
		function chain(): Subject[] {
			return [
				subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) ),
				subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) ),
				subjectWith( C_ID, 'Link', 'C', relationsTo( 'Link', D_ID ) ),
				subjectWith( D_ID, 'Link', 'D', relationsTo( 'Link', E_ID ) ),
				subjectWith( E_ID, 'Link', 'E' ),
			];
		}

		it( 'says nothing for a node whose relations it followed', () => {
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			const b = subjectWith( B_ID, 'Link', 'B' );

			const result = walk( a, linkSchema, [ a, b ] );

			expect( result.root.frontier ).toBeUndefined();
			// A leaf is that same fact: followed, and there was nothing there.
			expect( onlyChildOf( result.root ).frontier ).toBeUndefined();
		} );

		it( 'marks a node that repeats one already on its path', () => {
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', A_ID ) );

			const result = walk( a, linkSchema, [ a, b ] );

			expect( onlyChildOf( onlyChildOf( result.root ) ).frontier ).toBe( 'repeated' );
		} );

		it( 'marks an unexpanded node with relations as collapsed', () => {
			const subjects = chain();

			const result = walk( subjects[ 0 ], linkSchema, subjects, [ linkSchema ], [], () => false );

			expect( onlyChildOf( result.root ).frontier ).toBe( 'collapsed' );
		} );

		it( 'marks a node whose Subject it does not hold', () => {
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );

			const result = walk( a, linkSchema, [ a ] );

			expect( onlyChildOf( result.root ).frontier ).toBe( 'subjectUnresolved' );
		} );

		it( 'marks a node whose Schema it does not hold', () => {
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			// Holding a relation, or the walk would settle it as a leaf without asking for the
			// Schema at all — a Schema can only narrow what the Statements hold.
			const b = subjectWith( B_ID, 'Unfetched', 'B', relationsTo( 'Link', C_ID ) );

			const result = walk( a, linkSchema, [ a, b ] );

			expect( onlyChildOf( result.root ).frontier ).toBe( 'schemaUnresolved' );
		} );

		it( 'tells a leaf apart from a node whose relations it declined to follow', () => {
			// The behaviour the field exists for. Both draw no children; only one of them means
			// there is nothing to draw.
			const subjects = chain();
			const leafOnly = [
				subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', E_ID ) ),
				subjectWith( E_ID, 'Link', 'E' ),
			];

			const collapsed = onlyChildOf(
				walk( subjects[ 0 ], linkSchema, subjects, [ linkSchema ], [], () => false ).root,
			);
			const trueLeaf = onlyChildOf( walk( leafOnly[ 0 ], linkSchema, leafOnly ).root );

			expect( collapsed.children ).toStrictEqual( trueLeaf.children );
			expect( collapsed.frontier ).not.toBe( trueLeaf.frontier );
		} );

	} );

	// The bound on the tree is what the reader has opened, not a number: a node is walked into
	// when its own key is expanded, so depth costs one deliberate act per level.
	describe( 'expansion', () => {

		// A --Link--> B --Link--> C.
		function chain(): Subject[] {
			return [
				subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) ),
				subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) ),
				subjectWith( C_ID, 'Link', 'C' ),
			];
		}

		it( 'draws the root\'s own relations however little is expanded', () => {
			const subjects = chain();

			const result = walk( subjects[ 0 ], linkSchema, subjects, [ linkSchema ], [], () => false );

			// The root does not collapse: it is the only way back to the Subject being edited.
			expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID ] );
			expect( result.root.frontier ).toBeUndefined();
		} );

		it( 'walks into a node whose own key is expanded, and no other', () => {
			const subjects = chain();

			const opened = walk(
				subjects[ 0 ], linkSchema, subjects, [ linkSchema ], [],
				( key ) => key === `root:${ A_ID }:Link:${ B_ID }`,
			);
			// The same graph with a key nothing matches: it is B's own key that drew C, not the
			// walk expanding whatever it could reach.
			const closed = walk(
				subjects[ 0 ], linkSchema, subjects, [ linkSchema ], [],
				( key ) => key === 'no key in this tree',
			);

			expect( idsOf( opened.root ) ).toStrictEqual( [ A_ID, B_ID, C_ID ] );
			expect( idsOf( closed.root ) ).toStrictEqual( [ A_ID, B_ID ] );
		} );

		it( 'expands one occurrence of a Subject without expanding the other', () => {
			// A --Knows--> B and A --Likes--> B, with B --Knows--> C. Expansion is keyed by path,
			// so opening one B must leave the other closed — the same Subject in two places is
			// two rows, and a key derived from the Subject id would open both.
			const twoWaysSchema = relationSchema( 'TwoWays', [ 'Knows', 'TwoWays' ], [ 'Likes', 'TwoWays' ] );
			const a = subjectWith( A_ID, 'TwoWays', 'A', relationsTo( 'Knows', B_ID ), relationsTo( 'Likes', B_ID ) );
			const b = subjectWith( B_ID, 'TwoWays', 'B', relationsTo( 'Knows', C_ID ) );
			const c = subjectWith( C_ID, 'TwoWays', 'C' );

			const result = walk(
				a, twoWaysSchema, [ a, b, c ], [ twoWaysSchema ], [],
				( key ) => key === `root:${ A_ID }:Knows:${ B_ID }`,
			);

			expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID, C_ID, B_ID ] );
			const [ knownB, likedB ] = childrenOf( result.root );
			expect( knownB.frontier ).toBeUndefined();
			expect( likedB.frontier ).toBe( 'collapsed' );
		} );

		it( 'asks for nothing behind a node it did not walk into', () => {
			// B is held and C is not. Collapsed, the walk never looks past B, so C is not named
			// as missing and nothing fetches it.
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) );

			const result = walk( a, linkSchema, [ a, b ], [ linkSchema ], [], () => false );

			expect( result.missingSubjectIds ).toStrictEqual( [] );
			// B got its node; nothing below it did, which is the other half of asking for nothing.
			expect( onlyChildOf( result.root ).children ).toStrictEqual( [] );
		} );

	} );

	describe( 'relation target count', () => {

		it( 'counts what a node points at, from its own Statements', () => {
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID, D_ID ) );

			const result = walk( a, linkSchema, [ a, b ], [ linkSchema ], [], () => false );

			expect( result.root.relationTargetCount ).toBe( 1 );
			expect( onlyChildOf( result.root ).relationTargetCount ).toBe( 2 );
		} );

		it( 'counts a node whose Schema it does not hold', () => {
			// Counted from the Statements rather than through the Schema, so a row can say it has
			// something under it before its Schema has been read.
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			const b = subjectWith( B_ID, 'Unfetched', 'B', relationsTo( 'Link', C_ID ) );

			const result = walk( a, linkSchema, [ a, b ] );

			expect( onlyChildOf( result.root ).relationTargetCount ).toBe( 1 );
		} );

		it( 'counts nothing for a node whose Subject it does not hold', () => {
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );

			const result = walk( a, linkSchema, [ a ] );

			expect( onlyChildOf( result.root ).relationTargetCount ).toBe( 0 );
		} );

		it( 'counts one target listed twice under one property once', () => {
			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID, B_ID ) );

			const result = walk( a, linkSchema, [ a ] );

			expect( result.root.relationTargetCount ).toBe( 1 );
		} );

	} );

} );

// What a caller opens a path with, so it answers over everything held rather than over what is
// drawn. One Schema throughout, because this reads Statements and never asks for one.
describe( 'pathToSubject', () => {

	function pathTo(
		subjectId: string,
		rootSubject: Subject,
		fetched: Subject[],
		edited: Subject[] = [],
		schemas: Schema[] = [ linkSchema ],
		rootSchema: Schema = linkSchema,
	): string[] {
		const editedSubjects = new Map( edited.map( ( subject ) => [ subject.getId().text, subject ] ) );
		const fetchedSubjects = new Map( fetched.map( ( subject ) => [ subject.getId().text, subject ] ) );
		const fetchedSchemas = new Map( schemas.map( ( schema ) => [ schema.getName(), schema ] ) );

		return pathToSubject( {
			rootSubject,
			rootSchema,
			editedSubject: ( id ) => editedSubjects.get( id ),
			fetchedSubject: ( id ) => fetchedSubjects.get( id ),
			fetchedSchema: ( name ) => fetchedSchemas.get( name ),
		}, subjectId );
	}

	// Spelled out rather than composed from the walk's own keys: these two are the contract
	// between what the walk builds and what the caller opens, and a divergence opens nothing.
	it( 'gives the root its own key', () => {
		expect( pathTo( A_ID, subjectWith( A_ID, 'Link', 'A' ), [] ) )
			.toStrictEqual( [ `root:${ A_ID }` ] );
	} );

	it( 'gives a key per hop, each carrying the whole path above it', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
		const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) );

		expect( pathTo( C_ID, a, [ b, subjectWith( C_ID, 'Link', 'C' ) ] ) ).toStrictEqual( [
			`root:${ A_ID }:Link:${ B_ID }`,
			`root:${ A_ID }:Link:${ B_ID }:Link:${ C_ID }`,
		] );
	} );

	// The guard that makes this terminate. Without it the search follows the cycle forever
	// rather than reporting that the Subject is nowhere under the root. The root is held as well
	// as being the root, or an unresolvable hop back to it would be what ends the search.
	it( 'reports nothing for a subject a cyclic graph never reaches', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
		const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', A_ID ) );

		expect( pathTo( C_ID, a, [ a, b ] ) ).toStrictEqual( [] );
	} );

	// Two ways down to one Subject, and nothing here says which the reader meant.
	it( 'takes the first path to a subject reachable by two', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID, C_ID ) );
		const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', SHARED_ID ) );
		const c = subjectWith( C_ID, 'Link', 'C', relationsTo( 'Link', SHARED_ID ) );

		expect( pathTo( SHARED_ID, a, [ b, c, subjectWith( SHARED_ID, 'Link', 'Shared' ) ] ) )
			.toStrictEqual( [
				`root:${ A_ID }:Link:${ B_ID }`,
				`root:${ A_ID }:Link:${ B_ID }:Link:${ SHARED_ID }`,
			] );
	} );

	// These keys are matched against the keys the walk mints, and the walk goes by the Schema. A
	// Statement it no longer declares is not a hop the walk can make, so a key for one matches
	// nothing and the Subject being edited gets no row at all.
	it( 'ignores a statement the schema does not declare when the schema is held', () => {
		const declaring = relationSchema( 'Declaring', [ 'Link', 'Link' ] );
		const root = subjectWith(
			A_ID,
			'Declaring',
			'A',
			relationsTo( 'Ghost', C_ID ),
			relationsTo( 'Link', B_ID ),
		);
		const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) );

		expect( pathTo( C_ID, root, [ b, subjectWith( C_ID, 'Link', 'C' ) ], [], [ declaring, linkSchema ], declaring ) )
			.toStrictEqual( [
				`root:${ A_ID }:Link:${ B_ID }`,
				`root:${ A_ID }:Link:${ B_ID }:Link:${ C_ID }`,
			] );
	} );

	// Held Schemas are what the walk goes by, and on the first render there are none: a path that
	// could not be found until its own branch was open would never open it.
	it( 'falls back to the statements where the schema is not held yet', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );

		expect( pathTo( B_ID, a, [ subjectWith( B_ID, 'Link', 'B' ) ], [], [] ) )
			.toStrictEqual( [ `root:${ A_ID }:Link:${ B_ID }` ] );
	} );

	// A relation picked but not yet saved has a node, so it must have a path too.
	it( 'follows a relation only the edited copy holds', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
		const fetchedB = subjectWith( B_ID, 'Link', 'B' );
		const editedB = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) );

		expect( pathTo( C_ID, a, [ fetchedB ], [ editedB ] ) ).toStrictEqual( [
			`root:${ A_ID }:Link:${ B_ID }`,
			`root:${ A_ID }:Link:${ B_ID }:Link:${ C_ID }`,
		] );
	} );
} );

// Whether a Subject has a place in this tree at all, which is not what the walk answers: the walk
// draws what is open, and a Subject under a closed row is out of sight rather than unreachable.
describe( 'reachableSubjectIds', () => {

	function reachableFrom(
		rootSubject: Subject,
		fetched: Subject[],
		schemas: Schema[] = [ linkSchema ],
		rootSchema: Schema = linkSchema,
	): string[] {
		const fetchedSubjects = new Map( fetched.map( ( subject ) => [ subject.getId().text, subject ] ) );
		const fetchedSchemas = new Map( schemas.map( ( schema ) => [ schema.getName(), schema ] ) );

		// Sorted: this answers a question about membership, and the order it happens to visit in
		// is not something a caller may rely on.
		return [ ...reachableSubjectIds( {
			rootSubject,
			rootSchema,
			editedSubject: () => undefined,
			fetchedSubject: ( id ) => fetchedSubjects.get( id ),
			fetchedSchema: ( name ) => fetchedSchemas.get( name ),
		} ) ].sort();
	}

	it( 'reaches as far down as the data it holds goes, whatever is open', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
		const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) );
		const c = subjectWith( C_ID, 'Link', 'C', relationsTo( 'Link', D_ID ) );

		expect( reachableFrom( a, [ b, c ] ) ).toStrictEqual( [ B_ID, C_ID, D_ID ] );
	} );

	// Visited once rather than once per path, which is what keeps this linear where the walk,
	// whose rows are per path, is not: without it a converging graph revisits exponentially.
	it( 'visits a subject reached by two paths once, and terminates on a cycle', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID, C_ID ) );
		const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', SHARED_ID ) );
		const c = subjectWith( C_ID, 'Link', 'C', relationsTo( 'Link', SHARED_ID ) );
		// Closing the loop back on the root, so nothing but the visited set ends this.
		const shared = subjectWith( SHARED_ID, 'Link', 'Shared', relationsTo( 'Link', A_ID ) );

		expect( reachableFrom( a, [ b, c, shared, a ] ) )
			.toStrictEqual( [ A_ID, B_ID, C_ID, SHARED_ID ] );
	} );

	// The walk stops at a node whose Schema it does not hold, so this has to stop there too, or
	// it would report as reachable a Subject the tree cannot draw a row for.
	it( 'stops where a schema is not held', () => {
		const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
		const b = subjectWith( B_ID, 'Unfetched', 'B', relationsTo( 'Link', C_ID ) );

		expect( reachableFrom( a, [ b ] ) ).toStrictEqual( [ B_ID ] );
	} );

	// The case the stray list exists for: a pane whose only link is one the Schema dropped is
	// unreachable, and has to be listed rather than quietly left without a row.
	it( 'does not reach through a statement the schema no longer declares', () => {
		const declaring = relationSchema( 'Declaring', [ 'Link', 'Link' ] );
		const a = subjectWith( A_ID, 'Declaring', 'A', relationsTo( 'Ghost', C_ID ) );

		expect( reachableFrom( a, [ subjectWith( C_ID, 'Link', 'C' ) ], [ declaring, linkSchema ], declaring ) )
			.toStrictEqual( [] );
	} );
} );
