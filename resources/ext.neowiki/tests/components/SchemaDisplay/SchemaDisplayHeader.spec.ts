import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SchemaDisplayHeader from '@/components/SchemaDisplay/SchemaDisplayHeader.vue';
import { Schema } from '@/domain/Schema.ts';
import { setupMwMock, createI18nMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';

function mountComponent( schema: Schema, canEditSchema: boolean = false, canCreateSubject: boolean = false ): VueWrapper {
	setupMwMock( { functions: [ 'msg', 'util' ] } );

	return mount( SchemaDisplayHeader, {
		props: { schema, canEditSchema, canCreateSubject },
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				CdxIcon: true,
			},
		},
	} );
}

describe( 'SchemaDisplayHeader', () => {
	it( 'renders schema name and description', () => {
		const wrapper = mountComponent( newSchema( {
			title: 'Test schema',
			description: 'A schema for people',
		} ) );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__title' ).text() ).toBe( 'Test schema' );
		expect( wrapper.find( '.ext-neowiki-schema-display-header__description' ).text() ).toBe( 'A schema for people' );
	} );

	it( 'hides description when schema has none', () => {
		const wrapper = mountComponent( newSchema( { description: '' } ) );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__description' ).exists() ).toBe( false );
	} );

	it( 'shows edit button when canEditSchema is true', () => {
		const wrapper = mountComponent( newSchema(), true );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__actions button' ).exists() ).toBe( true );
	} );

	it( 'hides edit button when canEditSchema is false', () => {
		const wrapper = mountComponent( newSchema(), false );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__actions button' ).exists() ).toBe( false );
	} );

	it( 'emits edit event on edit button click', async () => {
		const wrapper = mountComponent( newSchema( { title: 'Company' } ), true );

		await wrapper.find( '.ext-neowiki-schema-display-header__actions button' ).trigger( 'click' );

		expect( wrapper.emitted( 'edit' ) ).toHaveLength( 1 );
	} );

	it( 'links to the page-first creator for this schema when the user may create subjects', () => {
		const wrapper = mountComponent( newSchema( { title: 'Company' } ), false, true );
		const link = wrapper.find( '.ext-neowiki-schema-display-header__create-subject' );

		expect( link.attributes( 'href' ) ).toBe( '/wiki/Special:CreateSubject/Company' );
		expect( link.text() ).toContain( 'neowiki-schema-create-subjectCompany' );
	} );

	it( 'offers no creation link when the user may not create subjects', () => {
		const wrapper = mountComponent( newSchema(), false, false );

		expect( wrapper.find( '.ext-neowiki-schema-display-header__create-subject' ).exists() ).toBe( false );
	} );
} );
