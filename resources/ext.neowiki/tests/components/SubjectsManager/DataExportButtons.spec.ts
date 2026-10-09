import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, VueWrapper } from '@vue/test-utils';
import { CdxButton, CdxMenu } from '@wikimedia/codex';
import DataExportButtons from '@/components/SubjectsManager/DataExportButtons.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

// useFloatingMenu drives FloatingUI against real geometry, which jsdom lacks. The button and the menu
// stay real, so the tests hold whichever of the two acts on a key.
vi.mock( '@wikimedia/codex', async ( importOriginal ) => ( {
	...await importOriginal<typeof import( '@wikimedia/codex' )>(),
	useFloatingMenu: vi.fn(),
} ) );

function mountButtons(): VueWrapper {
	return mount( DataExportButtons, {
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

function rdfTrigger( wrapper: VueWrapper ): Element {
	return wrapper.findComponent( CdxButton ).element;
}

function menuIsOpen( wrapper: VueWrapper ): boolean {
	return rdfTrigger( wrapper ).getAttribute( 'aria-expanded' ) === 'true';
}

function menuValues( wrapper: VueWrapper ): unknown[] {
	return ( wrapper.findComponent( CdxMenu ).props( 'menuItems' ) as { value: unknown }[] )
		.map( ( item ) => item.value );
}

async function press( wrapper: VueWrapper, key: string ): Promise<void> {
	rdfTrigger( wrapper ).dispatchEvent( new KeyboardEvent( 'keydown', { key, bubbles: true, cancelable: true } ) );
	await wrapper.vm.$nextTick();
	await wrapper.vm.$nextTick();
}

// A pointer click carries detail >= 1, the click a browser synthesises for Enter or Space on a button
// detail 0. vue-test-utils' trigger() cannot set the read-only `detail`, so dispatch a real MouseEvent.
async function click( wrapper: VueWrapper, detail = 1 ): Promise<void> {
	rdfTrigger( wrapper ).dispatchEvent( new MouseEvent( 'click', { detail, bubbles: true } ) );
	await wrapper.vm.$nextTick();
}

async function pick( wrapper: VueWrapper, value: string ): Promise<void> {
	const menu = wrapper.findComponent( CdxMenu );
	menu.vm.$emit( 'update:selected', value );
	// Codex's menu closes itself right after a pick.
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

	it( 'offers the projections in RDF when RDF is clicked', async () => {
		const wrapper = mountButtons();

		await click( wrapper );

		expect( menuIsOpen( wrapper ) ).toBe( true );
		expect( menuValues( wrapper ) ).toContain( 'RDF:EDM:trig' );
	} );

	it( 'downloads the picked RDF in a new tab and closes', async () => {
		const wrapper = mountButtons();
		await click( wrapper );

		await pick( wrapper, 'RDF:EDM:turtle' );

		expect( openSpy ).toHaveBeenCalledWith( 'RDF:EDM:turtle', '_blank', expect.stringContaining( 'noopener' ) );
		expect( menuIsOpen( wrapper ) ).toBe( false );
	} );

	it( 'downloads the RDF picked with the keyboard', async () => {
		const wrapper = mountButtons();

		await press( wrapper, 'ArrowDown' );
		await press( wrapper, 'Enter' );

		expect( openSpy ).toHaveBeenCalledWith( 'RDF:native:turtle', '_blank', expect.anything() );
	} );

	it.each( [ 'Enter', ' ' ] )( 'opens the RDF menu on %j', async ( key ) => {
		const wrapper = mountButtons();

		await press( wrapper, key );

		expect( menuIsOpen( wrapper ) ).toBe( true );
	} );

	it( 'closes the RDF menu on Escape', async () => {
		const wrapper = mountButtons();
		await click( wrapper );

		await press( wrapper, 'Escape' );

		expect( menuIsOpen( wrapper ) ).toBe( false );
	} );

	it( 'closes the RDF menu on a second click', async () => {
		const wrapper = mountButtons();
		await click( wrapper );

		await click( wrapper );

		expect( menuIsOpen( wrapper ) ).toBe( false );
	} );

	it( 'closes the RDF menu when the focus leaves it', async () => {
		const wrapper = mountButtons();
		await click( wrapper );

		rdfTrigger( wrapper ).dispatchEvent( new FocusEvent( 'focusout', { relatedTarget: null, bubbles: true } ) );
		await wrapper.vm.$nextTick();

		expect( menuIsOpen( wrapper ) ).toBe( false );
	} );

	it( 'keeps the RDF menu open through the click a keyboard activation synthesises', async () => {
		const wrapper = mountButtons();

		await press( wrapper, 'Enter' );
		await click( wrapper, 0 );

		expect( menuIsOpen( wrapper ) ).toBe( true );
	} );
} );
