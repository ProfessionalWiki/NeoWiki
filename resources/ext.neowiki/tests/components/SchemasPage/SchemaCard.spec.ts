import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SchemaCard from '@/components/SchemasPage/SchemaCard.vue';
import { Service } from '@/NeoWikiServices.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import type { SubjectSummary } from '@/application/SubjectSummaryLookup.ts';

const ARTIST: SchemaSummary = { name: 'Artist', description: 'A person who creates works of art.', propertyCount: 4 };

function subject( id: string, displayName: string ): SubjectSummary {
	return {
		id, displayName, displayNameIsGenerated: false, schema: 'Artist', pageId: 1, pageTitle: displayName,
		lastEdited: '2026-10-01T14:02:00Z',
	};
}

let getSubjectSummaries: ReturnType<typeof vi.fn>;

interface CardOptions {
	summary?: SchemaSummary;
	canEdit?: boolean;
	canDelete?: boolean;
	canCreateSubject?: boolean;
	subjectListAvailable?: boolean;
}

function mountCard( options: CardOptions = {} ): VueWrapper {
	setupMwMock( { functions: [ 'config', 'msg', 'message', 'util' ], config: { wgNeoWikiSubjectFirst: false } } );

	return mount( SchemaCard, {
		props: {
			summary: options.summary ?? ARTIST,
			canEdit: options.canEdit ?? false,
			canDelete: options.canDelete ?? false,
			canCreateSubject: options.canCreateSubject ?? false,
			subjectListAvailable: options.subjectListAvailable ?? true,
		},
		global: {
			mocks: { $i18n: createI18nMock() },
			provide: { [ Service.SubjectSummaryLookup ]: { getSubjectSummaries } },
			stubs: { CdxIcon: true },
		},
	} );
}

function findButton( wrapper: VueWrapper, label: string ): ReturnType<VueWrapper['find']> {
	return wrapper.find( `button[aria-label="${ label }"]` );
}

describe( 'SchemaCard', () => {

	beforeEach( () => {
		getSubjectSummaries = vi.fn().mockResolvedValue( {
			subjects: [ subject( 's1demo1aaaaaaa3', 'Johannes Vermeer' ), subject( 's1demo1aaaaaaa2', 'Gustav Klimt' ) ],
			nextCursor: null,
		} );
	} );

	it( 'links the Schema name to its page', () => {
		const link = mountCard().find( 'h2 a' );

		expect( link.text() ).toBe( 'Artist' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Schema:Artist' );
	} );

	it( 'shows the description', () => {
		expect( mountCard().text() ).toContain( 'A person who creates works of art.' );
	} );

	it( 'leaves the description out when the Schema has none', () => {
		const wrapper = mountCard( { summary: { ...ARTIST, description: '' } } );

		expect( wrapper.find( '.ext-neowiki-schema-card__description' ).exists() ).toBe( false );
	} );

	it( 'asks for the three newest Subjects of its Schema', async () => {
		mountCard();
		await flushPromises();

		expect( getSubjectSummaries ).toHaveBeenCalledWith( {
			schema: 'Artist', search: '', sort: 'newest', direction: 'desc', cursor: null, limit: 3,
		} );
	} );

	it( 'lists the newest Subjects with links to them', async () => {
		const wrapper = mountCard();
		await flushPromises();

		expect( wrapper.findAll( '.ext-neowiki-schema-card__subjects li a' ).map( ( a ) => a.text() ) )
			.toEqual( [ 'Johannes Vermeer', 'Gustav Klimt' ] );
		expect( wrapper.findAll( '.ext-neowiki-schema-card__subjects li time' ) ).toHaveLength( 2 );
	} );

	it( 'says when the Schema has no Subjects yet', async () => {
		getSubjectSummaries.mockResolvedValue( { subjects: [], nextCursor: null } );
		const wrapper = mountCard();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-empty-schemaArtist' );
	} );

	it( 'says nothing about Subjects while they load', () => {
		getSubjectSummaries.mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );

		expect( mountCard().text() ).not.toContain( 'neowiki-subjects-empty-schema' );
	} );

	it( 'says when the Subjects could not be loaded', async () => {
		getSubjectSummaries.mockRejectedValue( new Error( 'boom' ) );
		const wrapper = mountCard();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-load-error' );
	} );

	it( 'links to all the Subjects of its Schema', () => {
		const link = mountCard().find( '.ext-neowiki-schema-card__footer a' );

		expect( link.text() ).toBe( 'neowiki-subjects-view-all' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Special:Subjects/Artist' );
	} );

	it( 'shows no Subjects and asks for none without a Subject list', async () => {
		const wrapper = mountCard( { subjectListAvailable: false, canCreateSubject: true } );
		await flushPromises();

		expect( getSubjectSummaries ).not.toHaveBeenCalled();
		expect( wrapper.find( '.ext-neowiki-schema-card__subjects' ).exists() ).toBe( false );
		expect( wrapper.text() ).not.toContain( 'neowiki-subjects-view-all' );
	} );

	it( 'offers editing to a user who may edit Schemas', async () => {
		const wrapper = mountCard( { canEdit: true } );

		await findButton( wrapper, 'neowiki-edit-schema' ).trigger( 'click' );

		expect( wrapper.emitted( 'edit' ) ).toHaveLength( 1 );
	} );

	it( 'offers no editing to a user who may not edit Schemas', () => {
		expect( findButton( mountCard( { canEdit: false, canDelete: true } ), 'neowiki-edit-schema' ).exists() ).toBe( false );
	} );

	it( 'ties its edit and delete buttons to the Schema they act on', () => {
		const wrapper = mountCard( { canEdit: true, canDelete: true } );
		const headingId = wrapper.find( 'h2' ).attributes( 'id' );

		expect( headingId ).toBeTruthy();
		expect( findButton( wrapper, 'neowiki-edit-schema' ).attributes( 'aria-describedby' ) ).toBe( headingId );
		expect( findButton( wrapper, 'neowiki-schema-delete' ).attributes( 'aria-describedby' ) ).toBe( headingId );
	} );

	it( 'gives each card a heading id of its own', () => {
		expect( mountCard().find( 'h2' ).attributes( 'id' ) ).not.toBe( mountCard().find( 'h2' ).attributes( 'id' ) );
	} );

	it( 'offers deleting to a user who may delete Schemas', async () => {
		const wrapper = mountCard( { canDelete: true } );

		await findButton( wrapper, 'neowiki-schema-delete' ).trigger( 'click' );

		expect( wrapper.emitted( 'delete' ) ).toHaveLength( 1 );
	} );

	it( 'offers no deleting to a user who may edit but not delete Schemas', () => {
		expect( findButton( mountCard( { canEdit: true, canDelete: false } ), 'neowiki-schema-delete' ).exists() ).toBe( false );
	} );

	it( 'offers to create a Subject of its Schema to a user who may', async () => {
		const wrapper = mountCard( { canCreateSubject: true } );
		const button = wrapper.find( '.ext-neowiki-schema-card__footer button' );

		await button.trigger( 'click' );

		expect( button.text() ).toBe( 'neowiki-schema-create-subjectArtist' );
		expect( wrapper.emitted( 'create-subject' ) ).toHaveLength( 1 );
	} );

	it( 'offers no Subject creation to a user who may not create Subject pages', () => {
		expect( mountCard( { canCreateSubject: false } ).find( '.ext-neowiki-schema-card__footer button' ).exists() )
			.toBe( false );
	} );

} );
