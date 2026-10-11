import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import TargetSchemasInput from '@/components/SchemaEditor/Property/TargetSchemasInput.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SchemaReference } from '@/domain/SchemaReference';
import { createI18nMock } from '../../../VueTestHelpers.ts';

type TargetSchemasInputWrapper = VueWrapper<InstanceType<typeof TargetSchemasInput>>;

const SUMMARIES = [
	{ name: 'Product', description: 'A product', propertyCount: 2 },
	{ name: 'Office', description: 'A physical location', propertyCount: 4 },
	{ name: 'City', description: '', propertyCount: 3 },
];

// Mounted with the real Codex component: which chips a pick or a removal leaves is decided by
// Codex, so a stubbed field would only assert this component's own wiring back to itself.
describe( 'TargetSchemasInput', () => {
	let pinia: ReturnType<typeof createPinia>;
	const wrappers: VueWrapper[] = [];

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( SUMMARIES );
	} );

	afterEach( () => {
		wrappers.splice( 0 ).forEach( ( wrapper ) => wrapper.unmount() );
	} );

	// Focused, because the menu only opens on focus: assertions about what it lists would otherwise
	// pass against a list the user cannot see. Attached so that focus lands at all.
	async function mountInput( targetSchemas: SchemaReference[] ): Promise<TargetSchemasInputWrapper> {
		const wrapper: TargetSchemasInputWrapper = mount( TargetSchemasInput, {
			props: {
				modelValue: targetSchemas,
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

	it( 'shows each target schema as a chip', async () => {
		const wrapper = await mountInput( [ 'Person', 'Group' ] );

		expect( chipTexts( wrapper ) ).toEqual( [ 'Person', 'Group' ] );
	} );

	it( 'shows a target schema from another Source apart from the field, naming its source', async () => {
		const wrapper = await mountInput( [ { source: 'otherwiki', name: 'Person' }, 'Group' ] );

		expect( chipTexts( wrapper ) ).toEqual( [ 'Group' ] );
		expect( otherSourceChipTexts( wrapper ) ).toEqual( [ 'otherwiki:Person' ] );
	} );

	it( 'lists every schema and checks the targets', async () => {
		const wrapper = await mountInput( [ 'Office' ] );

		expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City' ] );
		expect( checkedSchemas( wrapper ) ).toEqual( [ 'Office' ] );
	} );

	it( 'lists only the schemas whose name contains the typed text', async () => {
		const wrapper = await mountInput( [] );

		await field( wrapper ).setValue( 'ity' );
		await flushPromises();

		expect( listedSchemas( wrapper ) ).toEqual( [ 'City' ] );
	} );

	it( 'adds a picked schema after the target schemas', async () => {
		const wrapper = await mountInput( [ 'Office' ] );

		await pickSchema( wrapper, 'City' );

		expect( emittedTargetSchemas( wrapper ) ).toEqual( [ [ 'Office', 'City' ] ] );
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

} );
