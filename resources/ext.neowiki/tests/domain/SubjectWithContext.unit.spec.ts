import { describe, expect, it } from 'vitest';
import { newSubject } from '@/TestHelpers';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { StatementList } from '@/domain/StatementList';

describe( 'SubjectWithContext', () => {

	it( 'stores the page identifiers it was given', () => {
		const identifiers = new PageIdentifiers( 123, 'TestPage' );

		expect( newSubject( { pageIdentifiers: identifiers } ).getPageIdentifiers() ).toEqual( identifiers );
	} );

	describe( 'getChosenName', () => {

		it( 'is the stored label when the Subject has one', () => {
			expect( newSubject( { label: 'Rijksmuseum' } ).getChosenName() ).toBe( 'Rijksmuseum' );
		} );

		it( 'is the name of its page when the Subject is that page\'s Main Subject', () => {
			const subject = newSubject( {
				label: null,
				isMainSubject: true,
				pageIdentifiers: new PageIdentifiers( 7, 'Rijksmuseum' ),
			} );

			expect( subject.getChosenName() ).toBe( 'Rijksmuseum' );
		} );

		it( 'is nothing for a Child Subject with no label', () => {
			const subject = newSubject( {
				label: null,
				pageIdentifiers: new PageIdentifiers( 7, 'Rijksmuseum' ),
			} );

			expect( subject.getChosenName() ).toBeNull();
		} );

	} );

	it( 'keeps its page context when the label is replaced', () => {
		const subject = newSubject( { isMainSubject: true } ).withLabel( 'Renamed' );

		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
		expect( subject.isMainSubject() ).toBe( true );
	} );

	it( 'keeps its page context when the statements are replaced', () => {
		const subject = newSubject( { isMainSubject: true } ).withStatements( new StatementList( [] ) );

		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
		expect( subject.isMainSubject() ).toBe( true );
	} );

	it( 'keeps its page context when the Schema name is replaced', () => {
		const subject = newSubject( { isMainSubject: true } ).withSchemaName( 'OtherSchema' );

		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
		expect( subject.isMainSubject() ).toBe( true );
	} );

} );
