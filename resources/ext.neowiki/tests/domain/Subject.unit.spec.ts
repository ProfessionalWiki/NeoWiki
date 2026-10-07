import { describe, expect, it } from 'vitest';
import { DEFAULT_SUBJECT_ID, newSubject } from '@/TestHelpers';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { StatementList } from '@/domain/StatementList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';
import { Statement } from '@/domain/Statement';
import { TextType } from '@/domain/propertyTypes/Text';

describe( 'Subject', () => {

	it( 'should be constructable via newSubject', () => {
		const subject = newSubject( {
			label: 'I am a tomato',
			schemaName: 'Tomato',
		} );

		expect( subject.getId().text ).toBe( DEFAULT_SUBJECT_ID );
		expect( subject.getLabel() ).toBe( 'I am a tomato' );
		expect( subject.getSchemaName() ).toBe( 'Tomato' );
		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
	} );

	it( 'should store page identifiers', () => {
		const identifiers = new PageIdentifiers( 123, 'TestPage' );

		const subject = newSubject( {
			pageIdentifiers: identifiers,
		} );

		expect( subject.getPageIdentifiers() ).toEqual( identifiers );
	} );

	describe( 'getDisplayName', () => {
		it( 'is the stored label when the Subject has one', () => {
			expect( newSubject( { label: 'I am a tomato' } ).getDisplayName() ).toBe( 'I am a tomato' );
		} );

		it( 'is the name the server derived when the Subject has no label', () => {
			const subject = newSubject( { label: null, displayName: 'TestSubjectPage' } );

			expect( subject.getLabel() ).toBeNull();
			expect( subject.getDisplayName() ).toBe( 'TestSubjectPage' );
		} );
	} );

	describe( 'withLabel', () => {
		it( 'returns a new Subject with the updated label', () => {
			const originalSubject = newSubject();

			const updatedSubject = originalSubject.withLabel( 'Updated Label' );

			expect( updatedSubject.getLabel() ).toBe( 'Updated Label' );
			expect( updatedSubject.getSchemaName() ).toBe( originalSubject.getSchemaName() );
			expect( updatedSubject.getStatements() ).toEqual( originalSubject.getStatements() );
			expect( updatedSubject ).not.toBe( originalSubject );
		} );

		it( 'displays the label it was given', () => {
			expect( newSubject().withLabel( 'Updated Label' ).getDisplayName() ).toBe( 'Updated Label' );
		} );

		it( 'keeps the previous display name when the label is cleared, since only the server can derive a new one', () => {
			const original = newSubject( { label: 'Acme Anvil' } );

			const cleared = original.withLabel( null );

			expect( cleared.getLabel() ).toBeNull();
			expect( cleared.getDisplayName() ).toBe( 'Acme Anvil' );
		} );

		it( 'stops calling the name generated once someone types one', () => {
			const unnamed = newSubject( { label: null, displayNameIsGenerated: true } );

			expect( unnamed.withLabel( 'Acme Anvil' ).hasGeneratedDisplayName() ).toBe( false );
		} );

		/**
		 * The retained name is the label just deleted, which nobody generated, so marking it would
		 * name the Subject after a string the user typed.
		 */
		it( 'leaves a retained label unmarked when the label is cleared', () => {
			const named = newSubject( { label: 'Acme Anvil' } );

			expect( named.withLabel( null ).hasGeneratedDisplayName() ).toBe( false );
		} );

		it( 'keeps the page the Subject is stored on', () => {
			const page = new PageIdentifiers( 7, 'Acme' );

			expect( newSubject( { pageIdentifiers: page } ).withLabel( 'Acme Anvil' ).getPageIdentifiers() ).toEqual( page );
		} );
	} );

	describe( 'withStatements', () => {
		it( 'returns a new Subject with the updated statements', () => {
			const originalSubject = newSubject();

			const newStatements = new StatementList( [
				{
					propertyName: new PropertyName( 'testProperty' ),
					propertyType: TextType.typeName,
					value: newStringValue( 'Test Value' ),
				} as Statement,
			] );

			const updatedSubject = originalSubject.withStatements( newStatements );

			expect( updatedSubject.getLabel() ).toBe( originalSubject.getLabel() );
			expect( updatedSubject.getSchemaName() ).toBe( originalSubject.getSchemaName() );
			expect( updatedSubject.getStatements() ).toEqual( newStatements );
			expect( updatedSubject ).not.toBe( originalSubject );
		} );

		it( 'keeps the page the Subject is stored on', () => {
			const page = new PageIdentifiers( 7, 'Acme' );

			expect( newSubject( { pageIdentifiers: page } ).withStatements( new StatementList( [] ) ).getPageIdentifiers() ).toEqual( page );
		} );
	} );

} );
