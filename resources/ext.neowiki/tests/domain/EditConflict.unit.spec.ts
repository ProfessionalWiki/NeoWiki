import { describe, expect, it } from 'vitest';
import { EditConflict, holdsLists, type Change, type Choice } from '@/domain/EditConflict';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { newMonolingualTextValue, newRelation, newStringValue, RelationValue } from '@/domain/Value';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { TextType } from '@/domain/propertyTypes/Text';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation';
import { Subject } from '@/domain/Subject';
import { newSubject } from '@/TestHelpers';

const HOST = 's11111111111111';
const DEPENDENT = 's11111111111112';

function text( propertyName: string, ...parts: string[] ): Statement {
	return new Statement( new PropertyName( propertyName ), TextType.typeName, newStringValue( ...parts ) );
}

function host( ...statements: Statement[] ): Subject {
	return newSubject( { id: HOST, label: 'ACME Inc', statements: new StatementList( statements ) } );
}

function hostWithDependent( ...statements: Statement[] ): Subject {
	return host(
		...statements,
		new Statement(
			new PropertyName( 'Attendance figures' ),
			RelationType.typeName,
			new RelationValue( [ newRelation( 'r11111111111111', DEPENDENT ) ] ),
		),
	);
}

const listProperty = (): PropertyDefinition => newRelationProperty( { name: 'Attendance figures', multiple: true } );

const OTHER = 's11111111111113';

function relationTo( propertyName: string, ...targets: string[] ): Statement {
	return new Statement(
		new PropertyName( propertyName ),
		RelationType.typeName,
		new RelationValue( targets.map( ( target ) => newRelation( `r${ target.slice( 1 ) }`, target ) ) ),
	);
}

function figure( id: string, ...statements: Statement[] ): Subject {
	return newSubject( { id, label: null, statements: new StatementList( statements ) } );
}

function relationTargets( subject: Subject, propertyName: string ): string[] {
	const name = new PropertyName( propertyName );

	return subject.getStatements().has( name ) ?
		( subject.getStatementValue( name ) as RelationValue ).targetIds.map( ( id ) => id.text ) :
		[];
}

function idsOf( subjects: Subject[] ): string[] {
	return subjects.map( ( subject ) => subject.getId().text );
}

function names( changes: readonly Change[] ): string[] {
	return changes.map( ( change ) => change.kind === 'statement' ? change.propertyName : `${ change.kind } ${ change.subjectId }` );
}

function choose( conflict: EditConflict, choice: Choice ): Map<string, Choice> {
	return new Map( conflict.bothChanged.map( ( change ) => [ change.key, choice ] ) );
}

function statementsOf( subjects: Subject[], id: string ): Record<string, string[]> {
	const subject = subjects.find( ( candidate ) => candidate.getId().text === id );

	return Object.fromEntries(
		[ ...subject?.getStatements() ?? [] ]
			.filter( ( statement ) => statement.value !== undefined && 'parts' in statement.value )
			.map( ( statement ) => [ statement.propertyName.toString(), ( statement.value as { parts: string[] } ).parts ] ),
	);
}

function attendanceTargets( subject: Subject ): string[] {
	return relationTargets( subject, 'Attendance figures' );
}

describe( 'EditConflict', () => {

	describe( 'sorting the changes', () => {

		const conflict = EditConflict.between(
			[ host( text( 'Founded', '2005' ), text( 'Progress', '42' ), text( 'Status', 'Active' ), text( 'Public', 'No' ) ) ],
			[ host( text( 'Founded', '2004' ), text( 'Progress', '57' ), text( 'Status', 'Active' ), text( 'Public', 'Yes' ) ) ],
			[ host( text( 'Founded', '2006' ), text( 'Progress', '42' ), text( 'Status', 'Acquired' ), text( 'Public', 'Yes' ) ) ],
		);

		it( 'asks only about a Statement both changed differently', () => {
			expect( names( conflict.bothChanged ) ).toEqual( [ 'Founded' ] );
		} );

	} );

	it( 'asks about a Dependent Subject they removed that you changed', () => {
		const conflict = EditConflict.between(
			[ hostWithDependent(), figure( DEPENDENT, text( 'Source', 'a' ) ) ],
			[ host() ],
			[ hostWithDependent(), figure( DEPENDENT, text( 'Source', 'b' ) ) ],
		);

		expect( names( conflict.bothChanged ) ).toEqual( [ `subject ${ DEPENDENT }` ] );
	} );

	it( 'asks about a Dependent Subject they removed that you added a field to', () => {
		const conflict = EditConflict.between(
			[ hostWithDependent(), figure( DEPENDENT, text( 'Year', '2021' ) ) ],
			[ host() ],
			[ hostWithDependent(), figure( DEPENDENT, text( 'Year', '2021' ), text( 'Source', 'b' ) ) ],
		);

		expect( names( conflict.bothChanged ) ).toEqual( [ `subject ${ DEPENDENT }` ] );
	} );

	it( 'names the relation that holds a Dependent Subject', () => {
		const conflict = EditConflict.between(
			[ host() ],
			[ hostWithDependent(), figure( DEPENDENT, text( 'Year', '2025' ) ) ],
			[ host( text( 'Founded', '1798' ) ) ],
		);

		expect( conflict.holderOf( DEPENDENT ) ).toEqual( { subjectId: HOST, propertyName: 'Attendance figures' } );
	} );

	it( 'offers both lists for texts but not for text in several languages', () => {
		const monolingual = new Statement( new PropertyName( 'Name' ), MonolingualTextType.typeName, newMonolingualTextValue( [ { text: 'A', language: 'en' } ] ) );
		const changes = EditConflict.between(
			[ host( text( 'Websites', 'a' ), monolingual ) ],
			[ host( text( 'Websites', 'b' ), monolingual.withValue( newMonolingualTextValue( [ { text: 'B', language: 'en' } ] ) ) ) ],
			[ host( text( 'Websites', 'c' ), monolingual.withValue( newMonolingualTextValue( [ { text: 'C', language: 'en' } ] ) ) ) ],
		).bothChanged;

		expect( changes.map( holdsLists ) ).toEqual( [ true, false ] );
	} );

	describe( 'resolving', () => {

		it( 'takes their value where only they changed a Statement and yours where only you did', () => {
			const resolved = EditConflict.between(
				[ host( text( 'Progress', '42' ), text( 'Status', 'Active' ) ) ],
				[ host( text( 'Progress', '57' ), text( 'Status', 'Active' ) ) ],
				[ host( text( 'Progress', '42' ), text( 'Status', 'Acquired' ) ) ],
			).resolve( new Map(), listProperty );

			expect( statementsOf( resolved, HOST ) ).toEqual( { Progress: [ '57' ], Status: [ 'Acquired' ] } );
		} );

		it( 'takes the value chosen where both changed a Statement', () => {
			const conflict = EditConflict.between(
				[ host( text( 'Founded', '2005' ) ) ],
				[ host( text( 'Founded', '2004' ) ) ],
				[ host( text( 'Founded', '2006' ) ) ],
			);

			expect( statementsOf( conflict.resolve( choose( conflict, 'theirs' ), listProperty ), HOST ) ).toEqual( { Founded: [ '2004' ] } );
			expect( statementsOf( conflict.resolve( choose( conflict, 'yours' ), listProperty ), HOST ) ).toEqual( { Founded: [ '2006' ] } );
		} );

		it( 'applies both sides\' changes to a list when both are chosen', () => {
			const conflict = EditConflict.between(
				[ host( text( 'Websites', 'acme.example', 'old.acme.example' ) ) ],
				[ host( text( 'Websites', 'acme.example', 'shop.acme.example' ) ) ],
				[ host( text( 'Websites', 'old.acme.example', 'shop.acme.example', 'blog.acme.example' ) ) ],
			);

			expect( statementsOf( conflict.resolve( choose( conflict, 'both' ), listProperty ), HOST ) ).toEqual( {
				Websites: [ 'shop.acme.example', 'blog.acme.example' ],
			} );
		} );

		it( 'keeps a Dependent Subject they removed, and the relation to it, when yours is chosen', () => {
			const conflict = EditConflict.between(
				[ hostWithDependent(), figure( DEPENDENT, text( 'Source', 'a' ) ) ],
				[ host() ],
				[ hostWithDependent(), figure( DEPENDENT, text( 'Source', 'b' ) ) ],
			);

			const resolved = conflict.resolve( choose( conflict, 'yours' ), listProperty );

			expect( statementsOf( resolved, DEPENDENT ) ).toEqual( { Source: [ 'b' ] } );
			expect( attendanceTargets( resolved[ 0 ] ) ).toEqual( [ DEPENDENT ] );
		} );

		it( 'keeps a Dependent Subject they added, and the relation to it', () => {
			const resolved = EditConflict.between(
				[ host() ],
				[ hostWithDependent(), figure( DEPENDENT, text( 'Year', '2025' ) ) ],
				[ host( text( 'Founded', '1798' ) ) ],
			).resolve( new Map(), listProperty );

			expect( statementsOf( resolved, DEPENDENT ) ).toEqual( { Year: [ '2025' ] } );
			expect( attendanceTargets( resolved[ 0 ] ) ).toEqual( [ DEPENDENT ] );
		} );

		it.each( [
			[ 'theirs' as const, [ DEPENDENT, OTHER ] ],
			[ 'yours' as const, [ DEPENDENT, 's11111111111114' ] ],
		] )( 'keeps the Dependent Subjects the list chosen, %s, holds', ( choice, kept ) => {
			const conflict = EditConflict.between(
				[ host( relationTo( 'Attendance figures', DEPENDENT ) ), figure( DEPENDENT ) ],
				[ host( relationTo( 'Attendance figures', DEPENDENT, OTHER ) ), figure( DEPENDENT ), figure( OTHER ) ],
				[ host( relationTo( 'Attendance figures', DEPENDENT, 's11111111111114' ) ), figure( DEPENDENT ), figure( 's11111111111114' ) ],
			);

			const resolved = conflict.resolve( choose( conflict, choice ), listProperty );

			expect( relationTargets( resolved[ 0 ], 'Attendance figures' ) ).toEqual( kept );
			expect( idsOf( resolved ) ).toEqual( [ HOST, ...kept ] );
		} );

		it( 'adds a Dependent Subject kept against their removal to a list beside theirs', () => {
			const conflict = EditConflict.between(
				[ host( relationTo( 'Attendance figures', DEPENDENT ) ), figure( DEPENDENT, text( 'Year', '2023' ) ) ],
				[ host( relationTo( 'Attendance figures', OTHER ) ), figure( OTHER, text( 'Year', '2025' ) ) ],
				[ host( relationTo( 'Attendance figures', DEPENDENT ) ), figure( DEPENDENT, text( 'Year', '2024' ) ) ],
			);

			const resolved = conflict.resolve( choose( conflict, 'yours' ), listProperty );

			expect( relationTargets( resolved[ 0 ], 'Attendance figures' ) ).toEqual( [ OTHER, DEPENDENT ] );
		} );

		it( 'leaves out the relation to a Dependent Subject removed by choice from the list chosen', () => {
			const added = 's11111111111114';
			const conflict = EditConflict.between(
				[ host( relationTo( 'Attendance figures', DEPENDENT ) ), figure( DEPENDENT, text( 'Year', '2023' ) ) ],
				[ host() ],
				[ host( relationTo( 'Attendance figures', DEPENDENT, added ) ), figure( DEPENDENT, text( 'Year', '2024' ) ), figure( added ) ],
			);
			const choices = new Map<string, Choice>( conflict.bothChanged.map(
				( change ) => [ change.key, change.kind === 'subject' ? 'theirs' : 'yours' ],
			) );

			const resolved = conflict.resolve( choices, listProperty );

			expect( relationTargets( resolved[ 0 ], 'Attendance figures' ) ).toEqual( [ added ] );
			expect( idsOf( resolved ) ).toEqual( [ HOST, added ] );
		} );

		it( 'replaces their single value with a Dependent Subject kept against their removal', () => {
			const conflict = EditConflict.between(
				[ host( relationTo( 'Birth', DEPENDENT ) ), figure( DEPENDENT, text( 'Date', '1881' ) ) ],
				[ host( relationTo( 'Birth', OTHER ) ), figure( OTHER, text( 'Date', '1882' ) ) ],
				[ host( relationTo( 'Birth', DEPENDENT ) ), figure( DEPENDENT, text( 'Date', '1880' ) ) ],
			);

			const resolved = conflict.resolve( choose( conflict, 'yours' ), () => newRelationProperty( { name: 'Birth' } ) );

			expect( relationTargets( resolved[ 0 ], 'Birth' ) ).toEqual( [ DEPENDENT ] );
			expect( idsOf( resolved ) ).toEqual( [ HOST, DEPENDENT ] );
		} );

		it( 'drops a Dependent Subject they removed when theirs is chosen', () => {
			const conflict = EditConflict.between(
				[ hostWithDependent(), figure( DEPENDENT, text( 'Source', 'a' ) ) ],
				[ host() ],
				[ hostWithDependent(), figure( DEPENDENT, text( 'Source', 'b' ) ) ],
			);

			const resolved = conflict.resolve( choose( conflict, 'theirs' ), listProperty );

			expect( idsOf( resolved ) ).toEqual( [ HOST ] );
			expect( attendanceTargets( resolved[ 0 ] ) ).toEqual( [] );
		} );

		it( 'builds on the revision their version is at', () => {
			const resolved = EditConflict.between(
				[ host( text( 'Status', 'Active' ) ) ],
				[ newSubject( { id: HOST, label: 'ACME Inc', statements: new StatementList( [ text( 'Status', 'Active' ) ] ), revisionId: 42 } ) ],
				[ host( text( 'Status', 'Acquired' ) ) ],
			).resolve( new Map(), listProperty );

			expect( resolved[ 0 ].getRevisionId() ).toBe( 42 );
		} );

	} );

} );
