import { enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import SetupSteps from '../../src/components/SetupSteps.vue';
import { createI18nMock } from '../../../tests/VueTestHelpers.ts';

enableAutoUnmount( afterEach );

const STEPS = [
	{ id: 'settings', label: 'How this wiki works' },
	{ id: 'model', label: 'Model' },
	{ id: 'destinations', label: 'Destinations' },
	{ id: 'review', label: 'Review' },
];

function mountSteps( current: string, disabled: boolean ): VueWrapper {
	return mount( SetupSteps, {
		props: { steps: STEPS, current, disabled },
		global: { mocks: { $i18n: createI18nMock() } },
	} );
}

describe( 'SetupSteps', () => {

	it( 'marks the current step for assistive technology', () => {
		const wrapper = mountSteps( 'destinations', false );

		expect( wrapper.find( '[aria-current="step"]' ).text() ).toContain( 'Destinations' );
	} );

	it( 'takes the reader back to a completed step', async () => {
		const wrapper = mountSteps( 'destinations', false );

		await wrapper.findAll( 'button' )[ 1 ].trigger( 'click' );

		expect( wrapper.emitted( 'go' ) ).toEqual( [ [ 'model' ] ] );
	} );

	it( 'offers the completed steps, and no step ahead, as buttons', () => {
		const wrapper = mountSteps( 'destinations', false );

		expect( wrapper.findAll( 'button' ).map( ( button ) => button.text() ) ).toEqual( [
			expect.stringContaining( 'How this wiki works' ),
			expect.stringContaining( 'Model' ),
		] );
	} );

	it( 'offers no step back while disabled', () => {
		const wrapper = mountSteps( 'review', true );

		expect( wrapper.findAll( 'button' ).map( ( button ) => button.element.disabled ) ).toEqual( [ true, true, true ] );
	} );

} );
