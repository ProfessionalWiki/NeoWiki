import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxIcon, CdxMenuButton } from '@wikimedia/codex';
import { cdxIconAlert, cdxIconError } from '@wikimedia/codex-icons';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import type { Severity } from '@/domain/Severity';
import { createTestWrapper, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'SeverityInput', () => {
	function mockMw( validationEnforced: boolean ): void {
		setupMwMock( {
			messages: {
				'neowiki-severity-input-label': ( constraint: string, severity: string ) => 'Severity of ' + constraint + ': ' + severity,
				'neowiki-severity-warning': 'Warning',
				'neowiki-severity-error': 'Error',
				'neowiki-severity-warning-description': 'warning meaning',
				'neowiki-severity-error-description-enforced': 'enforced error meaning',
				'neowiki-severity-error-description-not-enforced': 'unenforced error meaning',
			},
			config: { wgNeoWikiEnforceValidation: validationEnforced },
		} );
	}

	beforeEach( () => {
		mockMw( false );
	} );

	function newWrapper( modelValue?: Severity ): VueWrapper {
		return createTestWrapper( SeverityInput, { modelValue, constraint: 'Minimum' } );
	}

	function triggerIcon( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof CdxIcon>> {
		return wrapper.findAllComponents( CdxIcon ).filter( ( icon ) => icon.classes( 'ext-neowiki-severity-input__icon' ) )[ 0 ];
	}

	function menuButton( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof CdxMenuButton>> {
		return wrapper.findComponent( CdxMenuButton );
	}

	it( 'shows the warning icon when no severity is set, since warning is the default', () => {
		const wrapper = newWrapper();

		expect( triggerIcon( wrapper ).props( 'icon' ) ).toBe( cdxIconAlert );
		expect( menuButton( wrapper ).props( 'selected' ) ).toBe( 'warning' );
	} );

	it( 'shows the error icon when the severity is error', () => {
		const wrapper = newWrapper( 'error' );

		expect( triggerIcon( wrapper ).props( 'icon' ) ).toBe( cdxIconError );
		expect( menuButton( wrapper ).props( 'selected' ) ).toBe( 'error' );
	} );

	it( 'names the trigger after its Constraint and the current severity, for assistive technology and the tooltip', () => {
		const wrapper = newWrapper( 'error' );

		const trigger = wrapper.find( 'button' );
		expect( trigger.attributes( 'aria-label' ) ).toBe( 'Severity of Minimum: Error' );
		expect( trigger.attributes( 'title' ) ).toBe( 'Severity of Minimum: Error' );
	} );

	it( 'offers warning and error, each with its icon, name and meaning', () => {
		const wrapper = newWrapper();

		expect( menuButton( wrapper ).props( 'menuItems' ) ).toEqual( [
			{ value: 'warning', label: 'Warning', description: 'warning meaning', icon: cdxIconAlert },
			{ value: 'error', label: 'Error', description: 'unenforced error meaning', icon: cdxIconError },
		] );
	} );

	it( 'describes error as blocking when the wiki enforces validation', () => {
		mockMw( true );
		const wrapper = newWrapper();

		expect( menuButton( wrapper ).props( 'menuItems' )[ 1 ].description ).toBe( 'enforced error meaning' );
	} );

	it( 'emits the picked severity', async () => {
		const wrapper = newWrapper();

		await menuButton( wrapper ).vm.$emit( 'update:selected', 'error' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ 'error' ] ] );
	} );

	it( 'does not emit when the current severity is picked again', async () => {
		const wrapper = newWrapper( 'error' );

		await menuButton( wrapper ).vm.$emit( 'update:selected', 'error' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

	it( 'does not emit when the menu reports no selection', async () => {
		const wrapper = newWrapper();

		await menuButton( wrapper ).vm.$emit( 'update:selected', null );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );
} );
