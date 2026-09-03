import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import type { SchemaNameLink } from '@/components/common/SchemaNameDisplay.vue';
import { setupMwMock } from '../../VueTestHelpers.ts';

interface BadgeProps {
	schemaName: string;
	displayName?: string | null;
	link?: SchemaNameLink;
}

function newWrapper( props: BadgeProps ): ReturnType<typeof mount> {
	return mount( SchemaNameDisplay, { props } );
}

describe( 'SchemaNameDisplay', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'util' ] } );
	} );

	it( 'shows the schema name', () => {
		const wrapper = newWrapper( { schemaName: 'Company' } );

		// The visible span, not wrapper.text(): that also holds the visually-hidden noun,
		// which contains the name, so a `toContain` on it survives an empty badge.
		expect( wrapper.get( '.ext-neowiki-schema-name__text' ).text() ).toBe( 'Company' );
	} );

	it( 'links to the schema page by default', () => {
		const wrapper = newWrapper( { schemaName: 'Company' } );

		expect( wrapper.get( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Schema:Company' );
	} );

	it( 'opens in a new tab when asked, so leaving does not discard unsaved edits', () => {
		const wrapper = newWrapper( { schemaName: 'Company', link: 'new-tab' } );
		const anchor = wrapper.get( 'a' );

		expect( anchor.attributes( 'target' ) ).toBe( '_blank' );
		expect( anchor.attributes( 'rel' ) ).toBe( 'noopener' );
	} );

	it( 'stays in the same tab by default', () => {
		const wrapper = newWrapper( { schemaName: 'Company' } );

		expect( wrapper.get( 'a' ).attributes( 'target' ) ).toBeUndefined();
	} );

	it( 'renders no link where the surface owns the click', () => {
		const wrapper = newWrapper( { schemaName: 'Company', link: 'none' } );

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
		expect( wrapper.get( '.ext-neowiki-schema-name__text' ).text() ).toBe( 'Company' );
	} );

	it( 'renders nothing when the name would only repeat the displayed name', () => {
		const wrapper = newWrapper( { schemaName: 'Appellation', displayName: 'Appellation' } );

		expect( wrapper.find( '.ext-neowiki-schema-name' ).exists() ).toBe( false );
	} );

	it( 'still renders beside a differently named subject', () => {
		const wrapper = newWrapper( { schemaName: 'Company', displayName: 'ACME Inc' } );

		expect( wrapper.find( '.ext-neowiki-schema-name' ).exists() ).toBe( true );
	} );

	it( 'names the concept for assistive technology, and only once', () => {
		const wrapper = newWrapper( { schemaName: 'Company' } );

		// The noun reaches a screen reader through the hidden span; the visible name is
		// hidden from it, so the badge is announced "Schema: Company" rather than twice.
		expect( wrapper.get( '.ext-neowiki-schema-name__noun' ).text() )
			.toBe( 'neowiki-schema-labelCompany' );
		expect( wrapper.get( '.ext-neowiki-schema-name__text' ).attributes( 'aria-hidden' ) )
			.toBe( 'true' );
	} );
} );
