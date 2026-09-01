import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { defineComponent, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import SubjectPicker from '@/components/common/SubjectPicker.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { CdxLookup, CdxMessage } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Service } from '@/NeoWikiServices.ts';
import type { SubjectLabelSearch } from '@/domain/SubjectLabelSearch.ts';

const $i18n = createI18nMock();

const CdxLookupWithVModel = defineComponent( {
	// The no-results slot is rendered inside a marker element so a test can tell an empty slot
	// from an absent one, which is what distinguishes Codex's message from the picker's own item.
	template: '<div><span class="no-results-slot"><slot name="no-results" /></span></div>',
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

describe( 'SubjectPicker', () => {
	let pinia: ReturnType<typeof createPinia>;
	let subjectStore: any;
	let mockSubjectLabelSearch: SubjectLabelSearch;

	function createWrapper( props: Partial<InstanceType<typeof SubjectPicker>['$props']> = {} ): VueWrapper {
		return mount( SubjectPicker, {
			props: {
				selected: null,
				targetSchema: 'Product',
				...props,
			},
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
				provide: {
					[ Service.SubjectLabelSearch ]: mockSubjectLabelSearch,
				},
				stubs: { CdxLookup: true },
			},
		} );
	}

	function createWrapperWithVModel( props: Partial<InstanceType<typeof SubjectPicker>['$props']> = {} ): VueWrapper {
		return mount( SubjectPicker, {
			props: {
				selected: null,
				targetSchema: 'Product',
				...props,
			},
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
				provide: {
					[ Service.SubjectLabelSearch ]: mockSubjectLabelSearch,
				},
				stubs: { CdxLookup: CdxLookupWithVModel },
			},
		} );
	}

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		subjectStore = useSubjectStore();
		subjectStore.getOrFetchSubject = vi.fn().mockRejectedValue( new Error( 'not found' ) );

		mockSubjectLabelSearch = {
			searchSubjectLabels: vi.fn().mockResolvedValue( [] ),
		};
	} );

	it( 'calls searchSubjectLabels with input and targetSchema', async () => {
		const wrapper = createWrapper( { targetSchema: 'Company' } );
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'acme' );
		await flushPromises();

		expect( mockSubjectLabelSearch.searchSubjectLabels ).toHaveBeenCalledWith( 'acme', 'Company' );
	} );

	it( 'populates menu items from search results', async () => {
		( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockResolvedValue( [
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
		( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockResolvedValue( [
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
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'zzzzz' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'shows empty results when API call fails', async () => {
		( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockRejectedValue( new Error( 'Network error' ) );

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

	it( 'displays the derived name for a pre-selected subject that has no label', async () => {
		const subject = new Subject(
			new SubjectId( 's1demo1aaaaaaa1' ),
			null,
			'Acme Anvil',
			'Company',
			new StatementList( [] ),
		);
		subjectStore.getOrFetchSubject = vi.fn().mockResolvedValue( subject );

		const wrapper = createWrapper( { selected: 's1demo1aaaaaaa1' } );
		await flushPromises();

		expect( wrapper.findComponent( CdxLookup ).props( 'inputValue' ) ).toBe( 'Acme Anvil' );
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

		( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> )
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
			new Subject( new SubjectId( 's1demo1aaaaaaa1' ), 'ACME Inc.', 'ACME Inc.', 'Company', new StatementList( [] ) ),
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

		const wrapper = mount( SubjectPicker, {
			props: {
				selected: null,
				targetSchema: 'Product',
			},
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
				provide: {
					[ Service.SubjectLabelSearch ]: mockSubjectLabelSearch,
				},
				stubs: { CdxLookup: CdxLookupStub },
			},
		} );

		const input = wrapper.find( 'input' );
		const focusSpy = vi.spyOn( input.element, 'focus' );

		( wrapper.vm as any ).focus();

		expect( focusSpy ).toHaveBeenCalled();
	} );

	it( 'renders the suffix slot with the selected id as slot prop', () => {
		const wrapper = mount( SubjectPicker, {
			props: { selected: 's11111111111111', targetSchema: 'Person' },
			global: {
				plugins: [ pinia ],
				provide: { [ Service.SubjectLabelSearch ]: mockSubjectLabelSearch },
				mocks: { $i18n },
				stubs: { CdxLookup: true },
			},
			slots: {
				suffix: '<template #suffix="{ selected }"><span class="probe">{{ selected }}</span></template>',
			},
		} );

		expect( wrapper.find( '.probe' ).text() ).toBe( 's11111111111111' );
	} );

	describe( 'creating the target on the spot', () => {
		const attachedWrappers: VueWrapper[] = [];

		beforeEach( () => {
			setupMwMock( {
				messages: {
					'neowiki-subject-picker-create': ( schema: string ) => `Create a new ${ schema }`,
					'neowiki-subject-picker-create-named': ( text: string, schema: string ) =>
						`Create "${ text }" as a new ${ schema }`,
					'neowiki-subject-picker-no-results': 'No matches found',
				},
			} );
		} );

		afterEach( () => {
			attachedWrappers.splice( 0 ).forEach( ( wrapper ) => wrapper.unmount() );
		} );

		function createdSubject( label: string | null, displayName: string ): Subject {
			return new Subject(
				new SubjectId( 's1demo1aaaaaaa1' ),
				label,
				displayName,
				'Product',
				new StatementList( [] ),
			);
		}

		type SubjectCreator = ( label: string | null ) => Promise<Subject | null>;

		function creatorReturning( subject: Subject | null ): Mock<SubjectCreator> {
			return vi.fn<SubjectCreator>().mockResolvedValue( subject );
		}

		// Awaited, because the picker resolves the label of its committed Subject on mount and
		// writes the answer into the field: typing before that lands would be overwritten by it.
		async function createWrapperOffering(
			createSubject: Mock<SubjectCreator>,
			props: Partial<InstanceType<typeof SubjectPicker>['$props']> = {},
		): Promise<VueWrapper> {
			const wrapper = createWrapperWithVModel( { createSubject, ...props } );
			await flushPromises();
			return wrapper;
		}

		function menuItemsOf( wrapper: VueWrapper ): MenuItemData[] {
			return wrapper.findComponent( CdxLookupWithVModel ).props( 'menuItems' ) as MenuItemData[];
		}

		function menuLabelsOf( wrapper: VueWrapper ): string[] {
			return menuItemsOf( wrapper ).map( ( item ) => String( item.label ) );
		}

		function lastMenuLabelOf( wrapper: VueWrapper ): string {
			const labels = menuLabelsOf( wrapper );
			return labels[ labels.length - 1 ];
		}

		function inputTextOf( wrapper: VueWrapper ): string {
			return String( wrapper.findComponent( CdxLookupWithVModel ).props( 'inputValue' ) );
		}

		async function type( wrapper: VueWrapper, text: string ): Promise<void> {
			const lookup = wrapper.findComponent( CdxLookupWithVModel );
			lookup.vm.$emit( 'update:input-value', text );
			lookup.vm.$emit( 'input', text );
			await flushPromises();
		}

		// Picked by its position rather than by the sentinel value the component uses internally,
		// so the test states what the user does: choose the last entry, the create one.
		async function chooseLastMenuItem( wrapper: VueWrapper ): Promise<void> {
			const items = menuItemsOf( wrapper );
			wrapper.findComponent( CdxLookupWithVModel ).vm.$emit( 'update:selected', items[ items.length - 1 ].value );
			await flushPromises();
		}

		function searchReturns( results: { id: string; label: string }[] ): void {
			( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockResolvedValue( results );
		}

		it( 'lists nothing but the search results when the host cannot create Subjects', async () => {
			searchReturns( [ { id: 's1demo1aaaaaaa1', label: 'ACME Inc.' } ] );
			const wrapper = createWrapperWithVModel();
			await flushPromises();

			await type( wrapper, 'ac' );

			expect( menuItemsOf( wrapper ) ).toEqual( [ { label: 'ACME Inc.', value: 's1demo1aaaaaaa1' } ] );
		} );

		it( 'leaves the menu empty before anything is typed when the host cannot create Subjects', async () => {
			const wrapper = createWrapperWithVModel();
			await flushPromises();

			expect( menuItemsOf( wrapper ) ).toEqual( [] );
		} );

		it( 'lets Codex report a fruitless search when the host cannot create Subjects', async () => {
			const wrapper = createWrapperWithVModel();
			await flushPromises();

			await type( wrapper, 'zzz' );

			expect( wrapper.find( '.no-results-slot' ).text() ).toBe( 'neowiki-subject-picker-no-results' );
		} );

		it( 'offers the create option before anything is typed', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( null ) );

			expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Create a new Product' ] );
		} );

		it( 'lists the create option after the search results', async () => {
			searchReturns( [
				{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.' },
				{ id: 's1demo5sssssss1', label: 'Acme Anvils' },
			] );
			const wrapper = await createWrapperOffering( creatorReturning( null ) );

			await type( wrapper, 'ac' );

			expect( menuLabelsOf( wrapper ) ).toEqual( [
				'ACME Inc.',
				'Acme Anvils',
				'Create "ac" as a new Product',
			] );
		} );

		it( 'names the typed text and the schema in the create option once the user has typed', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( null ), { targetSchema: 'Company' } );

			await type( wrapper, 'Widget Co' );

			expect( lastMenuLabelOf( wrapper ) ).toBe( 'Create "Widget Co" as a new Company' );
		} );

		it( 'reports the fruitless search as an unpickable entry above the create option', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( null ) );

			await type( wrapper, 'zzz' );

			expect( menuItemsOf( wrapper )[ 0 ] ).toEqual( {
				value: '__no_results__',
				label: 'No matches found',
				disabled: true,
			} );
		} );

		it( 'leaves the fruitless search to its own entry rather than the Codex slot', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( null ) );

			await type( wrapper, 'zzz' );

			expect( wrapper.find( '.no-results-slot' ).text() ).toBe( '' );
		} );

		it( 'names the new Subject after the typed text when the create option is chosen', async () => {
			const createSubject = creatorReturning( createdSubject( 'Widget X', 'Widget X' ) );
			const wrapper = await createWrapperOffering( createSubject );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( createSubject ).toHaveBeenCalledWith( 'Widget X' );
		} );

		it( 'trims the typed text before naming the new Subject', async () => {
			const createSubject = creatorReturning( createdSubject( 'Widget X', 'Widget X' ) );
			const wrapper = await createWrapperOffering( createSubject );

			await type( wrapper, '  Widget X  ' );
			await chooseLastMenuItem( wrapper );

			expect( createSubject ).toHaveBeenCalledWith( 'Widget X' );
		} );

		it( 'leaves the new Subject unnamed when nothing was typed', async () => {
			const createSubject = creatorReturning( createdSubject( null, 'Product' ) );
			const wrapper = await createWrapperOffering( createSubject );

			await chooseLastMenuItem( wrapper );

			expect( createSubject ).toHaveBeenCalledWith( null );
		} );

		it( 'leaves the new Subject unnamed when only whitespace was typed', async () => {
			const createSubject = creatorReturning( createdSubject( null, 'Product' ) );
			const wrapper = await createWrapperOffering( createSubject );

			await type( wrapper, '   ' );
			await chooseLastMenuItem( wrapper );

			expect( createSubject ).toHaveBeenCalledWith( null );
		} );

		it( 'reports no selection while the Subject is still being created', async () => {
			const neverSettles = vi.fn().mockReturnValue( new Promise<Subject | null>( () => {
				// Left in flight, so the picker is observed while the creation is still running.
			} ) );
			const wrapper = await createWrapperOffering( neverSettles );

			await chooseLastMenuItem( wrapper );

			expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
		} );

		it( 'reports the created Subject as the selection', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( createdSubject( 'Widget X', 'Widget X' ) ) );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo1aaaaaaa1' ] ] );
		} );

		it( 'shows the display name of the created Subject in the field', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( createdSubject( null, 'Product' ) ) );

			await chooseLastMenuItem( wrapper );

			expect( inputTextOf( wrapper ) ).toBe( 'Product' );
		} );

		it( 'reports no selection when the creation is abandoned', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( null ) );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
		} );

		it( 'keeps the typed text in the field when the creation is abandoned', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( null ) );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( inputTextOf( wrapper ) ).toBe( 'Widget X' );
		} );

		// A Subject created here has not been written yet, so nothing can look it up.
		it( 'keeps showing the created Subject once the host commits it, without looking it up', async () => {
			const wrapper = await createWrapperOffering( creatorReturning( createdSubject( 'Widget X', 'Widget X' ) ) );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );
			await wrapper.setProps( { selected: 's1demo1aaaaaaa1' } );
			await flushPromises();

			expect( inputTextOf( wrapper ) ).toBe( 'Widget X' );
			expect( subjectStore.getOrFetchSubject ).not.toHaveBeenCalled();
		} );

		// Mounted with the real Codex component: whether an empty field's menu opens on focus is
		// decided by Codex, from the items the Lookup was built with. A stub cannot show that.
		it( 'opens the menu on focus with an empty field so the create option can be picked without typing', async () => {
			const wrapper = mount( SubjectPicker, {
				props: { selected: null, targetSchema: 'Product', createSubject: creatorReturning( null ) as SubjectCreator },
				attachTo: document.body,
				global: {
					mocks: { $i18n },
					plugins: [ pinia ],
					provide: { [ Service.SubjectLabelSearch ]: mockSubjectLabelSearch },
				},
			} );
			attachedWrappers.push( wrapper );
			await flushPromises();

			await wrapper.find( 'input' ).trigger( 'focus' );
			await nextTick();

			expect( wrapper.find( 'input' ).attributes( 'aria-expanded' ) ).toBe( 'true' );
			expect( wrapper.findAll( '[role="option"]' ).map( ( option ) => option.text() ) )
				.toEqual( [ 'Create a new Product' ] );
		} );
	} );

} );
