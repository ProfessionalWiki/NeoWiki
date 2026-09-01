import { describe, it, expect } from 'vitest';
import {
	reachableTargetIds,
	writeOrder,
	type HeldSubject,
} from '@/components/SubjectEditor/SubjectDraftGraph.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { newRelation, RelationValue } from '@/domain/Value.ts';

// SubjectId's format (ADR 14) excludes '0'/'O'/'I'/'l', so these stand in for readable literals.
const SAVED_ID = 'sSaved111111111';
const OTHER_SAVED_ID = 'sSaved211111111';
const A_ID = 'sAdraft11111111';
const B_ID = 'sBdraft21111111';
const C_ID = 'sCdraft31111111';

// One Schema whose single relation property points back at its own kind, so chains and cycles
// need no second Schema.
const linkSchema = newSchema( {
	title: 'Link',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Link', { type: 'relation', targetSchema: 'Link' } ),
	] ),
} );

function held( id: string, isNew: boolean, ...targetIds: string[] ): HeldSubject {
	const statements = targetIds.length === 0 ? [] : [ new Statement(
		new PropertyName( 'Link' ),
		'relation',
		new RelationValue( targetIds.map( ( targetId ) => newRelation( undefined, targetId ) ) ),
	) ];

	return {
		id,
		subject: newSubject( { id, schemaName: 'Link', statements: new StatementList( statements ) } ),
		schema: linkSchema,
		isNew,
	};
}

function idsOf( entries: readonly HeldSubject[] ): string[] {
	return entries.map( ( entry ) => entry.id );
}

describe( 'reachableTargetIds', () => {
	it( 'reaches a draft the saved Subject points at', () => {
		const reached = reachableTargetIds( [ held( SAVED_ID, false, A_ID ), held( A_ID, true ) ] );

		expect( [ ...reached ] ).toEqual( [ A_ID ] );
	} );

	it( 'leaves out a draft nothing points at', () => {
		const reached = reachableTargetIds( [ held( SAVED_ID, false ), held( A_ID, true ) ] );

		expect( reached.has( A_ID ) ).toBe( false );
	} );

	it( 'reaches a draft only another draft points at, as long as that one is reached', () => {
		const reached = reachableTargetIds( [
			held( SAVED_ID, false, A_ID ),
			held( A_ID, true, B_ID ),
			held( B_ID, true ),
		] );

		expect( [ ...reached ].sort() ).toEqual( [ A_ID, B_ID ].sort() );
	} );

	it( 'leaves out a chain of drafts whose head nothing points at', () => {
		const reached = reachableTargetIds( [
			held( SAVED_ID, false ),
			held( A_ID, true, B_ID ),
			held( B_ID, true ),
		] );

		expect( reached.size ).toBe( 0 );
	} );

	it( 'reaches a draft pointed at from a saved Subject that is not the first one held', () => {
		const reached = reachableTargetIds( [
			held( SAVED_ID, false ),
			held( OTHER_SAVED_ID, false, A_ID ),
			held( A_ID, true ),
		] );

		expect( reached.has( A_ID ) ).toBe( true );
	} );

	it( 'reaches a draft two saved Subjects both point at, once', () => {
		const reached = reachableTargetIds( [
			held( SAVED_ID, false, A_ID ),
			held( OTHER_SAVED_ID, false, A_ID ),
			held( A_ID, true ),
		] );

		expect( [ ...reached ] ).toEqual( [ A_ID ] );
	} );

	it( 'terminates on a cycle between two drafts', () => {
		const reached = reachableTargetIds( [
			held( SAVED_ID, false, A_ID ),
			held( A_ID, true, B_ID ),
			held( B_ID, true, A_ID ),
		] );

		expect( [ ...reached ].sort() ).toEqual( [ A_ID, B_ID ].sort() );
	} );
} );

describe( 'writeOrder', () => {
	it( 'writes a draft before the Subject pointing at it', () => {
		const ordered = writeOrder( [ held( SAVED_ID, false, A_ID ), held( A_ID, true ) ] );

		expect( idsOf( ordered ) ).toEqual( [ A_ID, SAVED_ID ] );
	} );

	it( 'writes a pointed-at draft before the draft pointing at it', () => {
		const ordered = writeOrder( [
			held( SAVED_ID, false, A_ID ),
			held( A_ID, true, B_ID ),
			held( B_ID, true ),
		] );

		expect( idsOf( ordered ) ).toEqual( [ B_ID, A_ID, SAVED_ID ] );
	} );

	it( 'orders a chain of three drafts from the far end back', () => {
		const ordered = writeOrder( [
			held( SAVED_ID, false, A_ID ),
			held( A_ID, true, B_ID ),
			held( B_ID, true, C_ID ),
			held( C_ID, true ),
		] );

		expect( idsOf( ordered ) ).toEqual( [ C_ID, B_ID, A_ID, SAVED_ID ] );
	} );

	it( 'writes a draft two drafts share only once', () => {
		const ordered = writeOrder( [
			held( SAVED_ID, false ),
			held( A_ID, true, C_ID ),
			held( B_ID, true, C_ID ),
			held( C_ID, true ),
		] );

		expect( idsOf( ordered ) ).toEqual( [ C_ID, A_ID, B_ID, SAVED_ID ] );
	} );

	it( 'terminates on a cycle between two drafts, writing each once', () => {
		const ordered = writeOrder( [
			held( SAVED_ID, false, A_ID ),
			held( A_ID, true, B_ID ),
			held( B_ID, true, A_ID ),
		] );

		expect( idsOf( ordered ).sort() ).toEqual( [ A_ID, B_ID, SAVED_ID ].sort() );
		expect( ordered ).toHaveLength( 3 );
	} );

	it( 'writes every Subject the wiki already holds after every draft', () => {
		const ordered = writeOrder( [
			held( SAVED_ID, false, A_ID ),
			held( A_ID, true ),
			held( OTHER_SAVED_ID, false ),
		] );

		expect( idsOf( ordered ) ).toEqual( [ A_ID, SAVED_ID, OTHER_SAVED_ID ] );
	} );

	it( 'keeps the order it was given among Subjects that need none', () => {
		const ordered = writeOrder( [ held( SAVED_ID, false ), held( OTHER_SAVED_ID, false ) ] );

		expect( idsOf( ordered ) ).toEqual( [ SAVED_ID, OTHER_SAVED_ID ] );
	} );
} );
