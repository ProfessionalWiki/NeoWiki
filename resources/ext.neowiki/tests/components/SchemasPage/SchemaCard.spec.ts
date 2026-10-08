import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, type Mock, vi } from 'vitest';
import { markRaw } from 'vue';
import SchemaCard from '@/components/SchemasPage/SchemaCard.vue';
import { SubjectPreviews } from '@/components/SchemasPage/SubjectPreviews.ts';
import { createI18nMock, type ScrollStub, setupMwMock, stubIntersectionObserver } from '../../VueTestHelpers.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import type { SubjectSummary, SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';

const ARTIST: SchemaSummary = { name: 'Artist', description: 'A person who creates works of art.', propertyCount: 4 };

function subject( id: string, displayName: string ): SubjectSummary {
	return {
		id, displayName, displayNameIsGenerated: false, schema: 'Artist', pageId: 1, pageTitle: displayName,
		lastEdited: '2026-10-01T14:02:00Z',
	};
}

let getSubjectSummaries: Mock<SubjectSummaryLookup['getSubjectSummaries']>;
let scroll: ScrollStub;
let subjectPreviews: SubjectPreviews;

interface CardOptions {
	summary?: SchemaSummary;
	canEdit?: boolean;
	canDelete?: boolean;
	canCreateSubject?: boolean;
	subjectListAvailable?: boolean;
	subjectCount?: number | null;
	subjectCountPending?: boolean;
}

function mountCard( options: CardOptions = {} ): VueWrapper {
	setupMwMock( {
		functions: [ 'config', 'msg', 'message', 'util', 'language' ],
		config: { wgNeoWikiSubjectFirst: false },
	} );

	return mount( SchemaCard, {
		props: {
			summary: options.summary ?? ARTIST,
			canEdit: options.canEdit ?? false,
			canDelete: options.canDelete ?? false,
			canCreateSubject: options.canCreateSubject ?? false,
			subjectListAvailable: options.subjectListAvailable ?? true,
			// Raw, so the card sees only the reactivity the previews bring themselves, not what mounting adds.
			subjectPreviews: markRaw( subjectPreviews ),
			subjectCount: options.subjectCount ?? null,
			subjectCountPending: options.subjectCountPending ?? false,
		},
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: { CdxIcon: true },
		},
	} );
}

// Codex observes the card afresh once its template ref settles after mounting, forgetting what it saw before.
async function scrollIntoView( wrapper: VueWrapper ): Promise<void> {
	await flushPromises();
	scroll.setInView( wrapper.element, true );
	await flushPromises();
}

function findSubjectListLink( wrapper: VueWrapper ): ReturnType<VueWrapper['find']> {
	return wrapper.find( '.ext-neowiki-schema-card__footer a' );
}

function subjectNames( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-schema-card__subjects li a' ).map( ( link ) => link.text() );
}

function findButton( wrapper: VueWrapper, label: string ): ReturnType<VueWrapper['find']> {
	return wrapper.find( `button[aria-label="${ label }"]` );
}

describe( 'SchemaCard', () => {

	beforeEach( () => {
		getSubjectSummaries = vi.fn<SubjectSummaryLookup['getSubjectSummaries']>().mockResolvedValue( {
			subjects: [ subject( 's1demo1aaaaaaa3', 'Johannes Vermeer' ), subject( 's1demo1aaaaaaa2', 'Gustav Klimt' ) ],
			nextCursor: null,
		} );
		scroll = stubIntersectionObserver();
		subjectPreviews = new SubjectPreviews( { getSubjectSummaries } );
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

	it( 'asks for no Subjects before it is scrolled into view', async () => {
		mountCard();
		await flushPromises();

		expect( getSubjectSummaries ).not.toHaveBeenCalled();
	} );

	it( 'asks for the three newest Subjects of its Schema once scrolled into view', async () => {
		await scrollIntoView( mountCard() );

		expect( getSubjectSummaries ).toHaveBeenCalledWith( expect.objectContaining( { schema: 'Artist', sort: 'newest', limit: 3 } ) );
	} );

	it( 'lists the newest Subjects with links to them', async () => {
		const wrapper = mountCard();

		await scrollIntoView( wrapper );

		expect( subjectNames( wrapper ) ).toEqual( [ 'Johannes Vermeer', 'Gustav Klimt' ] );
	} );

	it( 'shows the newest Subjects by name alone', async () => {
		const wrapper = mountCard();

		await scrollIntoView( wrapper );

		expect( wrapper.findAll( '.ext-neowiki-schema-card__subjects li' ).map( ( row ) => row.text() ) )
			.toEqual( [ 'Johannes Vermeer', 'Gustav Klimt' ] );
	} );

	it( 'asks once however often it is scrolled into view', async () => {
		getSubjectSummaries.mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );
		const wrapper = mountCard();
		await scrollIntoView( wrapper );
		scroll.setInView( wrapper.element, false );

		await scrollIntoView( wrapper );

		expect( getSubjectSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'says when the Schema has no Subjects yet', async () => {
		getSubjectSummaries.mockResolvedValue( { subjects: [], nextCursor: null } );
		const wrapper = mountCard();

		await scrollIntoView( wrapper );

		expect( wrapper.text() ).toContain( 'neowiki-subjects-empty-schemaArtist' );
	} );

	it( 'says nothing about Subjects while they load', async () => {
		getSubjectSummaries.mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );
		const wrapper = mountCard();

		await scrollIntoView( wrapper );

		expect( wrapper.text() ).not.toContain( 'neowiki-subjects-empty-schema' );
	} );

	it( 'says when the Subjects could not be loaded', async () => {
		getSubjectSummaries.mockRejectedValue( new Error( 'boom' ) );
		const wrapper = mountCard();

		await scrollIntoView( wrapper );

		expect( wrapper.text() ).toContain( 'neowiki-subjects-load-error' );
	} );

	it( 'asks again once scrolled back into view after its Subjects could not be loaded', async () => {
		getSubjectSummaries.mockRejectedValueOnce( new Error( 'boom' ) );
		const wrapper = mountCard();
		await scrollIntoView( wrapper );
		scroll.setInView( wrapper.element, false );

		await scrollIntoView( wrapper );

		expect( subjectNames( wrapper ) ).toEqual( [ 'Johannes Vermeer', 'Gustav Klimt' ] );
	} );

	it( 'says how many Subjects its Schema has in the link to them', () => {
		const link = findSubjectListLink( mountCard( { subjectCount: 1234 } ) );

		expect( link.text() ).toBe( 'neowiki-schema-subject-count1,234' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Special:Subjects/Artist' );
		expect( findSubjectListLink( mountCard( { subjectCount: 0 } ) ).text() ).toBe( 'neowiki-schema-subject-count0' );
	} );

	it( 'links to all the Subjects of its Schema where the reader sees no counts', () => {
		const link = findSubjectListLink( mountCard( { subjectCount: null } ) );

		expect( link.text() ).toBe( 'neowiki-subjects-view-all' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Special:Subjects/Artist' );
	} );

	it( 'offers no link to its Subjects until the counts arrive', () => {
		expect( findSubjectListLink( mountCard( { subjectCount: null, subjectCountPending: true } ) ).exists() ).toBe( false );
	} );

	it( 'ties the link to its Subjects to the Schema they belong to', () => {
		const wrapper = mountCard( { subjectCount: 2 } );

		expect( findSubjectListLink( wrapper ).attributes( 'aria-describedby' ) )
			.toBe( wrapper.find( 'h2' ).attributes( 'id' ) );
	} );

	it( 'says its Schema has no Subjects yet without asking, when it counts none', async () => {
		const wrapper = mountCard( { subjectCount: 0 } );

		await scrollIntoView( wrapper );

		expect( getSubjectSummaries ).not.toHaveBeenCalled();
		expect( wrapper.text() ).toContain( 'neowiki-subjects-empty-schemaArtist' );
	} );

	it( 'keeps showing the Subjects it loaded when its count arrives as 0', async () => {
		const wrapper = mountCard();
		await scrollIntoView( wrapper );

		await wrapper.setProps( { subjectCount: 0 } );

		expect( subjectNames( wrapper ) ).toEqual( [ 'Johannes Vermeer', 'Gustav Klimt' ] );
	} );

	it.each( [ [ 2, 2 ], [ 3, 12 ], [ 3, null ] ] )(
		'holds the room of %s rows for a Schema counting %s Subjects until they arrive',
		( rows, subjectCount ) => {
			expect( mountCard( { subjectCount } ).findAll( '.ext-neowiki-schema-card__subjects--pending li' ) )
				.toHaveLength( rows );
		},
	);

	it( 'shows no Subjects and asks for none without a Subject list', async () => {
		const wrapper = mountCard( { subjectListAvailable: false, canCreateSubject: true } );
		await scrollIntoView( wrapper );

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
