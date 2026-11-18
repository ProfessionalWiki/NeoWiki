import { beforeEach, describe, expect, it } from 'vitest';
import { needsDecision, planSummary, resultLine } from '../../src/presentation/bundleItems';
import type { InstallItem, InstallPlan } from '../../src/application/installPlan';
import type { InstallOutcome, ItemResult } from '../../src/application/installRun';
import { setupMwMock } from '../../../tests/VueTestHelpers';
import { InMemoryDataModel } from '../application/InMemoryDataModel';
import { bundleOf, installed, planOn, subjectEntry, VOCABULARIES } from '../application/builders';
import { newSchema } from '@/TestHelpers';

const ID_1 = 's1demo1aaaaaaa1';
const ID_2 = 's1demo1aaaaaaa2';
const ID_3 = 's1demo1aaaaaaa3';
const FEMALE = subjectEntry( { label: 'female' } );
const MALE = subjectEntry( { label: 'male' } );
const OTHER = subjectEntry( { label: 'other' } );

let wiki: InMemoryDataModel;

function plan( version = VOCABULARIES.version ): Promise<InstallPlan> {
	return planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ], [], { metadata: { ...VOCABULARIES, version } } ) );
}

function outcome( installPlan: InstallPlan, ...results: ItemResult[] ): InstallOutcome {
	return {
		items: results.map( ( result, index ) => ( {
			item: installPlan.items[ index ],
			result,
			reason: result === 'failed' ? 'The page is protected' : null,
		} ) ),
		record: { saved: true },
	};
}

describe( 'resultLine', () => {

	beforeEach( () => {
		setupMwMock( { messages: { 'colon-separator': ': ', 'comma-separator': ', ' } } );
		wiki = new InMemoryDataModel();
	} );

	it( 'says that the bundle was installed, and how many items it wrote', async () => {
		const installPlan = await plan();

		expect( resultLine( installPlan, outcome( installPlan, 'created' ) ) ).toEqual( {
			type: 'success',
			text: 'neowiki-bundle-installedVocabularies: neowiki-bundle-count-created1',
			note: null,
		} );
	} );

	it( 'counts the items created, replaced and removed apart', async () => {
		const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, MALE ], [ ID_3, OTHER ] ] ) );

		expect( resultLine( installPlan, outcome( installPlan, 'removed', 'created', 'replaced' ) ).text ).toBe(
			'neowiki-bundle-installedVocabularies: neowiki-bundle-count-created1, neowiki-bundle-count-replaced1, neowiki-bundle-count-removed1',
		);
	} );

	it( 'says when it wrote nothing', async () => {
		const installPlan = await plan();

		expect( resultLine( installPlan, outcome( installPlan ) ).text ).toBe( 'neowiki-bundle-installedVocabularies: neowiki-bundle-count-nothing' );
	} );

	it( 'says which version the bundle was updated to', async () => {
		wiki.records = [ installed( [ [ ID_1, FEMALE ] ] ) ];
		const installPlan = await plan();

		expect( resultLine( installPlan, outcome( installPlan ) ).text ).toBe( 'neowiki-bundle-updatedVocabularies2: neowiki-bundle-count-nothing' );
	} );

	it( 'says that the bundle was updated when the file gives no version', async () => {
		wiki.records = [ installed( [ [ ID_1, FEMALE ] ] ) ];
		const installPlan = await plan( '' );

		expect( resultLine( installPlan, outcome( installPlan ) ).text ).toBe( 'neowiki-bundle-updated-unversionedVocabularies: neowiki-bundle-count-nothing' );
	} );

	it( 'warns, saying how many items were not written, when a write failed', async () => {
		const installPlan = await plan();

		expect( resultLine( installPlan, outcome( installPlan, 'failed' ) ) ).toEqual( {
			type: 'warning',
			text: 'neowiki-bundle-installedVocabularies: neowiki-bundle-count-nothing',
			note: 'neowiki-bundle-not-written1',
		} );
	} );

	it( 'says instead that the bundle could not be recorded as installed', async () => {
		const installPlan = await plan();

		expect( resultLine( installPlan, { ...outcome( installPlan, 'created' ), record: { saved: false, reason: 'Edit conflict' } } ) ).toEqual( {
			type: 'error',
			text: 'neowiki-bundle-record-failedVocabulariesEdit conflict',
			note: null,
		} );
	} );

} );

describe( 'needsDecision', () => {

	function itemWith( status: InstallItem[ 'status' ], action: InstallItem[ 'action' ] ): InstallItem {
		return { status, action } as InstallItem;
	}

	it.each( [
		[ 'differs', 'replace' ],
		[ 'changed-both', 'replace' ],
		[ 'declined', 'create' ],
		[ 'deleted-here', 'create' ],
		[ 'removed-upstream', 'remove' ],
		[ 'removed-upstream', null ],
		[ 'conflict', null ],
	] as const )( 'takes %s, to %s, for a decision', ( status, action ) => {
		expect( needsDecision( itemWith( status, action ) ) ).toBe( true );
	} );

	it.each( [
		[ 'new', 'create' ],
		[ 'changed-upstream', 'replace' ],
		[ 'unchanged', null ],
		[ 'changed-here', null ],
		[ 'deleted-here', null ],
	] as const )( 'leaves %s, to %s, to installing', ( status, action ) => {
		expect( needsDecision( itemWith( status, action ) ) ).toBe( false );
	} );

} );

describe( 'planSummary', () => {

	const ID_2 = 's1demo1aaaaaaa2';
	const ID_3 = 's1demo1aaaaaaa3';

	beforeEach( () => {
		setupMwMock( { messages: { 'comma-separator': ', ' } } );
		wiki = new InMemoryDataModel();
	} );

	it( 'sums up a first install as its items by type, all new', async () => {
		const installPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ], [ ID_2, FEMALE ] ], [ newSchema( { title: 'Gender Types' } ) ] ) );

		expect( planSummary( installPlan ) ).toBe( 'neowiki-bundle-summary-schemas1, neowiki-bundle-summary-subjects2, neowiki-bundle-summary-all-new' );
	} );

	it( 'counts what an update creates, updates, leaves to a decision and leaves as it is', async () => {
		wiki.records = [ installed( [ [ ID_1, FEMALE ], [ ID_2, FEMALE ], [ ID_3, FEMALE ] ] ) ];
		wiki.withSubject( ID_1, FEMALE ).withSubject( ID_2, subjectEntry( { label: 'female (edited)' } ) ).withSubject( ID_3, FEMALE );

		const installPlan = await planOn( wiki, bundleOf( [
			[ ID_1, subjectEntry( { label: 'woman' } ) ],
			[ ID_2, subjectEntry( { label: 'woman' } ) ],
			[ ID_3, FEMALE ],
			[ 's1demo1aaaaaaa4', FEMALE ],
		] ) );

		expect( planSummary( installPlan ) ).toBe( 'neowiki-bundle-summary-subjects4, neowiki-bundle-summary-new1, ' +
			'neowiki-bundle-summary-update1, neowiki-bundle-summary-decide1, neowiki-bundle-summary-unchanged1' );
	} );

	it( 'sums up a bundle the wiki has as it is as all unchanged', async () => {
		wiki.withSubject( ID_1, FEMALE );

		expect( planSummary( await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ) ) ) )
			.toBe( 'neowiki-bundle-summary-subjects1, neowiki-bundle-summary-all-unchanged' );
	} );

} );
