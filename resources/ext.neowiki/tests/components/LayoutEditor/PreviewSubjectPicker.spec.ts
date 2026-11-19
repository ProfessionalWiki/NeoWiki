import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PreviewSubjectPicker from '@/components/LayoutEditor/PreviewSubjectPicker.vue';
import { Service } from '@/NeoWikiServices.ts';
import type { SubjectSummary, SubjectSummaryLookup, SubjectSummaryQuery } from '@/application/SubjectSummaryLookup.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

const EDITED_LAST: [ string, string ][] = [
	[ 's1demo1aaaaaaa1', 'ACME Inc' ],
	[ 's1demo1aaaaaaa2', 'Globex' ],
	[ 's1demo1aaaaaaa3', 'Initech' ],
];

function summary( [ id, name ]: [ string, string ] ): SubjectSummary {
	return {
		id,
		displayName: name,
		displayNameIsGenerated: false,
		hostSubject: null,
		schema: 'Company',
		pageId: 1,
		pageTitle: name,
		lastEdited: '2026-11-19T12:00:00Z',
	};
}

describe( 'PreviewSubjectPicker', () => {
	let lookup: SubjectSummaryLookup;

	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg', 'config', 'language' ] } );
		vi.useFakeTimers( { toFake: [ 'setTimeout', 'clearTimeout' ] } );

		// Searches as the API does: a name holding the text, in any case.
		lookup = {
			getSubjectSummaries: vi.fn( async ( query: SubjectSummaryQuery ) => ( {
				subjects: EDITED_LAST
					.filter( ( [ , name ] ) => name.toLowerCase().includes( query.search.toLowerCase() ) )
					.map( summary ),
				nextCursor: null,
			} ) ),
			getRecentlyEditedSubjects: vi.fn(),
		};
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	function mountPicker(): VueWrapper {
		return mount( PreviewSubjectPicker, {
			props: { schemaName: 'Company', subject: { id: 's1demo1aaaaaaa1', name: 'ACME Inc' }, disabled: false },
			attachTo: document.body,
			global: {
				mocks: { $i18n: createI18nMock() },
				provide: { [ Service.SubjectSummaryLookup ]: lookup },
			},
		} );
	}

	function trigger( picker: VueWrapper ): DOMWrapper<Element> {
		return picker.find( '.ext-neowiki-preview-subject-picker__trigger' );
	}

	async function open( picker: VueWrapper ): Promise<void> {
		await trigger( picker ).trigger( 'click' );
		await flushPromises();
	}

	async function typeWithoutPausing( picker: VueWrapper, text: string ): Promise<void> {
		await picker.find( '.ext-neowiki-searchable-picker__search input' ).setValue( text );
	}

	async function type( picker: VueWrapper, text: string ): Promise<void> {
		await typeWithoutPausing( picker, text );
		vi.advanceTimersByTime( 300 );
		await flushPromises();
	}

	function listedNames( picker: VueWrapper ): string[] {
		return picker.findAll( '.cdx-menu-item:not( .cdx-menu__no-results ) .cdx-menu-item__text__label' )
			.map( ( label ) => label.text() );
	}

	it( 'names the previewed Subject on its button', () => {
		const picker = mountPicker();

		expect( trigger( picker ).text() ).toBe( 'ACME Inc' );
	} );

	it( 'lists the Schema\'s Subjects, those edited last first, once opened', async () => {
		const picker = mountPicker();

		await open( picker );

		expect( lookup.getSubjectSummaries ).toHaveBeenCalledWith( expect.objectContaining( {
			schema: 'Company',
			search: '',
			sort: 'edited',
			direction: 'desc',
		} ) );
		expect( listedNames( picker ) ).toEqual( [ 'ACME Inc', 'Globex', 'Initech' ] );
	} );

	it( 'searches the Schema\'s Subjects for what is typed', async () => {
		const picker = mountPicker();
		await open( picker );

		await type( picker, 'tech' );

		expect( listedNames( picker ) ).toEqual( [ 'Initech' ] );
	} );

	it( 'searches once typing pauses, for what was typed by then', async () => {
		const picker = mountPicker();
		await open( picker );

		await typeWithoutPausing( picker, 'te' );
		await typeWithoutPausing( picker, 'tech' );
		vi.advanceTimersByTime( 300 );
		await flushPromises();

		expect( vi.mocked( lookup.getSubjectSummaries ).mock.calls.map( ( [ query ] ) => query.search ) ).toEqual( [ '', 'tech' ] );
	} );

	it( 'opens again on the Subjects edited last, with one search, after a search', async () => {
		const picker = mountPicker();
		await open( picker );
		await type( picker, 'tech' );
		await trigger( picker ).trigger( 'click' );

		await open( picker );

		expect( lookup.getSubjectSummaries ).toHaveBeenCalledTimes( 3 );
		expect( listedNames( picker ) ).toEqual( [ 'ACME Inc', 'Globex', 'Initech' ] );
	} );

	it( 'tells Subjects of one name apart by their pages', async () => {
		vi.mocked( lookup.getSubjectSummaries ).mockResolvedValue( {
			subjects: [
				{ ...summary( [ 's1demo1aaaaaaa1', 'Attendance' ] ), pageTitle: 'Rijksmuseum' },
				{ ...summary( [ 's1demo1aaaaaaa2', 'Attendance' ] ), pageTitle: 'Louvre' },
			],
			nextCursor: null,
		} );
		const picker = mountPicker();

		await open( picker );

		expect( picker.findAll( '.cdx-menu-item__text__description' ).map( ( description ) => description.text() ) )
			.toEqual( [ 'Rijksmuseum', 'Louvre' ] );
	} );

	it( 'says so when the search finds nothing', async () => {
		const picker = mountPicker();
		await open( picker );

		await type( picker, 'Umbrella' );

		expect( picker.find( '.cdx-menu__no-results' ).text() ).toBe( 'neowiki-layout-preview-no-results' );
	} );

	it( 'says the search failed rather than that it found nothing', async () => {
		vi.mocked( lookup.getSubjectSummaries ).mockRejectedValue( new Error( 'Network down' ) );
		const picker = mountPicker();

		await open( picker );

		expect( picker.find( '.cdx-menu__no-results' ).text() ).toBe( 'neowiki-subjects-load-error' );
	} );

	it( 'reports the Subject picked', async () => {
		const picker = mountPicker();
		await open( picker );

		const globex = picker.findAll( '.cdx-menu-item' ).find( ( item ) => item.text() === 'Globex' );
		await globex!.trigger( 'click' );

		expect( picker.emitted( 'pick' ) ).toEqual( [ [ 's1demo1aaaaaaa2' ] ] );
	} );

} );
