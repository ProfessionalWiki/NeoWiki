import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectLookup from '@/components/common/SubjectLookup.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { CdxLookup, CdxMessage } from '@wikimedia/codex';
import { createI18nMock } from '../../VueTestHelpers.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';

const $i18n = createI18nMock();

const CdxLookupWithVModel = defineComponent( {
	template: '<div />',
	props: {
		selected: { type: String, default: null },
		inputValue: { type: [ String, Number ], default: '' },
		menuItems: { type: Array, default: () => [] },
		startIcon: { type: [ String, Object ], default: undefined },
		placeholder: { type: String, default: '' },
		status: { type: String, default: 'default' },
		ariaLabel: { type: String, default: undefined },
	},
	emits: [ 'update:selected', 'update:input-value', 'input', 'blur' ],
} );

describe( 'SubjectLookup', () => {
	let pinia: ReturnType<typeof createPinia>;
	let subjectStore: any;

	function createWrapper( props: Partial<InstanceType<typeof SubjectLookup>['$props']> = {} ): VueWrapper {
		return mount( SubjectLookup, {
			props: {
				selected: null,
				targetSchema: 'Product',
				...props,
			},
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
				stubs: { CdxLookup: true },
			},
		} );
	}

	function createWrapperWithVModel( props: Partial<InstanceType<typeof SubjectLookup>['$props']> = {} ): VueWrapper {
		return mount( SubjectLookup, {
			props: {
				selected: null,
				targetSchema: 'Product',
				...props,
			},
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
				stubs: { CdxLookup: CdxLookupWithVModel },
			},
		} );
	}

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		subjectStore = useSubjectStore();
		subjectStore.getSubjectLabels = vi.fn().mockResolvedValue( [] );
		subjectStore.getOrFetchSubject = vi.fn().mockRejectedValue( new Error( 'not found' ) );
	} );

	it( 'calls getSubjectLabels with input and targetSchema', async () => {
		const wrapper = createWrapper( { targetSchema: 'Company' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'acme' );
		await flushPromises();

		expect( subjectStore.getSubjectLabels ).toHaveBeenCalledWith( 'acme', 'Company' );
	} );

	it( 'populates menu items from search results', async () => {
		subjectStore.getSubjectLabels.mockResolvedValue( [
			{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.' },
			{ id: 's1demo5sssssss1', label: 'Professional Wiki GmbH' },
		] );

		const wrapper = createWrapper( { targetSchema: 'Company' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'a' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'ACME Inc.', value: 's1demo1aaaaaaa1' },
			{ label: 'Professional Wiki GmbH', value: 's1demo5sssssss1' },
		] );
	} );

	it( 'clears menu items when input is empty', async () => {
		subjectStore.getSubjectLabels.mockResolvedValue( [
			{ id: 's1demo1aaaaaaa2', label: 'Foo' },
		] );

		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'Foo' );
		await flushPromises();
		expect( lookup.props( 'menuItems' ) ).toHaveLength( 1 );

		lookup.vm.$emit( 'input', '' );
		await flushPromises();
		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'shows empty results when API returns no matches', async () => {
		subjectStore.getSubjectLabels.mockResolvedValue( [] );

		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'zzzzz' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'shows empty results when API call fails', async () => {
		subjectStore.getSubjectLabels.mockRejectedValue( new Error( 'Network error' ) );

		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'test' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'emits update:selected when a subject is selected', () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'update:selected', 's1demo1aaaaaaa2' );

		expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo1aaaaaaa2' ] ] );
	} );

	it( 'emits blur with false when CdxLookup blurs with no text', () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'blur' );

		expect( wrapper.emitted( 'blur' ) ).toEqual( [ [ false ] ] );
	} );

	it( 'displays label for a pre-selected subject', async () => {
		const subject = new Subject(
			new SubjectId( 's1demo1aaaaaaa1' ),
			'ACME Inc.',
			'Company',
			new StatementList( [] ),
		);
		subjectStore.getOrFetchSubject = vi.fn().mockResolvedValue( subject );

		const wrapper = createWrapper( { selected: 's1demo1aaaaaaa1' } );
		await flushPromises();

		const lookup = wrapper.findComponent( CdxLookup );
		expect( lookup.props( 'inputValue' ) ).toBe( 'ACME Inc.' );
	} );

	it( 'falls back to raw SubjectId when subject lookup fails', async () => {
		subjectStore.getOrFetchSubject = vi.fn().mockRejectedValue( new Error( 'not found' ) );

		const wrapper = createWrapper( { selected: 'sABCDEFGHJKLMNP' } );
		await flushPromises();

		const lookup = wrapper.findComponent( CdxLookup );
		expect( lookup.props( 'inputValue' ) ).toBe( 'sABCDEFGHJKLMNP' );
	} );

	it( 'discards stale search results when a newer request completes first', async () => {
		let resolveFirst: ( value: { id: string; label: string }[] ) => void;
		const firstCallPromise = new Promise<{ id: string; label: string }[]>( ( resolve ) => {
			resolveFirst = resolve;
		} );

		subjectStore.getSubjectLabels = vi.fn()
			.mockReturnValueOnce( firstCallPromise )
			.mockResolvedValueOnce( [
				{ id: 's1demo5sssssss1', label: 'Second Result' },
			] );

		const wrapper = createWrapper( { targetSchema: 'Company' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'first' );
		lookup.vm.$emit( 'input', 'second' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'Second Result', value: 's1demo5sssssss1' },
		] );

		resolveFirst!( [ { id: 's1demo1aaaaaaa1', label: 'Stale Result' } ] );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'Second Result', value: 's1demo5sssssss1' },
		] );
	} );

	it( 'does not propagate null selection to parent when input has text', async () => {
		subjectStore.getOrFetchSubject = vi.fn().mockResolvedValue(
			new Subject( new SubjectId( 's1demo1aaaaaaa1' ), 'ACME Inc.', 'Company', new StatementList( [] ) ),
		);

		const wrapper = createWrapperWithVModel( { selected: 's1demo1aaaaaaa1' } );
		await flushPromises();

		const lookup = wrapper.findComponent( CdxLookupWithVModel );
		lookup.vm.$emit( 'update:input-value', 'ACME In' );
		lookup.vm.$emit( 'input', 'ACME In' );
		lookup.vm.$emit( 'update:selected', null );
		await flushPromises();

		expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
	} );

	it( 'shows error message on blur with text but no selection', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
		lookup.vm.$emit( 'input', 'some text' );
		await flushPromises();

		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( true );
	} );

	it( 'emits blur with true when text is present but no selection', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'unmatched' );
		lookup.vm.$emit( 'input', 'unmatched' );
		await flushPromises();

		lookup.vm.$emit( 'blur' );

		expect( wrapper.emitted( 'blur' ) ).toEqual( [ [ true ] ] );
	} );

	it( 'clears error when subject is selected', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
		lookup.vm.$emit( 'input', 'some text' );
		await flushPromises();
		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( true );

		lookup.vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
	} );

	it( 'clears error when user types again', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
		lookup.vm.$emit( 'input', 'some text' );
		await flushPromises();
		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( true );

		lookup.vm.$emit( 'input', 'new text' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
	} );

	it( 'sets status to error when there is unmatched text', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
		lookup.vm.$emit( 'input', 'some text' );
		await flushPromises();
		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( lookup.props( 'status' ) ).toBe( 'error' );
	} );

	it( 'does not show error when input is cleared and blurred', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
		lookup.vm.$emit( 'input', 'some text' );
		await flushPromises();
		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( true );

		lookup.vm.$emit( 'update:input-value', '' );
		lookup.vm.$emit( 'input', '' );
		await flushPromises();
		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
		expect( lookup.props( 'status' ) ).toBe( 'default' );
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
				plugins: [ pinia ],
				stubs: { CdxLookup: CdxLookupStub },
			},
		} );

		const input = wrapper.find( 'input' );
		const focusSpy = vi.spyOn( input.element, 'focus' );

		( wrapper.vm as any ).focus();

		expect( focusSpy ).toHaveBeenCalled();
	} );

} );
