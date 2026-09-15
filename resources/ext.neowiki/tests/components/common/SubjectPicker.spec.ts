import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { defineComponent, nextTick, shallowRef, type Ref } from 'vue';
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
import { SubjectCreationKey, type SubjectCreation } from '@/components/common/SubjectCreation.ts';

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

function silence(): void {
	return undefined;
}

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

	// The creation capability reaches the picker the way its host supplies it: through provide,
	// absent for a host that cannot carry a Subject the wiki does not hold yet.
	function createWrapperWithVModel(
		props: Partial<InstanceType<typeof SubjectPicker>['$props']> = {},
		subjectCreation: SubjectCreation | undefined = undefined,
	): VueWrapper {
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
					...( subjectCreation === undefined ? {} : { [ SubjectCreationKey as symbol ]: subjectCreation } ),
				},
				stubs: { CdxLookup: CdxLookupWithVModel },
			},
		} );
	}

	function labellessSubject( id: string, displayName: string, schemaName: string ): Subject {
		return new Subject( new SubjectId( id ), null, displayName, false, schemaName, new StatementList( [] ) );
	}

	function wikiHolds( subject: Subject ): void {
		subjectStore.getOrFetchSubject = vi.fn().mockResolvedValue( subject );
	}

	function searchReturns( results: { id: string; label: string }[] ): void {
		( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockResolvedValue( results );
	}

	function menuItemsOf( wrapper: VueWrapper ): MenuItemData[] {
		return wrapper.findComponent( CdxLookupWithVModel ).props( 'menuItems' ) as MenuItemData[];
	}

	function menuLabelsOf( wrapper: VueWrapper ): string[] {
		return menuItemsOf( wrapper ).map( ( item ) => String( item.label ) );
	}

	// Only the field's value: the picker ignores CdxLookup's `input` event, which Codex also emits for
	// text it writes itself. Typing waits for the name the picker writes into the field on mount, as
	// anyone typing in the browser does.
	async function type( wrapper: VueWrapper, text: string ): Promise<void> {
		await flushPromises();
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:input-value', text );
		await flushPromises();
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

		await type( wrapper, 'acme' );

		expect( mockSubjectLabelSearch.searchSubjectLabels ).toHaveBeenCalledWith( 'acme', 'Company' );
	} );

	it( 'populates menu items from search results', async () => {
		( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.' },
			{ id: 's1demo5sssssss1', label: 'Professional Wiki GmbH' },
		] );

		const wrapper = createWrapper( { targetSchema: 'Company' } );
		const lookup = wrapper.findComponent( CdxLookup );

		await type( wrapper, 'a' );

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

		await type( wrapper, 'Foo' );
		expect( lookup.props( 'menuItems' ) ).toHaveLength( 1 );

		await type( wrapper, '' );
		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'shows empty results when API returns no matches', async () => {
		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		await type( wrapper, 'zzzzz' );

		expect( lookup.props( 'menuItems' ) ).toEqual( [] );
	} );

	it( 'shows empty results when API call fails', async () => {
		( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockRejectedValue( new Error( 'Network error' ) );

		const wrapper = createWrapper();
		const lookup = wrapper.findComponent( CdxLookup );

		await type( wrapper, 'test' );

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
			false,
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
			false,
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

		await type( wrapper, 'first' );
		await type( wrapper, 'second' );

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'Second Result', value: 's1demo5sssssss1' },
		] );

		resolveFirst!( [ { id: 's1demo1aaaaaaa1', label: 'Stale Result' } ] );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'Second Result', value: 's1demo5sssssss1' },
		] );
	} );

	it( 'does not propagate a null selection reported by Codex', async () => {
		subjectStore.getOrFetchSubject = vi.fn().mockResolvedValue(
			new Subject( new SubjectId( 's1demo1aaaaaaa1' ), 'ACME Inc.', 'ACME Inc.', false, 'Company', new StatementList( [] ) ),
		);

		const wrapper = createWrapperWithVModel( { selected: 's1demo1aaaaaaa1' } );
		await flushPromises();

		const lookup = wrapper.findComponent( CdxLookupWithVModel );
		lookup.vm.$emit( 'update:input-value', 'ACME In' );
		lookup.vm.$emit( 'update:selected', null );
		await flushPromises();

		expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
	} );

	it( 'shows error message on blur with text but no selection', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
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
		await flushPromises();

		lookup.vm.$emit( 'blur' );

		expect( wrapper.emitted( 'blur' ) ).toEqual( [ [ true ] ] );
	} );

	it( 'clears error when subject is selected', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
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
		await flushPromises();
		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( true );

		lookup.vm.$emit( 'update:input-value', 'new text' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
	} );

	it( 'sets status to error when there is unmatched text', async () => {
		const wrapper = createWrapperWithVModel();
		await flushPromises();
		const lookup = wrapper.findComponent( CdxLookupWithVModel );

		lookup.vm.$emit( 'update:input-value', 'some text' );
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
		await flushPromises();
		lookup.vm.$emit( 'blur' );
		await wrapper.vm.$nextTick();

		expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( true );

		await type( wrapper, '' );
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

	describe( 'finding the target by its id', () => {
		const TARGET_ID = 's1demo1aaaaaaa1';
		const OTHER_ID = 's1demo5sssssss1';

		it( 'offers the Subject an entered id names, under its display name', async () => {
			wikiHolds( labellessSubject( TARGET_ID, 'ACME Inc.', 'Company' ) );
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, TARGET_ID );

			expect( menuItemsOf( wrapper ) ).toEqual( [ { label: 'ACME Inc.', value: TARGET_ID } ] );
		} );

		it( 'searches no labels once an id has named a usable Subject', async () => {
			wikiHolds( labellessSubject( TARGET_ID, 'ACME Inc.', 'Company' ) );
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, TARGET_ID );

			expect( mockSubjectLabelSearch.searchSubjectLabels ).not.toHaveBeenCalled();
		} );

		// An id is fifteen base58 characters, which is also the shape of an ordinary word: typing
		// one must still search labels, or a Subject named after such a word becomes unfindable.
		it( 'searches labels for an id-shaped word that names no Subject', async () => {
			searchReturns( [ { id: TARGET_ID, label: 'Straightforward Solutions' } ] );
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, 'straightforward' );

			expect( menuItemsOf( wrapper ) ).toEqual( [
				{ label: 'Straightforward Solutions', value: TARGET_ID },
			] );
		} );

		it( 'searches labels for an id naming a Subject of another Schema', async () => {
			wikiHolds( labellessSubject( TARGET_ID, 'Ada Lovelace', 'Person' ) );
			searchReturns( [ { id: OTHER_ID, label: 'Acme Anvils' } ] );
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, TARGET_ID );

			expect( menuItemsOf( wrapper ) ).toEqual( [ { label: 'Acme Anvils', value: OTHER_ID } ] );
		} );

		it( 'offers nothing for an id naming a Subject of another Schema', async () => {
			wikiHolds( labellessSubject( TARGET_ID, 'Ada Lovelace', 'Person' ) );
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, TARGET_ID );

			expect( menuItemsOf( wrapper ) ).toEqual( [] );
		} );

		it( 'searches labels for a padded name without its padding', async () => {
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, '  Acme  ' );

			expect( mockSubjectLabelSearch.searchSubjectLabels ).toHaveBeenCalledWith( 'Acme', 'Company' );
		} );

		it( 'reads the Subject named by a pasted id despite the whitespace around it', async () => {
			wikiHolds( labellessSubject( TARGET_ID, 'ACME Inc.', 'Company' ) );
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, `  ${ TARGET_ID }\n` );

			expect( menuItemsOf( wrapper ) ).toEqual( [ { label: 'ACME Inc.', value: TARGET_ID } ] );
		} );

		it( 'ignores an id lookup that lands after the field was cleared', async () => {
			let answerLookup: ( subject: Subject ) => void;
			subjectStore.getOrFetchSubject = vi.fn().mockReturnValue(
				new Promise<Subject>( ( resolve ) => {
					answerLookup = resolve;
				} ),
			);
			const wrapper = createWrapperWithVModel( { targetSchema: 'Company' } );

			await type( wrapper, TARGET_ID );
			await type( wrapper, '' );
			answerLookup!( labellessSubject( TARGET_ID, 'ACME Inc.', 'Company' ) );
			await flushPromises();

			expect( menuItemsOf( wrapper ) ).toEqual( [] );
		} );
	} );

	describe( 'creating the target on the spot', () => {
		const attachedWrappers: VueWrapper[] = [];

		const EXISTING_TARGET_ID = 's1target1aaaaa1';
		const DRAFT_ID = 's1draft1aaaaaa1';
		const OTHER_DRAFT_ID = 's1draft2bbbbbb1';

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
			vi.restoreAllMocks();
		} );

		function subjectNamed( id: string, name: string, schemaName: string ): Subject {
			return new Subject( new SubjectId( id ), name, name, false, schemaName, new StatementList( [] ) );
		}

		function createdSubject( label: string | null, displayName: string ): Subject {
			return new Subject(
				new SubjectId( 's1demo1aaaaaaa1' ),
				label,
				displayName,
				false,
				'Product',
				new StatementList( [] ),
			);
		}

		type SubjectCreator = ( schemaName: string, label: string | null ) => Promise<Subject | null>;

		function creatorReturning( subject: Subject | null ): Mock<SubjectCreator> {
			return vi.fn<SubjectCreator>().mockResolvedValue( subject );
		}

		function creatorThrowing(): Mock<SubjectCreator> {
			return vi.fn<SubjectCreator>().mockRejectedValue( new Error( 'the host could not create it' ) );
		}

		// Its Subject arrives only once the test calls finish, so the picker can be observed while the
		// creation is still in flight.
		function creatorPending(): { create: Mock<SubjectCreator>; finish: ( subject: Subject | null ) => void } {
			let finish!: ( subject: Subject | null ) => void;
			const create = vi.fn<SubjectCreator>().mockReturnValue(
				new Promise<Subject | null>( ( resolve ) => {
					finish = resolve;
				} ),
			);
			return { create, finish };
		}

		// Stands in for the editor hosting the picker: it keeps the Subjects invented this session in
		// a ref, as the editor does, so the picker sees a rename the same way it would there, and it
		// answers only for the Schema it was asked about, as the editor does.
		function hostOffering(
			create: Mock<SubjectCreator>,
			drafts: Ref<readonly Subject[]> = shallowRef( [] ),
		): SubjectCreation {
			return {
				// What the editor creates joins its drafts, which is the only place a name for such a
				// Subject can come from: the wiki has never been told it exists.
				create: async ( schemaName, label ) => {
					const subject = await create( schemaName, label );
					drafts.value = subject === null ? drafts.value : [ ...drafts.value, subject ];
					return subject;
				},
				drafts: ( schemaName ) => drafts.value.filter( ( draft ) => draft.getSchemaName() === schemaName ),
			};
		}

		// Awaited, because the picker resolves the label of its committed Subject on mount and
		// writes the answer into the field: typing before that lands would be overwritten by it.
		async function createWrapperOffering(
			subjectCreation: SubjectCreation,
			props: Partial<InstanceType<typeof SubjectPicker>['$props']> = {},
		): Promise<VueWrapper> {
			const wrapper = createWrapperWithVModel( props, subjectCreation );
			await flushPromises();
			return wrapper;
		}

		// A picker whose relation already points at a Subject the wiki holds, which is what puts the
		// name of that Subject in the field before the user does anything.
		async function createWrapperHolding( targetName: string, subjectCreation: SubjectCreation ): Promise<VueWrapper> {
			subjectStore.getOrFetchSubject = vi.fn().mockResolvedValue(
				subjectNamed( EXISTING_TARGET_ID, targetName, 'Company' ),
			);

			return createWrapperOffering( subjectCreation, { selected: EXISTING_TARGET_ID, targetSchema: 'Company' } );
		}

		function lastMenuLabelOf( wrapper: VueWrapper ): string {
			const labels = menuLabelsOf( wrapper );
			return labels[ labels.length - 1 ];
		}

		function inputTextOf( wrapper: VueWrapper ): string {
			return String( wrapper.findComponent( CdxLookupWithVModel ).props( 'inputValue' ) );
		}

		function statusOf( wrapper: VueWrapper ): string {
			return String( wrapper.findComponent( CdxLookupWithVModel ).props( 'status' ) );
		}

		async function leaveField( wrapper: VueWrapper ): Promise<void> {
			wrapper.findComponent( CdxLookupWithVModel ).vm.$emit( 'blur' );
			await nextTick();
		}

		// Picked by its position rather than by the sentinel value the component uses internally,
		// so the test states what the user does: choose the last entry, the create one.
		async function chooseLastMenuItem( wrapper: VueWrapper ): Promise<void> {
			const items = menuItemsOf( wrapper );
			wrapper.findComponent( CdxLookupWithVModel ).vm.$emit( 'update:selected', items[ items.length - 1 ].value );
			await flushPromises();
		}

		async function chooseFirstMenuItem( wrapper: VueWrapper ): Promise<void> {
			wrapper.findComponent( CdxLookupWithVModel ).vm.$emit( 'update:selected', menuItemsOf( wrapper )[ 0 ].value );
			await flushPromises();
		}

		function searchNeverAnswers(): void {
			( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockReturnValue(
				new Promise<{ id: string; label: string }[]>( () => {
					// Left in flight, so the menu is observed while the search is still running.
				} ),
			);
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
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Create a new Product' ] );
		} );

		it( 'lists the create option after the search results', async () => {
			searchReturns( [
				{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.' },
				{ id: 's1demo5sssssss1', label: 'Acme Anvils' },
			] );
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, 'ac' );

			expect( menuLabelsOf( wrapper ) ).toEqual( [
				'ACME Inc.',
				'Acme Anvils',
				'Create "ac" as a new Product',
			] );
		} );

		it( 'lists the Subject an id names above the create option, in place of the fruitless search', async () => {
			wikiHolds( subjectNamed( EXISTING_TARGET_ID, 'Acme Anvil', 'Product' ) );
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, EXISTING_TARGET_ID );

			expect( menuLabelsOf( wrapper ) ).toEqual( [
				'Acme Anvil',
				`Create "${ EXISTING_TARGET_ID }" as a new Product`,
			] );
		} );

		it( 'names the typed text and the schema in the create option once the user has typed', async () => {
			const wrapper = await createWrapperOffering(
				hostOffering( creatorReturning( null ) ),
				{ targetSchema: 'Company' },
			);

			await type( wrapper, 'Widget Co' );

			expect( lastMenuLabelOf( wrapper ) ).toBe( 'Create "Widget Co" as a new Company' );
		} );

		it( 'reports the fruitless search as an unpickable entry above the create option', async () => {
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, 'zzz' );

			expect( menuItemsOf( wrapper )[ 0 ] ).toEqual( {
				value: '__no_results__',
				label: 'No matches found',
				disabled: true,
			} );
		} );

		it( 'reports a fruitless search for an id no readable Subject has', async () => {
			subjectStore.getOrFetchSubject = vi.fn().mockRejectedValue( new Error( 'not found' ) );
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, EXISTING_TARGET_ID );

			expect( menuLabelsOf( wrapper ) ).toEqual( [
				'No matches found',
				`Create "${ EXISTING_TARGET_ID }" as a new Product`,
			] );
		} );

		it( 'leaves the fruitless search to its own entry rather than the Codex slot', async () => {
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, 'zzz' );

			expect( wrapper.find( '.no-results-slot' ).text() ).toBe( '' );
		} );

		it( 'reports no fruitless search while one is still running', async () => {
			searchNeverAnswers();
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, 'zzz' );

			expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Create "zzz" as a new Product' ] );
		} );

		it( 'reports no selection when the unpickable entry of a fruitless search is chosen', async () => {
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );
			await type( wrapper, 'zzz' );

			await chooseFirstMenuItem( wrapper );

			expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
		} );

		it( 'creates a Subject of the target Schema named after the typed text', async () => {
			const create = creatorReturning( createdSubject( 'Widget X', 'Widget X' ) );
			const wrapper = await createWrapperOffering( hostOffering( create ), { targetSchema: 'Company' } );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( create ).toHaveBeenCalledWith( 'Company', 'Widget X' );
		} );

		it( 'trims the typed text before naming the new Subject', async () => {
			const create = creatorReturning( createdSubject( 'Widget X', 'Widget X' ) );
			const wrapper = await createWrapperOffering( hostOffering( create ) );

			await type( wrapper, '  Widget X  ' );
			await chooseLastMenuItem( wrapper );

			expect( create ).toHaveBeenCalledWith( 'Product', 'Widget X' );
		} );

		it( 'leaves the new Subject unnamed when nothing was typed', async () => {
			const create = creatorReturning( createdSubject( null, 'Product' ) );
			const wrapper = await createWrapperOffering( hostOffering( create ) );

			await chooseLastMenuItem( wrapper );

			expect( create ).toHaveBeenCalledWith( 'Product', null );
		} );

		it( 'leaves the new Subject unnamed when only whitespace was typed', async () => {
			const create = creatorReturning( createdSubject( null, 'Product' ) );
			const wrapper = await createWrapperOffering( hostOffering( create ) );

			await type( wrapper, '   ' );
			await chooseLastMenuItem( wrapper );

			expect( create ).toHaveBeenCalledWith( 'Product', null );
		} );

		it( 'reports no selection while the Subject is still being created', async () => {
			const neverSettles = vi.fn<SubjectCreator>().mockReturnValue( new Promise<Subject | null>( () => {
				// Left in flight, so the picker is observed while the creation is still running.
			} ) );
			const wrapper = await createWrapperOffering( hostOffering( neverSettles ) );

			await chooseLastMenuItem( wrapper );

			expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
		} );

		it( 'reports the created Subject as the selection', async () => {
			const wrapper = await createWrapperOffering(
				hostOffering( creatorReturning( createdSubject( 'Widget X', 'Widget X' ) ) ),
			);

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo1aaaaaaa1' ] ] );
		} );

		it( 'shows the display name of the created Subject in the field', async () => {
			const wrapper = await createWrapperOffering(
				hostOffering( creatorReturning( createdSubject( null, 'Product' ) ) ),
			);

			await chooseLastMenuItem( wrapper );

			expect( inputTextOf( wrapper ) ).toBe( 'Product' );
		} );

		it( 'reports no selection when the creation is abandoned', async () => {
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
		} );

		it( 'keeps the typed text in the field when the creation is abandoned', async () => {
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );

			expect( inputTextOf( wrapper ) ).toBe( 'Widget X' );
		} );

		// A Subject created here has not been written yet, so nothing can look it up.
		it( 'keeps showing the created Subject once the host commits it, without looking it up', async () => {
			const wrapper = await createWrapperOffering(
				hostOffering( creatorReturning( createdSubject( 'Widget X', 'Widget X' ) ) ),
			);

			await type( wrapper, 'Widget X' );
			await chooseLastMenuItem( wrapper );
			await wrapper.setProps( { selected: 's1demo1aaaaaaa1' } );
			await flushPromises();

			expect( inputTextOf( wrapper ) ).toBe( 'Widget X' );
			expect( subjectStore.getOrFetchSubject ).not.toHaveBeenCalled();
		} );

		it( 'keeps a creation that lands after the field was cleared', async () => {
			const { create, finish } = creatorPending();
			const wrapper = await createWrapperOffering( hostOffering( create ) );
			await type( wrapper, 'Widget Co' );
			await chooseLastMenuItem( wrapper );

			await type( wrapper, '' );
			finish( createdSubject( 'Widget Co', 'Widget Co' ) );
			await flushPromises();

			expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo1aaaaaaa1' ] ] );
		} );

		it( 'keeps a creation that lands after the field was cleared while a search was still out', async () => {
			const { create, finish } = creatorPending();
			searchNeverAnswers();
			const wrapper = await createWrapperOffering( hostOffering( create ) );
			await type( wrapper, 'Widget Co' );
			await chooseLastMenuItem( wrapper );

			await type( wrapper, '' );
			finish( createdSubject( 'Widget Co', 'Widget Co' ) );
			await flushPromises();

			expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo1aaaaaaa1' ] ] );
		} );

		it( 'keeps what a search still out finds when the creation is refused', async () => {
			let answerSearch: ( results: { id: string; label: string }[] ) => void = silence;
			( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockReturnValue(
				new Promise<{ id: string; label: string }[]>( ( resolve ) => {
					answerSearch = resolve;
				} ),
			);
			const wrapper = await createWrapperOffering( hostOffering( creatorReturning( null ) ) );
			await type( wrapper, 'Widget' );
			await chooseLastMenuItem( wrapper );

			answerSearch( [ { id: 's1demo5sssssss1', label: 'Widget Co' } ] );
			await flushPromises();

			expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Widget Co', 'Create "Widget" as a new Product' ] );
		} );

		it( 'drops what a search still out finds once the creation has landed', async () => {
			let answerSearch: ( results: { id: string; label: string }[] ) => void = silence;
			( mockSubjectLabelSearch.searchSubjectLabels as ReturnType<typeof vi.fn> ).mockReturnValue(
				new Promise<{ id: string; label: string }[]>( ( resolve ) => {
					answerSearch = resolve;
				} ),
			);
			const wrapper = await createWrapperOffering(
				hostOffering( creatorReturning( createdSubject( 'Widget Co', 'Widget Co' ) ) ),
			);
			await type( wrapper, 'Widget' );
			await chooseLastMenuItem( wrapper );

			answerSearch( [ { id: 's1demo5sssssss1', label: 'Widget Ltd' } ] );
			await flushPromises();

			expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Widget Co', 'Create a new Product' ] );
		} );

		it( 'abandons a creation that lands after another Subject has been selected', async () => {
			const { create, finish } = creatorPending();
			searchReturns( [ { id: 's1demo5sssssss1', label: 'ACME Inc.' } ] );
			const wrapper = await createWrapperOffering( hostOffering( create ) );
			await type( wrapper, 'wid' );
			await chooseLastMenuItem( wrapper );

			await type( wrapper, 'acme' );
			await chooseFirstMenuItem( wrapper );
			finish( createdSubject( 'Widget X', 'Widget X' ) );
			await flushPromises();

			expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo5sssssss1' ] ] );
		} );

		describe( 'with a target already selected', () => {
			it( 'offers creating an unnamed Subject rather than one named after the target', async () => {
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorReturning( null ) ) );

				expect( lastMenuLabelOf( wrapper ) ).toBe( 'Create a new Company' );
			} );

			it( 'searches again when the target name is typed back after other text', async () => {
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorReturning( null ) ) );

				await type( wrapper, 'Widget Co' );
				await type( wrapper, 'ACME Inc.' );

				expect( mockSubjectLabelSearch.searchSubjectLabels ).toHaveBeenLastCalledWith( 'ACME Inc.', 'Company' );
			} );

			it( 'names the create option after text typed over the target', async () => {
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorReturning( null ) ) );

				await type( wrapper, 'Widget Co' );

				expect( lastMenuLabelOf( wrapper ) ).toBe( 'Create "Widget Co" as a new Company' );
			} );

			it( 'keeps reporting the target when the creation is abandoned', async () => {
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorReturning( null ) ) );

				await chooseLastMenuItem( wrapper );

				expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
			} );

			it( 'keeps showing the target in the field when the creation is abandoned', async () => {
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorReturning( null ) ) );

				await chooseLastMenuItem( wrapper );

				expect( inputTextOf( wrapper ) ).toBe( 'ACME Inc.' );
			} );

			it( 'reports no unmatched text on leaving the field after an abandoned creation', async () => {
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorReturning( null ) ) );

				await chooseLastMenuItem( wrapper );
				await leaveField( wrapper );

				expect( wrapper.emitted( 'blur' ) ).toEqual( [ [ false ] ] );
			} );

			it( 'keeps the field out of the error status after an abandoned creation', async () => {
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorReturning( null ) ) );

				await chooseLastMenuItem( wrapper );
				await leaveField( wrapper );

				expect( statusOf( wrapper ) ).toBe( 'default' );
			} );

			it( 'keeps reporting the target when the creation throws', async () => {
				// The picker logs the host's failure; the test asserts on the field, not on the console.
				vi.spyOn( console, 'error' ).mockImplementation( silence );
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorThrowing() ) );

				await chooseLastMenuItem( wrapper );

				expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
			} );

			it( 'keeps showing the target in the field when the creation throws', async () => {
				// The picker logs the host's failure; the test asserts on the field, not on the console.
				vi.spyOn( console, 'error' ).mockImplementation( silence );
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorThrowing() ) );

				await chooseLastMenuItem( wrapper );

				expect( inputTextOf( wrapper ) ).toBe( 'ACME Inc.' );
			} );

			it( 'keeps the field out of the error status when the creation throws', async () => {
				// The picker logs the host's failure; the test asserts on the field, not on the console.
				vi.spyOn( console, 'error' ).mockImplementation( silence );
				const wrapper = await createWrapperHolding( 'ACME Inc.', hostOffering( creatorThrowing() ) );

				await chooseLastMenuItem( wrapper );
				await leaveField( wrapper );

				expect( statusOf( wrapper ) ).toBe( 'default' );
			} );
		} );

		describe( 'with Subjects invented earlier in the session', () => {
			// Shallow, so the Subjects keep their type: ref() unwraps a class into a bare object.
			function draftsHolding( ...drafts: Subject[] ): Ref<readonly Subject[]> {
				return shallowRef<readonly Subject[]>( drafts );
			}

			it( 'lists the session drafts of its own Schema above the search results', async () => {
				searchReturns( [ { id: 's1demo1aaaaaaa1', label: 'ACME Inc.' } ] );
				const drafts = draftsHolding( subjectNamed( DRAFT_ID, 'Anvil Co', 'Company' ) );
				const wrapper = await createWrapperOffering(
					hostOffering( creatorReturning( null ), drafts ),
					{ targetSchema: 'Company' },
				);

				await type( wrapper, 'a' );

				expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Anvil Co', 'ACME Inc.', 'Create "a" as a new Company' ] );
			} );

			// The picker names Subjects bare on purpose: a menu label becomes the field's own text,
			// which the user can edit and which feeds the offer to create under what they typed.
			// Marking it would put "(unnamed Company)" on the way into a stored label.
			it( 'names a label-less draft bare, without the generated-name marker', async () => {
				const drafts = draftsHolding(
					new Subject( new SubjectId( DRAFT_ID ), null, 'Company', true, 'Company', new StatementList( [] ) ),
				);
				const wrapper = await createWrapperOffering(
					hostOffering( creatorReturning( null ), drafts ),
					{ targetSchema: 'Company' },
				);

				expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Company', 'Create a new Company' ] );
			} );

			it( 'leaves out the session drafts of another Schema', async () => {
				const drafts = draftsHolding(
					subjectNamed( DRAFT_ID, 'Anvil Co', 'Company' ),
					subjectNamed( OTHER_DRAFT_ID, 'Widget X', 'Product' ),
				);
				const wrapper = await createWrapperOffering(
					hostOffering( creatorReturning( null ), drafts ),
					{ targetSchema: 'Company' },
				);

				expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Anvil Co', 'Create a new Company' ] );
			} );

			it( 'reports a chosen session draft as the selection', async () => {
				const drafts = draftsHolding( subjectNamed( DRAFT_ID, 'Anvil Co', 'Company' ) );
				const wrapper = await createWrapperOffering(
					hostOffering( creatorReturning( null ), drafts ),
					{ targetSchema: 'Company' },
				);

				await chooseFirstMenuItem( wrapper );

				expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ DRAFT_ID ] ] );
			} );

			it( 'matches the session drafts against the typed text regardless of case', async () => {
				const drafts = draftsHolding(
					subjectNamed( DRAFT_ID, 'Anvil Co', 'Company' ),
					subjectNamed( OTHER_DRAFT_ID, 'Zeta Ltd', 'Company' ),
				);
				const wrapper = await createWrapperOffering(
					hostOffering( creatorReturning( null ), drafts ),
					{ targetSchema: 'Company' },
				);

				await type( wrapper, 'anv' );

				expect( menuLabelsOf( wrapper ) ).toEqual( [ 'Anvil Co', 'Create "anv" as a new Company' ] );
			} );

			it( 'renames the field when the host renames the draft it points at', async () => {
				const drafts = draftsHolding( subjectNamed( DRAFT_ID, 'Company', 'Company' ) );
				const wrapper = await createWrapperOffering(
					hostOffering( creatorReturning( null ), drafts ),
					{ selected: DRAFT_ID, targetSchema: 'Company' },
				);

				drafts.value = [ subjectNamed( DRAFT_ID, 'Anvil Co', 'Company' ) ];
				await nextTick();

				expect( inputTextOf( wrapper ) ).toBe( 'Anvil Co' );
			} );
		} );

		// Mounted with the real Codex component, for what Codex decides on its own: the stub has
		// none of its watchers.
		async function mountInCodex(
			subjectCreation: SubjectCreation,
			props: Partial<InstanceType<typeof SubjectPicker>['$props']> = {},
		): Promise<VueWrapper> {
			const wrapper = mount( SubjectPicker, {
				props: { selected: null, targetSchema: 'Product', ...props },
				attachTo: document.body,
				global: {
					mocks: { $i18n },
					plugins: [ pinia ],
					provide: {
						[ Service.SubjectLabelSearch ]: mockSubjectLabelSearch,
						[ SubjectCreationKey as symbol ]: subjectCreation,
					},
				},
			} );
			attachedWrappers.push( wrapper );
			await flushPromises();
			return wrapper;
		}

		// Whether an empty field's menu opens on focus is decided by Codex, from the items the
		// Lookup was built with.
		it( 'opens the menu on focus with an empty field so the create option can be picked without typing', async () => {
			const wrapper = await mountInCodex( hostOffering( creatorReturning( null ) ) );

			await wrapper.find( 'input' ).trigger( 'focus' );
			await nextTick();

			expect( wrapper.find( 'input' ).attributes( 'aria-expanded' ) ).toBe( 'true' );
			expect( wrapper.findAll( '[role="option"]' ).map( ( option ) => option.text() ) )
				.toEqual( [ 'Create a new Product' ] );
		} );

		async function typeInField( wrapper: VueWrapper, text: string ): Promise<void> {
			const input = wrapper.find( 'input' );
			await input.trigger( 'focus' );
			await input.setValue( text );
			await flushPromises();
		}

		async function chooseFirstOption( wrapper: VueWrapper ): Promise<void> {
			await wrapper.findAll( '[role="option"]' )[ 0 ].trigger( 'click' );
			await flushPromises();
		}

		// The create option is the last one, as in the stubbed tests above.
		async function chooseLastOption( wrapper: VueWrapper ): Promise<void> {
			const options = wrapper.findAll( '[role="option"]' );
			await options[ options.length - 1 ].trigger( 'click' );
			await flushPromises();
		}

		function fieldTextOf( wrapper: VueWrapper ): string {
			return wrapper.find( 'input' ).element.value;
		}

		describe( 'replacing the target in the real Codex Lookup', () => {
			async function mountHoldingTarget( subjectCreation: SubjectCreation ): Promise<VueWrapper> {
				wikiHolds( subjectNamed( EXISTING_TARGET_ID, 'ACME Inc.', 'Product' ) );
				return mountInCodex( subjectCreation, { selected: EXISTING_TARGET_ID } );
			}

			it( 'reports a Subject created over the target as the selection', async () => {
				const wrapper = await mountHoldingTarget(
					hostOffering( creatorReturning( createdSubject( 'Zurich Depot', 'Zurich Depot' ) ) ),
				);

				await typeInField( wrapper, 'Zurich Depot' );
				await chooseLastOption( wrapper );

				expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ 's1demo1aaaaaaa1' ] ] );
			} );

			// Typed with padding, which the field keeps unless the created Subject's name replaces it.
			it( 'shows a Subject created over the target in the field', async () => {
				const wrapper = await mountHoldingTarget(
					hostOffering( creatorReturning( createdSubject( 'Zurich Depot', 'Zurich Depot' ) ) ),
				);

				await typeInField( wrapper, '  Zurich Depot  ' );
				await chooseLastOption( wrapper );

				expect( fieldTextOf( wrapper ) ).toBe( 'Zurich Depot' );
			} );

			it( 'keeps the text typed over the target when the creation is abandoned', async () => {
				const wrapper = await mountHoldingTarget( hostOffering( creatorReturning( null ) ) );

				await typeInField( wrapper, 'Zurich Depot' );
				await chooseLastOption( wrapper );

				expect( fieldTextOf( wrapper ) ).toBe( 'Zurich Depot' );
			} );

			it( 'keeps the text typed over the target when the creation throws', async () => {
				// The picker logs the host's failure; the test asserts on the field, not on the console.
				vi.spyOn( console, 'error' ).mockImplementation( silence );
				const wrapper = await mountHoldingTarget( hostOffering( creatorThrowing() ) );

				await typeInField( wrapper, 'Zurich Depot' );
				await chooseLastOption( wrapper );

				expect( fieldTextOf( wrapper ) ).toBe( 'Zurich Depot' );
			} );

			it( 'flags text typed over the target as unmatched on leaving the field', async () => {
				const wrapper = await mountHoldingTarget( hostOffering( creatorReturning( null ) ) );

				await typeInField( wrapper, 'Zurich Depot' );
				await wrapper.find( 'input' ).trigger( 'blur' );

				expect( wrapper.emitted( 'blur' ) ).toEqual( [ [ true ] ] );
			} );

			it( 'empties the relation when the target is cleared without being typed over', async () => {
				const wrapper = await mountHoldingTarget( hostOffering( creatorReturning( null ) ) );

				await typeInField( wrapper, '' );

				expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ null ] ] );
			} );

			it( 'empties the relation when the text typed over the target is cleared', async () => {
				const wrapper = await mountHoldingTarget( hostOffering( creatorReturning( null ) ) );

				await typeInField( wrapper, 'Zu' );
				await typeInField( wrapper, '' );

				expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ null ] ] );
			} );
		} );

		describe( 'picking in the real Codex Lookup', () => {
			const PICKED_ID = 's1demo5sssssss1';

			it( 'searches once for text that a pick then replaces with its label', async () => {
				searchReturns( [ { id: PICKED_ID, label: 'ACME Inc.' } ] );
				const wrapper = await mountInCodex( hostOffering( creatorReturning( null ) ) );

				await typeInField( wrapper, 'acme' );
				await chooseFirstOption( wrapper );

				expect( mockSubjectLabelSearch.searchSubjectLabels ).toHaveBeenCalledTimes( 1 );
			} );

			// Typed as the picked Subject's label, so the pick changes no text: Codex writes nothing into
			// the field and emits nothing for it.
			it( 'abandons a pending creation once another Subject is picked', async () => {
				const { create, finish } = creatorPending();
				searchReturns( [ { id: PICKED_ID, label: 'Widget Co' } ] );
				const wrapper = await mountInCodex( hostOffering( create ) );

				await typeInField( wrapper, 'Widget Co' );
				await chooseLastOption( wrapper );
				await chooseFirstOption( wrapper );
				finish( createdSubject( 'Widget Co', 'Widget Co' ) );
				await flushPromises();

				expect( wrapper.emitted( 'update:selected' ) ).toEqual( [ [ PICKED_ID ] ] );
			} );

			// Codex blanks a field holding other text when its selection turns to an item the menu does
			// not list, as the host's target is. That is Codex writing, not the user emptying the field.
			it( 'keeps a target the host sets over text the user had typed', async () => {
				const wrapper = await mountInCodex( hostOffering( creatorReturning( null ) ) );
				wikiHolds( subjectNamed( EXISTING_TARGET_ID, 'ACME Inc.', 'Product' ) );

				await typeInField( wrapper, 'Zu' );
				await wrapper.setProps( { selected: EXISTING_TARGET_ID } );
				await flushPromises();

				expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
			} );
		} );
	} );

} );
