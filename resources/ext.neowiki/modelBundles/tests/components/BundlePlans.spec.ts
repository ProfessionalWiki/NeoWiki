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

function mountPlans(
	plans: ImportPlan[],
	outcomes: ImportOutcome[] | null = null,
	requiredBy: ReadonlyMap<string, string[]> = new Map(),
): VueWrapper {
	return mount( BundlePlans, {
		props: {
			plans,
			outcomes,
			requiredBy,
			modelValue: plans.map( ( plan ) => new Set( plan.items.filter( ( item ) => item.selectedByDefault ).map( ( item ) => item.id ) ) ),
			disabled: false,
		},
		global: { mocks: { $i18n: createI18nMock() }, stubs: { CdxIcon: true } },
	} );
}

/**
 * The names of the items the preview shows.
 */
function shownItems( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-bundle-preview__name, .ext-neowiki-bundle-preview__item .cdx-label__label__text' )
		.map( ( name ) => name.text() );
}

function failedItems( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-import-results__failures li' ).map( ( failure ) => failure.element.firstChild?.textContent?.trim() ?? '' );
}

function outcomeOf( plan: ImportPlan, ...results: ImportOutcome[ 'items' ][ number ][ 'result' ][] ): ImportOutcome {
	return {
		items: results.map( ( result, index ) => ( {
			item: plan.items[ index ],
			result,
			reason: result === 'failed' ? 'The page is protected' : null,
		} ) ),
		record: { saved: true },
	};
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

	it( 'marks a bundle that another pulled in as required by it', async () => {
		const plans = [
			await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ), 'Core' ),
			await planOn( wiki, bundleOf( [ [ ID_2, MALE ] ] ), 'Museum' ),
		];

		const wrapper = mountPlans( plans, null, new Map( [ [ 'Core', [ 'Museum', 'EDM' ] ] ] ) );

		expect( wrapper.findAll( '.ext-neowiki-bundle-plans__bundle' ).map( ( bundle ) => bundle.find( '.ext-neowiki-bundle-plans__required' ).exists() ?
			bundle.find( '.ext-neowiki-bundle-plans__required' ).text() :
			null ) ).toEqual( [ 'neowiki-bundle-required-byMuseum and EDM', null ] );
	} );

	it( 'leaves out of each row the status the summary line gives every item', async () => {
		const wrapper = mountPlans( [ await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) ) ] );

		await toggle( wrapper );

		expect( wrapper.find( '.ext-neowiki-bundle-preview' ).text() ).not.toContain( 'neowiki-bundle-status-new' );
	} );

	it( 'shows the status of each row where the summary line counts the items apart', async () => {
		wiki.withSubject( ID_2, subjectEntry( { label: 'male (edited)' } ) );
		const wrapper = mountPlans( [ await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) ) ] );

		await toggle( wrapper );

		expect( wrapper.find( '.ext-neowiki-bundle-preview' ).text() ).toContain( 'neowiki-bundle-status-new' );
	} );

	it( 'tells, once installed, what each bundle came to on one line', async () => {
		const plan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) );

		const wrapper = mountPlans( [ plan ], [ outcomeOf( plan, 'created', 'created' ) ] );

		expect( [ wrapper.find( '.ext-neowiki-bundle-plans__summary' ).exists(), wrapper.find( '.ext-neowiki-import-results' ).text() ] )
			.toEqual( [ false, 'neowiki-bundle-installedVocabularies: neowiki-bundle-count-created2' ] );
	} );

	it( 'lists the items that failed under that line, and no others', async () => {
		const plan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ] ] ) );

		const wrapper = mountPlans( [ plan ], [ outcomeOf( plan, 'failed', 'created' ) ] );

		expect( failedItems( wrapper ) ).toEqual( [ 'female' ] );
	} );

} );
