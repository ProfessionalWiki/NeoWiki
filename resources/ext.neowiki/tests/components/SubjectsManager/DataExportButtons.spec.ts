import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { shallowMount, VueWrapper } from '@vue/test-utils';
import { CdxButton, CdxMenu } from '@wikimedia/codex';
import DataExportButtons from '@/components/SubjectsManager/DataExportButtons.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

// useFloatingMenu drives FloatingUI against real geometry; neutralise it under jsdom while keeping
// the real CdxButton/CdxMenu so shallowMount stubs them by name and we can read props / emit events.
vi.mock( '@wikimedia/codex', async ( importOriginal ) => ( {
	...await importOriginal<typeof import( '@wikimedia/codex' )>(),
	useFloatingMenu: vi.fn(),
} ) );

function mountButtons(): VueWrapper {
	return shallowMount( DataExportButtons, {
		props: {
			jsonUrl: 'JSON_URL',
			rdfUrl: ( projection: string, format: string ) => `RDF:${ projection }:${ format }`,
			projections: [ 'native', 'EDM' ],
		},
		global: {
			mocks: { $i18n: createI18nMock() },
		},
	} );
}

function menuValues( wrapper: VueWrapper ): unknown[] {
	return ( wrapper.findComponent( CdxMenu ).props( 'menuItems' ) as { value: unknown }[] )
		.map( ( item ) => item.value );
}

function menuIsOpen( wrapper: VueWrapper ): boolean {
	return wrapper.findComponent( CdxMenu ).props( 'expanded' ) as boolean;
}

function rdfTrigger( wrapper: VueWrapper ): Element {
	return wrapper.findComponent( CdxButton ).element;
}

async function openRdfMenu( wrapper: VueWrapper ): Promise<void> {
	// A genuine pointer click carries detail >= 1; the component ignores detail-0 clicks (the ones a
	// native button synthesises on Enter/Space, which the keydown handler owns). vue-test-utils'
	// trigger() cannot set the read-only `detail`, so dispatch a real MouseEvent.
	rdfTrigger( wrapper ).dispatchEvent( new MouseEvent( 'click', { detail: 1, bubbles: true } ) );
	await wrapper.vm.$nextTick();
}

async function pick( wrapper: VueWrapper, value: string ): Promise<void> {
	const menu = wrapper.findComponent( CdxMenu );
	menu.vm.$emit( 'update:selected', value );
	// Real CdxMenu always emits update:expanded(false) right after a single-select pick.
	menu.vm.$emit( 'update:expanded', false );
	await wrapper.vm.$nextTick();
}

describe( 'DataExportButtons', () => {
	let openSpy: ReturnType<typeof vi.spyOn>;

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
		openSpy = vi.spyOn( window, 'open' ).mockImplementation( () => null );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'links JSON to the JSON export in a new tab', () => {
		const link = mountButtons().get( 'a' );

		expect( link.text() ).toBe( 'neowiki-managesubjects-export-json' );
		expect( link.attributes( 'href' ) ).toBe( 'JSON_URL' );
		expect( link.attributes( 'target' ) ).toBe( '_blank' );
	} );

	it( 'offers each projection in each RDF format when RDF is clicked', async () => {
		const wrapper = mountButtons();

		await openRdfMenu( wrapper );

		expect( menuIsOpen( wrapper ) ).toBe( true );
		expect( menuValues( wrapper ) ).toEqual(
			[ 'RDF:native:turtle', 'RDF:native:trig', 'RDF:EDM:turtle', 'RDF:EDM:trig' ],
		);
	} );

	it( 'downloads the picked RDF in a new tab and closes', async () => {
		const wrapper = mountButtons();
		await openRdfMenu( wrapper );

		await pick( wrapper, 'RDF:EDM:turtle' );

		expect( openSpy ).toHaveBeenCalledWith( 'RDF:EDM:turtle', '_blank', 'noopener' );
		expect( menuIsOpen( wrapper ) ).toBe( false );
	} );

	it( 'opens the RDF menu on Enter', async () => {
		const wrapper = mountButtons();

		rdfTrigger( wrapper ).dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );
		await wrapper.vm.$nextTick();

		expect( menuIsOpen( wrapper ) ).toBe( true );
	} );

	it( 'closes the RDF menu on Escape', async () => {
		const wrapper = mountButtons();
		await openRdfMenu( wrapper );

		rdfTrigger( wrapper ).dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
		await wrapper.vm.$nextTick();

		expect( menuIsOpen( wrapper ) ).toBe( false );
	} );

	it( 'ignores the detail-0 click a keyboard activation synthesises', async () => {
		const wrapper = mountButtons();
		// Enter opens via the keydown handler; the native button also fires a detail-0 click, which
		// must NOT toggle the menu back closed (that was the flash). Only detail > 0 clicks toggle.
		const trigger = rdfTrigger( wrapper );

		trigger.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );
		trigger.dispatchEvent( new MouseEvent( 'click', { detail: 0, bubbles: true } ) );
		await wrapper.vm.$nextTick();

		expect( menuIsOpen( wrapper ) ).toBe( true );
	} );
} );
