import { enableAutoUnmount, mount, VueWrapper, DOMWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconCheck, cdxIconTrash } from '@wikimedia/codex-icons';
import { createI18nMock, setupMwMock, stubUpdateTooltipAccessKeys } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

enableAutoUnmount( afterEach );

describe( 'SummaryAction', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg' ], messages: { 'accesskey-save': 's' } } );
		stubUpdateTooltipAccessKeys();
	} );

	function mountComponent( props: Partial<InstanceType<typeof SummaryAction>[ '$props' ]> = {} ): VueWrapper {
		return mount( SummaryAction, {
			props: {
				helpText: '',
				saveButtonLabel: 'Save',
				saveDisabled: false,
				...props,
			},
			global: {
				mocks: { $i18n },
			},
		} );
	}

	it( 'disables save button when saveDisabled is true', () => {
		const wrapper = mountComponent( { saveDisabled: true } );

		const button = wrapper.findComponent( CdxButton );
		expect( button.attributes( 'disabled' ) ).toBe( '' );
	} );

	it( 'enables save button when saveDisabled is false', () => {
		const wrapper = mountComponent( { saveDisabled: false } );

		const button = wrapper.findComponent( CdxButton );
		expect( button.attributes( 'disabled' ) ).toBeUndefined();
	} );

	it( 'emits save with summary when button is clicked', async () => {
		const wrapper = mountComponent( { saveDisabled: false } );

		await wrapper.findComponent( CdxButton ).trigger( 'click' );

		expect( wrapper.emitted( 'save' ) ).toEqual( [ [ '' ] ] );
	} );

	it( 'defaults the save button action to progressive', () => {
		const wrapper = mountComponent();

		const button = wrapper.findComponent( CdxButton );
		expect( button.props( 'action' ) ).toBe( 'progressive' );
	} );

	it( 'forwards the saveButtonAction prop to the save button', () => {
		const wrapper = mountComponent( { saveButtonAction: 'destructive' } );

		const button = wrapper.findComponent( CdxButton );
		expect( button.props( 'action' ) ).toBe( 'destructive' );
	} );

	it( 'defaults the save button icon to cdxIconCheck', () => {
		const wrapper = mountComponent();

		const icon = wrapper.findComponent( CdxIcon );
		expect( icon.props( 'icon' ) ).toBe( cdxIconCheck );
	} );

	it( 'forwards the saveButtonIcon prop to the save button', () => {
		const wrapper = mountComponent( { saveButtonIcon: cdxIconTrash } );

		const icon = wrapper.findComponent( CdxIcon );
		expect( icon.props( 'icon' ) ).toBe( cdxIconTrash );
	} );

	it( 'defaults the field label to the edit-summary message', () => {
		const wrapper = mountComponent();

		expect( wrapper.text() ).toContain( 'neowiki-edit-summary-label' );
	} );

	it( 'uses the provided label prop over the default', () => {
		const wrapper = mountComponent( { label: 'Reason' } );

		expect( wrapper.text() ).toContain( 'Reason' );
		expect( wrapper.text() ).not.toContain( 'neowiki-edit-summary-label' );
	} );

	it( 'defaults the field placeholder to the edit-summary message', () => {
		const wrapper = mountComponent();

		expect( wrapper.find( 'textarea' ).attributes( 'placeholder' ) )
			.toBe( 'neowiki-edit-summary-placeholder' );
	} );

	it( 'uses the provided placeholder prop over the default', () => {
		const wrapper = mountComponent( { placeholder: 'Why are you deleting this?' } );

		expect( wrapper.find( 'textarea' ).attributes( 'placeholder' ) )
			.toBe( 'Why are you deleting this?' );
	} );

	describe( 'Footer text', () => {
		function actionRow( wrapper: VueWrapper ): Omit<DOMWrapper<Element>, 'exists'> {
			return wrapper.get( '.ext-neowiki-summary-action__actions' );
		}

		// Without a note there is no extra node in the row, and no class the stylesheet keys
		// the inline layout off.
		it( 'leaves the action row untouched when no footer text is given', () => {
			const wrapper = mountComponent();

			expect( actionRow( wrapper ).classes() ).toEqual( [ 'ext-neowiki-summary-action__actions' ] );
			expect( actionRow( wrapper ).element.children ).toHaveLength( 1 );
			expect( wrapper.find( '.ext-neowiki-summary-action__footer-text' ).exists() ).toBe( false );
		} );

		it( 'renders the footer text in the action row, ahead of the button', () => {
			const wrapper = mountComponent( { footerText: 'Saving updates 2 subjects' } );

			const row = actionRow( wrapper );
			expect( row.classes() ).toContain( 'ext-neowiki-summary-action__actions--with-text' );
			expect( row.element.children ).toHaveLength( 2 );
			expect( row.element.children[ 0 ].textContent?.trim() ).toBe( 'Saving updates 2 subjects' );
		} );

		// The block above the row keeps its own slot: the two are shown together.
		it( 'renders the footer text alongside the help text rather than in its place', () => {
			const wrapper = mountComponent( { helpText: 'Help', footerText: 'Footer' } );

			expect( wrapper.get( '.ext-neowiki-summary-action__help-text' ).text() ).toBe( 'Help' );
			expect( wrapper.get( '.ext-neowiki-summary-action__footer-text' ).text() ).toBe( 'Footer' );
		} );
	} );

	describe( 'Save access key', () => {
		function saveButton( wrapper: VueWrapper ): VueWrapper {
			return wrapper.findComponent( CdxButton );
		}

		async function mountSettled( props: Partial<InstanceType<typeof SummaryAction>[ '$props' ]> = {} ): Promise<VueWrapper> {
			const wrapper = mountComponent( props );
			await nextTick();
			return wrapper;
		}

		it( 'gives the save button the wiki\'s save key, named in its tooltip', async () => {
			const wrapper = await mountSettled( { saveButtonLabel: 'Save changes' } );

			expect( saveButton( wrapper ).attributes( 'accesskey' ) ).toBe( 's' );
			expect( saveButton( wrapper ).attributes( 'title' ) ).toBe( 'Save changes [alt-shift-s]' );
		} );

		it( 'leaves a destructive button without the key', async () => {
			const wrapper = await mountSettled( { saveButtonAction: 'destructive' } );

			expect( saveButton( wrapper ).attributes( 'accesskey' ) ).toBeUndefined();
		} );

		it( 'leaves the button without a key when the wiki switches the save key off', async () => {
			setupMwMock( { functions: [ 'message', 'msg' ], messages: { 'accesskey-save': '-' } } );

			const wrapper = await mountSettled();

			expect( saveButton( wrapper ).attributes( 'accesskey' ) ).toBeUndefined();
		} );

		it( 'gives the key up as soon as its dialog closes', async () => {
			const wrapper = await mountSettled();
			const button = saveButton( wrapper ).element;

			wrapper.unmount();

			expect( button.hasAttribute( 'accesskey' ) ).toBe( false );
		} );

		describe( 'with dialogs stacked', () => {
			// The footers of the open dialogs, from the bottom of the stack to the top.
			const OpenDialogs = {
				components: { SummaryAction },
				template: '<SummaryAction v-for="label in labels" :key="label" help-text="" ' +
					':save-button-label="label" :save-disabled="false" />',
				props: [ 'labels' ],
			};

			async function mountOpenDialogs( labels: string[] ): Promise<VueWrapper> {
				const wrapper = mount( OpenDialogs, { props: { labels }, global: { mocks: { $i18n } } } );
				await nextTick();
				return wrapper;
			}

			function button( wrapper: VueWrapper, label: string ): DOMWrapper<Element> {
				return wrapper.findAll( 'button' ).find( ( candidate ) => candidate.text() === label )!;
			}

			it( 'gives the key only to the dialog in front', async () => {
				const wrapper = await mountOpenDialogs( [ 'Save changes', 'Save schema' ] );

				expect( button( wrapper, 'Save changes' ).attributes( 'accesskey' ) ).toBeUndefined();
				expect( button( wrapper, 'Save schema' ).attributes( 'accesskey' ) ).toBe( 's' );
			} );

			it( 'hands the key, named in the tooltip, to the dialog next in line once the one in front closes', async () => {
				const wrapper = await mountOpenDialogs( [ 'Save changes', 'Save schema', 'Create mapping' ] );

				await wrapper.setProps( { labels: [ 'Save changes', 'Save schema' ] } );

				expect( button( wrapper, 'Save schema' ).attributes( 'accesskey' ) ).toBe( 's' );
				expect( button( wrapper, 'Save schema' ).attributes( 'title' ) ).toBe( 'Save schema [alt-shift-s]' );
			} );

			describe( 'under a CloseConfirmationDialog', () => {
				// Hosts write the question after the footer of the dialog it asks about, and mount it closed.
				async function mountAskingDialog(): Promise<VueWrapper> {
					const wrapper = mount(
						{
							components: { CloseConfirmationDialog, SummaryAction },
							template: '<SummaryAction help-text="" save-button-label="Save changes" :save-disabled="false" />' +
								'<CloseConfirmationDialog :open="asking" />',
							props: [ 'asking' ],
						},
						{ props: { asking: false }, global: { mocks: { $i18n } } },
					);
					await nextTick();
					return wrapper;
				}

				it( 'gives the key up while the question is open', async () => {
					const wrapper = await mountAskingDialog();
					expect( button( wrapper, 'Save changes' ).attributes( 'accesskey' ) ).toBe( 's' );

					await wrapper.setProps( { asking: true } );

					expect( button( wrapper, 'Save changes' ).attributes( 'accesskey' ) ).toBeUndefined();
				} );

				it( 'gets the key back once the question is answered', async () => {
					const wrapper = await mountAskingDialog();
					await wrapper.setProps( { asking: true } );

					await wrapper.setProps( { asking: false } );

					expect( button( wrapper, 'Save changes' ).attributes( 'accesskey' ) ).toBe( 's' );
				} );
			} );
		} );
	} );
} );
