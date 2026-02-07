import { mount, VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import SubjectLookup from '@/components/common/SubjectLookup.vue';
import { CdxLookup } from '@wikimedia/codex';
import { createI18nMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

function createWrapper( props: Partial<InstanceType<typeof SubjectLookup>['$props']> = {} ): VueWrapper {
	return mount( SubjectLookup, {
		props: {
			selected: null,
			targetSchema: 'Product',
			...props,
		},
		global: {
			mocks: { $i18n },
			stubs: { CdxLookup: true },
		},
	} );
}

describe( 'SubjectLookup', () => {

	it( 'filters stub subjects by targetSchema when input changes', async () => {
		const wrapper = createWrapper( { targetSchema: 'Company' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'a' );
		await nextTick();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'ACME Inc.', value: 's1demo1aaaaaaa1' },
			{ label: 'Professional Wiki GmbH', value: 's1demo5sssssss1' },
		] );
	} );

	it( 'filters by search query case-insensitively', async () => {
		const wrapper = createWrapper( { targetSchema: 'Product' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'neo' );
		await nextTick();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'NeoWiki', value: 's1demo4sssssss1' },
		] );
	} );

	it( 'clears menu items when input is empty', async () => {
		const wrapper = createWrapper( { targetSchema: 'Product' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'Foo' );
		await nextTick();
		expect( lookup.props( 'menuItems' ) ).toHaveLength( 1 );

		lookup.vm.$emit( 'input', '' );
		await nextTick();
		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'emits update:selected when a subject is selected', () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'update:selected', 's1demo1aaaaaaa2' );

		expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo1aaaaaaa2' ] ] );
	} );

	it( 'emits blur when CdxLookup blurs', () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'blur' );

		expect( wrapper.emitted( 'blur' ) ).toHaveLength( 1 );
	} );

	it( 'displays label for a pre-selected subject', () => {
		const wrapper = createWrapper( { selected: 's1demo1aaaaaaa1' } );
		const lookup = wrapper.findComponent( CdxLookup );

		expect( lookup.props( 'inputValue' ) ).toBe( 'ACME Inc.' );
	} );

	it( 'falls back to raw SubjectId when selected subject is not in stub data', () => {
		const wrapper = createWrapper( { selected: 'sABCDEFGHJKLMNP' } );
		const lookup = wrapper.findComponent( CdxLookup );

		expect( lookup.props( 'inputValue' ) ).toBe( 'sABCDEFGHJKLMNP' );
	} );

	it( 'exposes focus method', () => {
		const CdxLookupStub = {
			template: '<div><input /></div>',
		};

		const wrapper = mount( SubjectLookup, {
			props: {
				selected: null,
				targetSchema: 'Product',
			},
			global: {
				mocks: { $i18n },
				stubs: { CdxLookup: CdxLookupStub },
			},
		} );

		const input = wrapper.find( 'input' );
		const focusSpy = vi.spyOn( input.element, 'focus' );

		( wrapper.vm as any ).focus();

		expect( focusSpy ).toHaveBeenCalled();
	} );

	it( 'returns no results when targetSchema does not match any stub subjects', async () => {
		const wrapper = createWrapper( { targetSchema: 'NonExistent' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'ACME' );
		await nextTick();

		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'returns no results when query does not match any stub subjects', async () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'zzzzz' );
		await nextTick();

		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'emits update:inputText when user types', async () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'zzzzz' );
		await nextTick();

		expect( wrapper.emitted( 'update:inputText' ) ).toEqual( [ [ 'zzzzz' ] ] );
	} );

	it( 'emits update:inputText with empty string when input is cleared', async () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'foo' );
		await nextTick();

		lookup.vm.$emit( 'input', '' );
		await nextTick();

		expect( wrapper.emitted( 'update:inputText' ) ).toEqual( [ [ 'foo' ], [ '' ] ] );
	} );

	it( 'resets search state when a subject is selected', async () => {
		const wrapper = createWrapper( { targetSchema: 'Product' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'Foo' );
		await nextTick();
		expect( lookup.props( 'menuItems' ) ).toHaveLength( 1 );

		lookup.vm.$emit( 'update:selected', 's1demo1aaaaaaa2' );
		lookup.vm.$emit( 'input', '' );
		await nextTick();

		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

} );
