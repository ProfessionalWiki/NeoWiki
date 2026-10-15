import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import TargetSchemasInput from '@/components/SchemaEditor/Property/TargetSchemasInput.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SchemaReference } from '@/domain/SchemaReference';
import { createI18nMock, setupMwMock } from '../../../VueTestHelpers.ts';

type TargetSchemasInputWrapper = VueWrapper<InstanceType<typeof TargetSchemasInput>>;

const SUMMARIES = [
	{ name: 'Product', description: 'A product', propertyCount: 2, subjectsAreDependent: false },
	{ name: 'Office', description: 'A physical location', propertyCount: 4, subjectsAreDependent: false },
	{ name: 'City', description: '', propertyCount: 3, subjectsAreDependent: false },
];

// Mounted with the real Codex component: which chips a pick or a removal leaves is decided by
// Codex, so a stubbed field would only assert this component's own wiring back to itself.
describe( 'TargetSchemasInput', () => {
	let pinia: ReturnType<typeof createPinia>;
	const wrappers: VueWrapper[] = [];

	beforeEach( () => {
		setupMwMock();

		pinia = createPinia();
		setActivePinia( pinia );

		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( SUMMARIES );
	} );

	afterEach( () => {
		wrappers.splice( 0 ).forEach( ( wrapper ) => wrapper.unmount() );
	} );

	async function mountInput( targetSchemas: SchemaReference[] ): Promise<TargetSchemasInputWrapper> {
		return mountFocused( { modelValue: targetSchemas } );
	}

	// As under a dialog that can stack the Create schema dialog in front of its own.
	async function mountCreatingInput( targetSchemas: SchemaReference[] ): Promise<TargetSchemasInputWrapper> {
		return mountFocused( { modelValue: targetSchemas, offersCreation: true } );
	}

	// Focused, as the field is when a user picks from it, and attached so that focus lands at all.
	async function mountFocused(
		props: { modelValue: SchemaReference[]; offersCreation?: boolean },
	): Promise<TargetSchemasInputWrapper> {
		const wrapper: TargetSchemasInputWrapper = mount( TargetSchemasInput, {
			props: {
				...props,
				'onUpdate:modelValue': ( value: SchemaReference[] ) => wrapper.setProps( { modelValue: value } ),
			},
			attachTo: document.body,
			global: {
				mocks: { $i18n: createI18nMock() },
				plugins: [ pinia ],
			},
		} );
		wrappers.push( wrapper );
		await flushPromises();
		await field( wrapper ).trigger( 'focus' );
		await nextTick();
		return wrapper;
	}

	function field( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return wrapper.find( 'input' );
	}

	function chipTexts( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-input-chip__text' ).map( ( chip ) => chip.text() );
	}

	function otherSourceChipTexts( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-info-chip' ).map( ( chip ) => chip.text() );
	}

	function listedSchemas( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-menu-item__text__label' ).map( ( label ) => label.text() );
	}

	function checkedSchemas( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-menu-item[aria-checked="true"] .cdx-menu-item__text__label' )
			.map( ( label ) => label.text() );
	}

	function boundTargetSchemas( wrapper: TargetSchemasInputWrapper ): readonly SchemaReference[] {
		return wrapper.props( 'modelValue' );
	}

	function emittedTargetSchemas( wrapper: VueWrapper ): unknown[] {
		return ( wrapper.emitted( 'update:modelValue' ) ?? [] ).map( ( args ) => args[ 0 ] );
	}

	async function pickSchema( wrapper: VueWrapper, name: string ): Promise<void> {
		const option = wrapper.findAll( '.cdx-menu-item' )
			.find( ( candidate ) => candidate.find( '.cdx-menu-item__text__label' ).text() === name );
		expect( option, `no menu entry for ${ name }` ).toBeDefined();
		await option!.trigger( 'click' );
		await flushPromises();
	}

	function chipNamed( wrapper: VueWrapper, text: string ): DOMWrapper<Element> {
		const chip = wrapper.findAll( '.cdx-input-chip' )
			.find( ( candidate ) => candidate.find( '.cdx-input-chip__text' ).text() === text );
		expect( chip, `no chip for ${ text }` ).toBeDefined();
		return chip!;
	}

	async function removeChip( wrapper: VueWrapper, text: string ): Promise<void> {
		await chipNamed( wrapper, text ).find( '.cdx-input-chip__button' ).trigger( 'click' );
		await flushPromises();
	}

	it( 'names its field for assistive technology', async () => {
		const wrapper = await mountInput( [] );

		expect( field( wrapper ).attributes( 'aria-label' ) ).toBe( 'neowiki-property-editor-target-schema' );
	} );

	it( 'shows a target schema from another Source apart from the field, naming its source', async () => {
		const wrapper = await mountInput( [ { source: 'otherwiki', name: 'Person' }, 'Group' ] );

		expect( chipTexts( wrapper ) ).toEqual( [ 'Group' ] );
		expect( otherSourceChipTexts( wrapper ) ).toEqual( [ 'otherwiki:Person' ] );
	} );

	it( 'lists only the schemas whose name contains the typed text', async () => {
		const wrapper = await mountInput( [] );

		await field( wrapper ).setValue( 'ity' );
		await flushPromises();

		expect( listedSchemas( wrapper ) ).toEqual( [ 'City' ] );
	} );

	it( 'shows a picked schema as a chip and checks it', async () => {
		const wrapper = await mountInput( [ 'Office' ] );

		await pickSchema( wrapper, 'City' );

		expect( chipTexts( wrapper ) ).toEqual( [ 'Office', 'City' ] );
		expect( checkedSchemas( wrapper ) ).toEqual( [ 'Office', 'City' ] );
	} );

	it( 'drops the target schema whose chip is removed', async () => {
		const wrapper = await mountInput( [ 'Office', 'City' ] );

		await removeChip( wrapper, 'Office' );

		expect( emittedTargetSchemas( wrapper ) ).toEqual( [ [ 'City' ] ] );
	} );

	it( 'adds a schema of this wiki alongside its namesake from another Source', async () => {
		const wrapper = await mountInput( [ { source: 'otherwiki', name: 'Product' } ] );

		await pickSchema( wrapper, 'Product' );

		expect( emittedTargetSchemas( wrapper ) ).toEqual( [ [ 'Product', { source: 'otherwiki', name: 'Product' } ] ] );
	} );

	it( 'follows target schemas the host replaces', async () => {
		const wrapper = await mountInput( [ 'Office', 'City' ] );

		await wrapper.setProps( { modelValue: [ 'Product' ] } );
		await flushPromises();

		expect( chipTexts( wrapper ) ).toEqual( [ 'Product' ] );
	} );

	// Codex adds the text in the field as a chip of its own when a chip is clicked meanwhile.
	it( 'leaves text typed into the field out of the target schemas', async () => {
		const wrapper = await mountInput( [ 'Office', 'City' ] );

		await field( wrapper ).setValue( 'Prod' );
		await chipNamed( wrapper, 'Office' ).trigger( 'click' );
		await flushPromises();
		await removeChip( wrapper, 'City' );

		expect( emittedTargetSchemas( wrapper ).flat() ).not.toContain( 'Prod' );
	} );

	it( 'starts a fresh search once a schema is picked', async () => {
		const wrapper = await mountInput( [ 'Office' ] );

		await field( wrapper ).setValue( 'Cit' );
		await flushPromises();
		await pickSchema( wrapper, 'City' );

		expect( field( wrapper ).element.value ).toBe( '' );
		expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City' ] );
	} );

	it( 'keeps the typed text when a target schema is removed', async () => {
		const wrapper = await mountInput( [ 'Office', 'City' ] );

		await field( wrapper ).setValue( 'Prod' );
		await flushPromises();
		await removeChip( wrapper, 'Office' );

		expect( field( wrapper ).element.value ).toBe( 'Prod' );
	} );

	it( 'shows the target schemas it holds as chips after a chip is clicked while text is typed', async () => {
		const wrapper = await mountInput( [ 'Office', 'City' ] );

		await field( wrapper ).setValue( 'Prod' );
		await chipNamed( wrapper, 'Office' ).trigger( 'click' );
		await flushPromises();

		expect( chipTexts( wrapper ) ).toEqual( boundTargetSchemas( wrapper ) );
	} );

	it( 'lists and checks a target schema created after the list was fetched', async () => {
		const wrapper = await mountInput( [ 'Office' ] );
		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue(
			[ ...SUMMARIES, { name: 'Artwork', description: '', propertyCount: 0, subjectsAreDependent: false } ],
		);

		await wrapper.setProps( { modelValue: [ 'Office', 'Artwork' ] } );
		await flushPromises();

		expect( checkedSchemas( wrapper ) ).toEqual( [ 'Office', 'Artwork' ] );
	} );

	describe( 'offering to create a schema', () => {
		const OFFER = 'neowiki-schema-picker-create';

		function offerFor( typed: string ): string {
			return 'neowiki-schema-picker-create-named' + typed;
		}

		async function type( wrapper: VueWrapper, text: string ): Promise<void> {
			await field( wrapper ).setValue( text );
			await flushPromises();
		}

		function menuShown( wrapper: VueWrapper ): boolean {
			return field( wrapper ).attributes( 'aria-expanded' ) === 'true';
		}

		// Codex's stylesheet pins the last item of a menu with this class below the list, in view while
		// the list scrolls.
		function pinnedItem( wrapper: VueWrapper ): string | null {
			const labels = wrapper.findAll( '.cdx-menu--has-footer .cdx-menu-item__text__label' );

			return labels.length === 0 ? null : labels[ labels.length - 1 ].text();
		}

		it( 'makes no offer unless asked to', async () => {
			const wrapper = await mountInput( [] );

			await type( wrapper, 'Artwork' );

			expect( listedSchemas( wrapper ) ).toEqual( [] );
		} );

		it( 'shows the offer alone for typed text no schema matches', async () => {
			const wrapper = await mountCreatingInput( [] );

			await type( wrapper, 'Artwork' );

			expect( menuShown( wrapper ) ).toBe( true );
			expect( listedSchemas( wrapper ) ).toEqual( [ offerFor( 'Artwork' ) ] );
		} );

		it( 'pins the offer for the typed text below the schemas it matches', async () => {
			const wrapper = await mountCreatingInput( [] );

			await type( wrapper, 'o' );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', offerFor( 'o' ) ] );
			expect( pinnedItem( wrapper ) ).toBe( offerFor( 'o' ) );
		} );

		it( 'makes no offer for typed text naming a schema that exists', async () => {
			const wrapper = await mountCreatingInput( [] );

			await type( wrapper, 'office' );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Office' ] );
		} );

		it( 'makes no offer for typed text naming a target schema the list leaves out', async () => {
			const wrapper = await mountCreatingInput( [ 'Archived' ] );

			await type( wrapper, 'archived' );

			expect( listedSchemas( wrapper ) ).toEqual( [] );
		} );

		it( 'offers to create a schema named like a target schema of another Source', async () => {
			const wrapper = await mountCreatingInput( [ { source: 'otherwiki', name: 'Person' } ] );

			await type( wrapper, 'Person' );

			expect( listedSchemas( wrapper ) ).toEqual( [ offerFor( 'Person' ) ] );
		} );

		describe( 'chosen', () => {
			async function chooseOffer(): Promise<TargetSchemasInputWrapper> {
				const wrapper = await mountCreatingInput( [ 'Office' ] );
				await type( wrapper, 'Prod' );

				await pickSchema( wrapper, offerFor( 'Prod' ) );

				return wrapper;
			}

			it( 'hands the typed text on', async () => {
				const wrapper = await chooseOffer();

				expect( wrapper.emitted( 'create' ) ).toEqual( [ [ 'Prod', false ] ] );
			} );

			it( 'adds no target schema', async () => {
				const wrapper = await chooseOffer();

				expect( chipTexts( wrapper ) ).toEqual( [ 'Office' ] );
			} );

			it( 'starts a fresh search', async () => {
				const wrapper = await chooseOffer();

				expect( field( wrapper ).element.value ).toBe( '' );
				expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City', OFFER ] );
			} );
		} );
	} );

	describe( 'Schemas of dependent and of standalone Subjects', () => {
		beforeEach( () => {
			useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( [
				{ name: 'Birth', description: '', propertyCount: 2, subjectsAreDependent: true },
				{ name: 'City', description: '', propertyCount: 3, subjectsAreDependent: false },
				{ name: 'Name', description: '', propertyCount: 1, subjectsAreDependent: true },
				{ name: 'Office', description: '', propertyCount: 4, subjectsAreDependent: false },
			] );
		} );

		it( 'offers both while no target is chosen', async () => {
			const wrapper = await mountInput( [] );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Birth', 'City', 'Name', 'Office' ] );
		} );

		it( 'offers only Schemas of dependent Subjects once one of those is a target', async () => {
			const wrapper = await mountInput( [ 'Birth' ] );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Birth', 'Name' ] );
		} );

		it( 'offers only Schemas of standalone Subjects once one of those is a target', async () => {
			const wrapper = await mountInput( [ 'Office' ] );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'City', 'Office' ] );
		} );

		it( 'decides by the first target whose Schema it knows', async () => {
			const wrapper = await mountInput( [ 'Deleted', 'Name' ] );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Birth', 'Name' ] );
		} );

		// Codex shows the line in place of results, under its own class, and keeps an empty one where it has none.
		function noResultsLine( wrapper: VueWrapper ): string {
			const line = wrapper.find( '.cdx-menu__no-results' );

			return line.exists() ? line.text() : '';
		}

		it( 'says why a Schema of standalone Subjects the typed text finds cannot be added beside targets of dependent ones', async () => {
			const wrapper = await mountInput( [ 'Birth' ] );

			await field( wrapper ).setValue( 'Offi' );
			await flushPromises();

			expect( noResultsLine( wrapper ) ).toBe( 'neowiki-property-editor-target-schema-dependent-only' );
		} );

		it( 'says why a Schema of dependent Subjects the typed text finds cannot be added beside targets of standalone ones', async () => {
			const wrapper = await mountInput( [ 'Office' ] );

			await field( wrapper ).setValue( 'Bir' );
			await flushPromises();

			expect( noResultsLine( wrapper ) ).toBe( 'neowiki-property-editor-target-schema-standalone-only' );
		} );

		it( 'says nothing of typed text that finds no Schema at all', async () => {
			const wrapper = await mountInput( [ 'Birth' ] );

			await field( wrapper ).setValue( 'Zeppelin' );
			await flushPromises();

			expect( noResultsLine( wrapper ) ).toBe( '' );
		} );

		it( 'hands on that the targets\' Subjects are dependent with the offer to create a Schema', async () => {
			const wrapper = await mountCreatingInput( [ 'Birth' ] );
			await field( wrapper ).setValue( 'Death' );
			await flushPromises();

			await pickSchema( wrapper, 'neowiki-schema-picker-create-namedDeath' );

			expect( wrapper.emitted( 'create' ) ).toEqual( [ [ 'Death', true ] ] );
		} );

		it( 'hands on that nothing decides it while no target is chosen', async () => {
			const wrapper = await mountCreatingInput( [] );
			await field( wrapper ).setValue( 'Death' );
			await flushPromises();

			await pickSchema( wrapper, 'neowiki-schema-picker-create-namedDeath' );

			expect( wrapper.emitted( 'create' ) ).toEqual( [ [ 'Death', null ] ] );
		} );
	} );

} );
