import { enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import BundlePlans from '../../src/components/BundlePlans.vue';
import type { ImportPlan } from '../../src/application/importPlan';
import type { ImportOutcome } from '../../src/application/importRun';
import { createI18nMock, setupMwMock } from '../../../tests/VueTestHelpers.ts';
import { InMemoryDataModel } from '../application/InMemoryDataModel';
import { bundleOf, planOn, subjectEntry } from '../application/builders';

enableAutoUnmount( afterEach );

const ID_1 = 's1demo1aaaaaaa1';
const ID_2 = 's1demo1aaaaaaa2';
const FEMALE = subjectEntry( { label: 'female' } );
const MALE = subjectEntry( { label: 'male' } );

let wiki: InMemoryDataModel;

function mountPlans( plans: ImportPlan[], outcomes: ImportOutcome[] | null = null ): VueWrapper {
	return mount( BundlePlans, {
		props: {
			plans,
			outcomes,
			modelValue: plans.map( ( plan ) => new Set( plan.items.filter( ( item ) => item.selectedByDefault ).map( ( item ) => item.id ) ) ),
			disabled: false,
		},
		global: { mocks: { $i18n: createI18nMock() }, stubs: { CdxIcon: true } },
	} );
}

/**
 * The names of the items the preview or the results show.
 */
function shownItems( wrapper: VueWrapper ): string[] {
	return [
		...wrapper.findAll( '.ext-neowiki-bundle-preview__name, .ext-neowiki-bundle-preview__item .cdx-label__label__text' )
			.map( ( name ) => name.text() ),
		...wrapper.findAll( '.ext-neowiki-import-results__list li' )
			.map( ( result ) => result.element.firstChild?.textContent?.trim() ?? '' ),
	];
}

async function toggle( wrapper: VueWrapper ): Promise<void> {
	await wrapper.find( '.ext-neowiki-bundle-plans__toggle' ).trigger( 'click' );
}

describe( 'BundlePlans', () => {

	beforeEach( () => {
		setupMwMock( { messages: { 'colon-separator': ': ', 'comma-separator': ', ' } } );
		wiki = new InMemoryDataModel();
	} );

	it( 'sums up each bundle on one line, its items out of view', async () => {
		const wrapper = mountPlans( [ await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) ) ] );

		expect( wrapper.find( '.ext-neowiki-bundle-plans__summary' ).text() )
			.toBe( 'Vocabularies neowiki-bundle-version2: neowiki-bundle-summary-subjects2, neowiki-bundle-summary-all-new' );
		expect( shownItems( wrapper ) ).toEqual( [] );
	} );

	it( 'keeps in view what needs a decision', async () => {
		wiki.withSubject( ID_2, subjectEntry( { label: 'male (edited)' } ) );

		const wrapper = mountPlans( [ await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) ) ] );

		expect( shownItems( wrapper ) ).toEqual( [ 'male' ] );
	} );

	it( 'shows every item once expanded, and hides them again', async () => {
		const wrapper = mountPlans( [ await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) ) ] );

		await toggle( wrapper );
		const expanded = shownItems( wrapper );
		await toggle( wrapper );

		expect( [ expanded, shownItems( wrapper ) ] ).toEqual( [ [ 'female', 'male' ], [] ] );
	} );

	it( 'tells assistive technology whether a bundle\'s items are shown', async () => {
		const wrapper = mountPlans( [ await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ) ] );

		await toggle( wrapper );

		expect( wrapper.find( '.ext-neowiki-bundle-plans__toggle' ).attributes( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'keeps failures in view after the install, and shows the rest once expanded', async () => {
		const plan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) );
		const outcome: ImportOutcome = {
			items: [
				{ item: plan.items[ 0 ], result: 'failed', reason: 'The page is protected' },
				{ item: plan.items[ 1 ], result: 'created', reason: null },
			],
			record: { saved: true },
		};
		const wrapper = mountPlans( [ plan ], [ outcome ] );

		const collapsed = shownItems( wrapper );
		await toggle( wrapper );

		expect( [ collapsed, shownItems( wrapper ) ] ).toEqual( [ [ 'female' ], [ 'female', 'male' ] ] );
	} );

} );
