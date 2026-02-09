import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { CdxLookup } from '@wikimedia/codex';
import { createI18nMock } from '../../VueTestHelpers.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';

const $i18n = createI18nMock();

describe( 'SchemaLookup', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: any;

	const mountComponent = (): VueWrapper => (
		mount( SchemaLookup, {
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				stubs: {
					CdxLookup: true,
				},
			},
		} )
	);

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.searchAndFetchMissingSchemas = vi.fn().mockResolvedValue( [] );
	} );

	it( 'searches for schemas when input changes', () => {
		const wrapper = mountComponent();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'test query' );

		expect( schemaStore.searchAndFetchMissingSchemas ).toHaveBeenCalledWith( 'test query' );
	} );

	it( 'updates menu items with search results', async () => {
		const mockResults = [ 'Schema1', 'Schema2' ];
		schemaStore.searchAndFetchMissingSchemas.mockResolvedValue( mockResults );
		schemaStore.schemas.set( 'Schema1', new Schema( 'Schema1', 'First description', new PropertyDefinitionList( [] ) ) );
		schemaStore.schemas.set( 'Schema2', new Schema( 'Schema2', 'Second description', new PropertyDefinitionList( [] ) ) );

		const wrapper = mountComponent();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'test' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'Schema1', value: 'Schema1', description: 'First description' },
			{ label: 'Schema2', value: 'Schema2', description: 'Second description' },
		] );
	} );

	it( 'omits description from menu items when schema has empty description', async () => {
		schemaStore.searchAndFetchMissingSchemas.mockResolvedValue( [ 'WithDesc', 'NoDesc' ] );
		schemaStore.schemas.set( 'WithDesc', new Schema( 'WithDesc', 'Has a description', new PropertyDefinitionList( [] ) ) );
		schemaStore.schemas.set( 'NoDesc', new Schema( 'NoDesc', '', new PropertyDefinitionList( [] ) ) );

		const wrapper = mountComponent();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'test' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'WithDesc', value: 'WithDesc', description: 'Has a description' },
			{ label: 'NoDesc', value: 'NoDesc', description: undefined },
		] );
	} );

	it( 'discards stale search results when a newer request completes first', async () => {
		let resolveFirst: ( value: string[] ) => void;
		const firstCallPromise = new Promise<string[]>( ( resolve ) => {
			resolveFirst = resolve;
		} );

		schemaStore.searchAndFetchMissingSchemas = vi.fn()
			.mockReturnValueOnce( firstCallPromise )
			.mockResolvedValueOnce( [ 'SecondSchema' ] );

		schemaStore.schemas.set( 'FirstSchema', new Schema( 'FirstSchema', 'Stale', new PropertyDefinitionList( [] ) ) );
		schemaStore.schemas.set( 'SecondSchema', new Schema( 'SecondSchema', 'Fresh', new PropertyDefinitionList( [] ) ) );

		const wrapper = mountComponent();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'first' );
		lookup.vm.$emit( 'input', 'second' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'SecondSchema', value: 'SecondSchema', description: 'Fresh' },
		] );

		resolveFirst!( [ 'FirstSchema' ] );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'SecondSchema', value: 'SecondSchema', description: 'Fresh' },
		] );
	} );

	it( 'exposes focus method', () => {
		const CdxLookupStub = {
			template: '<div><input /></div>',
		};

		const wrapper = mount( SchemaLookup, {
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				stubs: {
					CdxLookup: CdxLookupStub,
				},
			},
		} );

		const input = wrapper.find( 'input' );
		const focusSpy = vi.spyOn( input.element, 'focus' );

		( wrapper.vm as any ).focus();

		expect( focusSpy ).toHaveBeenCalled();
	} );
} );
