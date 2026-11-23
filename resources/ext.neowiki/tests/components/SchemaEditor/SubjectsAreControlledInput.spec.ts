import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SubjectsAreControlledInput from '@/components/SchemaEditor/SubjectsAreControlledInput.vue';
import { accessibleName, createI18nMock } from '../../VueTestHelpers.ts';
import { Service } from '@/NeoWikiServices.ts';

describe( 'SubjectsAreControlledInput', () => {

	async function mountInput( canEditControlledSubjects: boolean, modelValue = false ): Promise<VueWrapper> {
		const wrapper = mount( SubjectsAreControlledInput, {
			props: { modelValue },
			global: {
				mocks: { $i18n: createI18nMock() },
				provide: {
					[ Service.SubjectPermissionHints ]: { canEditControlledSubjects: async () => canEditControlledSubjects },
				},
			},
		} );
		await flushPromises();

		return wrapper;
	}

	function checkbox( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return wrapper.find<HTMLInputElement>( 'input[type="checkbox"]' );
	}

	function explanationToggle( wrapper: VueWrapper ): DOMWrapper<HTMLButtonElement> {
		const toggle = wrapper.findAll<HTMLButtonElement>( 'button' )
			.find( ( button ) => accessibleName( button.element ) === 'neowiki-schema-editor-controlled-info-label' );

		if ( toggle === undefined ) {
			throw new Error( 'No button named after what the explanation explains' );
		}

		return toggle;
	}

	function explanationShown( wrapper: VueWrapper ): boolean {
		return wrapper.findAll( '*' )
			.some( ( element ) => element.text() === 'neowiki-schema-editor-controlled-info' && element.isVisible() );
	}

	it( 'lets a user with the right to edit Controlled Subjects declare them', async () => {
		const wrapper = await mountInput( true );

		await checkbox( wrapper ).setValue( true );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ true ] ] );
	} );

	it( 'shows a user without the right what the Schema declares, without letting them change it', async () => {
		const wrapper = await mountInput( false, true );

		expect( checkbox( wrapper ).element.checked ).toBe( true );
		expect( checkbox( wrapper ).element.disabled ).toBe( true );
	} );

	it( 'tells a user with the right what to choose when unsure', async () => {
		const wrapper = await mountInput( true );

		expect( wrapper.text() ).toContain( 'neowiki-schema-editor-controlled-help' );
		expect( wrapper.text() ).not.toContain( 'neowiki-schema-editor-controlled-denied' );
	} );

	it( 'tells a user without the right why they cannot change it', async () => {
		const wrapper = await mountInput( false );

		expect( wrapper.text() ).toContain( 'neowiki-schema-editor-controlled-denied' );
		expect( wrapper.text() ).not.toContain( 'neowiki-schema-editor-controlled-help' );
	} );

	it( 'names its checkbox by the label alone, also while the explanation shows', async () => {
		const wrapper = await mountInput( true );

		await explanationToggle( wrapper ).trigger( 'click' );

		expect( accessibleName( checkbox( wrapper ).element ) ).toBe( 'neowiki-schema-editor-controlled' );
	} );

	it( 'keeps its explanation out of sight until asked for it', async () => {
		const wrapper = await mountInput( true );

		expect( explanationShown( wrapper ) ).toBe( false );
		expect( explanationToggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'shows its explanation once asked for it, also to a user without the right', async () => {
		const wrapper = await mountInput( false );

		await explanationToggle( wrapper ).trigger( 'click' );

		expect( explanationShown( wrapper ) ).toBe( true );
		expect( explanationToggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
	} );

} );
