import { describe, it, expect } from 'vitest';
import {
	TREE_DEPTH,
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

function walk(
	rootSubject: Subject,
	rootSchema: Schema,
	fetched: Subject[],
	schemas: Schema[] = [ rootSchema ],
	edited: Subject[] = [],
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

		it( 'stops a two-hop cycle inside the depth bound', () => {
			// A --Link--> B --Link--> A, so the closing A sits two hops from the root while the
			// cap allows three: only the visited set can be what stops the walk here.
			expect( TREE_DEPTH ).toBeGreaterThan( 2 );

			const a = subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) );
			const b = subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', A_ID ) );

			const result = walk( a, linkSchema, [ a, b ] );

			const bNode = onlyChildOf( result.root );
			const closingA = onlyChildOf( bNode );
			expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID, A_ID ] );
			expect( closingA.children ).toStrictEqual( [] );
		} );

	} );

	describe( 'depth cap', () => {

		// A chain of five: root --> 1 --> 2 --> 3 --> 4, one link longer than the cap allows.
		function chain(): Subject[] {
			return [
				subjectWith( A_ID, 'Link', 'A', relationsTo( 'Link', B_ID ) ),
				subjectWith( B_ID, 'Link', 'B', relationsTo( 'Link', C_ID ) ),
				subjectWith( C_ID, 'Link', 'C', relationsTo( 'Link', D_ID ) ),
				subjectWith( D_ID, 'Link', 'D', relationsTo( 'Link', E_ID ) ),
				subjectWith( E_ID, 'Link', 'E' ),
			];
		}

		it( 'renders the node at the limit', () => {
			const subjects = chain();

			const result = walk( subjects[ 0 ], linkSchema, subjects );

			expect( idsOf( result.root ) ).toStrictEqual( [ A_ID, B_ID, C_ID, D_ID ] );
		} );

		it( 'renders nothing past the limit', () => {
			const subjects = chain();

			const result = walk( subjects[ 0 ], linkSchema, subjects );

			expect( idsOf( result.root ) ).not.toContain( E_ID );
			expect( result.reachedIds.has( E_ID ) ).toBe( false );
			// The node at the cap renders, it just does not expand.
			const atCap = onlyChildOf( onlyChildOf( onlyChildOf( result.root ) ) );
			expect( atCap.subjectId ).toBe( D_ID );
			expect( atCap.children ).toStrictEqual( [] );
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
		// root --Left--> B --Shared--> S --Link--> E and root --Right--> C --Shared--> S: one
		// converged-upon Subject with a descendant of its own. Convergence is ordinary, not a
		// cycle, so S expands on both paths; a walk sharing one visited set across branches
		// would show E under the first S alone.
		const diamondSchema = relationSchema( 'Diamond', [ 'Left', 'Branch' ], [ 'Right', 'Branch' ] );
		const branchSchema = relationSchema( 'Branch', [ 'Shared', 'Link' ] );

		const root = subjectWith(
			A_ID,
			'Diamond',
			'Root',
			relationsTo( 'Left', B_ID ),
			relationsTo( 'Right', C_ID ),
		);
		const left = subjectWith( B_ID, 'Branch', 'Left branch', relationsTo( 'Shared', SHARED_ID ) );
		const right = subjectWith( C_ID, 'Branch', 'Right branch', relationsTo( 'Shared', SHARED_ID ) );
		const shared = subjectWith( SHARED_ID, 'Link', 'Shared', relationsTo( 'Link', E_ID ) );
		const leaf = subjectWith( E_ID, 'Link', 'Leaf' );

		const result = walk(
			root,
			diamondSchema,
			[ root, left, right, shared, leaf ],
			[ diamondSchema, branchSchema, linkSchema ],
		);

		expect( idsOf( result.root ) ).toStrictEqual(
			[ A_ID, B_ID, SHARED_ID, E_ID, C_ID, SHARED_ID, E_ID ],
		);
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
		// Resolved, but its Schema is not, so the walk cannot expand it.
		const b = subjectWith( B_ID, 'Unfetched', 'B' );

		const result = walk( root, linkSchema, [ root, b ] );

		expect( result.missingSubjectIds ).toStrictEqual( [ C_ID ] );
		expect( result.missingSchemaNames ).toStrictEqual( [ 'Unfetched' ] );
		// Both still render: an unresolved target shows its raw id.
		expect( childrenOf( result.root ).map( ( node ) => node.label ) ).toStrictEqual( [ 'B', C_ID ] );
		expect( result.reachedIds ).toStrictEqual( new Set( [ B_ID, C_ID ] ) );
	} );

} );
