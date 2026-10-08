import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SchemasTable from '@/components/SchemasPage/SchemasTable.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const SUBJECT_COUNTS = new Map( [ [ 'Artist', 4 ], [ 'Artwork', 30 ] ] );

function mountTable( props: Partial<InstanceType<typeof SchemasTable>['$props']> = {} ): VueWrapper {
	setupMwMock( { functions: [ 'msg', 'util', 'language' ] } );

	return mount( SchemasTable, {
		props: {
			schemas: [
				{ name: 'Artist', description: 'A person who creates works of art.', propertyCount: 4 },
				{ name: 'Artwork', description: '', propertyCount: 2 },
			],
			canEdit: false,
			canDelete: false,
			canCreateSubject: false,
			subjectListAvailable: true,
			subjectCountOf: ( schemaName: string ) => SUBJECT_COUNTS.get( schemaName ) ?? null,
			subjectCountPending: false,
			...props,
		},
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: { CdxIcon: true },
		},
	} );
}

function row( wrapper: VueWrapper, index: number ): DOMWrapper<Element> {
	return wrapper.findAll( 'tbody tr' )[ index ];
}

function findButton( element: DOMWrapper<Element>, label: string ): DOMWrapper<Element> {
	return element.findAll( 'button' ).find( ( button ) => ( button.attributes( 'aria-label' ) ?? button.text() ) === label )!;
}

describe( 'SchemasTable', () => {

	it( 'links each Schema to its page and to its Subjects, saying how many there are', () => {
		const artwork = row( mountTable(), 1 );

		expect( artwork.find( 'a[href="/wiki/Schema:Artwork"]' ).text() ).toBe( 'Artwork' );
		expect( artwork.find( 'a[href="/wiki/Special:Subjects/Artwork"]' ).text() ).toBe( 'neowiki-schema-subject-count30' );
	} );

	it( 'links to all the Subjects of a Schema whose count it does not know', () => {
		const artwork = row( mountTable( { subjectCountOf: () => null } ), 1 );

		expect( artwork.find( 'a[href="/wiki/Special:Subjects/Artwork"]' ).text() ).toBe( 'neowiki-subjects-view-all' );
	} );

	it( 'offers no links to the Subjects until the counts arrive', () => {
		const wrapper = mountTable( { subjectCountOf: () => null, subjectCountPending: true } );

		expect( wrapper.find( 'a[href^="/wiki/Special:Subjects/"]' ).exists() ).toBe( false );
	} );

	it.each( [
		[ 'edit', 'neowiki-edit-schema' ],
		[ 'delete', 'neowiki-schema-delete' ],
		[ 'create-subject', 'neowiki-schema-create-subjectArtwork' ],
	] )( 'asks to %s for the Schema of the row', async ( event, buttonLabel ) => {
		const wrapper = mountTable( { canEdit: true, canDelete: true, canCreateSubject: true } );

		await findButton( row( wrapper, 1 ), buttonLabel ).trigger( 'click' );

		expect( wrapper.emitted( event ) ).toEqual( [ [ 'Artwork' ] ] );
	} );

	it( 'offers only the link to the Schema to a user without rights, on a wiki without a Subject list', () => {
		const artist = row( mountTable( { subjectListAvailable: false } ), 0 );

		expect( artist.findAll( 'button' ) ).toEqual( [] );
		expect( artist.findAll( 'a' ).map( ( link ) => link.attributes( 'href' ) ) ).toEqual( [ '/wiki/Schema:Artist' ] );
		expect( artist.findAll( 'td' ) ).toHaveLength( 2 );
	} );

	it( 'offers Subject creation on a wiki without a Subject list', () => {
		const artist = row( mountTable( { subjectListAvailable: false, canCreateSubject: true } ), 0 );

		expect( findButton( artist, 'neowiki-schema-create-subjectArtist' ) ).toBeDefined();
		expect( artist.find( 'a[href^="/wiki/Special:Subjects/"]' ).exists() ).toBe( false );
	} );

	it( 'ties the edit and delete buttons and the Subjects link of each row to its Schema', () => {
		const wrapper = mountTable( {
			schemas: [
				{ name: 'Artist', description: '', propertyCount: 1 },
				{ name: 'Validation Demo', description: '', propertyCount: 1 },
			],
			canEdit: true,
			canDelete: true,
		} );
		const demo = row( wrapper, 1 );
		const describedAs = ( element: DOMWrapper<Element> ): string[] => element.attributes( 'aria-describedby' )!
			.split( ' ' ).map( ( id ) => wrapper.find( `[id="${ id }"]` ).text() );

		expect( describedAs( findButton( demo, 'neowiki-edit-schema' ) ) ).toEqual( [ 'Validation Demo' ] );
		expect( describedAs( findButton( demo, 'neowiki-schema-delete' ) ) ).toEqual( [ 'Validation Demo' ] );
		expect( describedAs( demo.find( 'a[href="/wiki/Special:Subjects/Validation Demo"]' ) ) ).toEqual( [ 'Validation Demo' ] );
	} );

} );
