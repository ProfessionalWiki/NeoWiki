import { beforeEach, describe, expect, it } from 'vitest';
import { resultHeadline } from '@/presentation/bundleItems';
import type { ImportPlan } from '@/application/modelBundle/importPlan';
import type { ImportOutcome, ItemResult } from '@/application/modelBundle/importRun';
import { setupMwMock } from '../VueTestHelpers';
import { InMemoryDataModel } from '../application/modelBundle/InMemoryDataModel';
import { bundleOf, installed, planOn, subjectEntry, VOCABULARIES } from '../application/modelBundle/builders';

const ID_1 = 's1demo1aaaaaaa1';
const FEMALE = subjectEntry( { label: 'female' } );

let wiki: InMemoryDataModel;

function plan( version = VOCABULARIES.version ): Promise<ImportPlan> {
	return planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ], [], { metadata: { ...VOCABULARIES, version } } ) );
}

function outcome( importPlan: ImportPlan, result: ItemResult = 'created' ): ImportOutcome {
	return {
		items: importPlan.items.map( ( item ) => ( { item, result, reason: result === 'failed' ? 'The page is protected' : null } ) ),
		record: { saved: true },
	};
}

describe( 'resultHeadline', () => {

	beforeEach( () => {
		setupMwMock();
		wiki = new InMemoryDataModel();
	} );

	it( 'says that the bundle was installed', async () => {
		const importPlan = await plan();

		expect( resultHeadline( importPlan, outcome( importPlan ) ) ).toEqual( {
			type: 'success',
			text: 'neowiki-bundle-installedVocabularies',
			note: null,
		} );
	} );

	it( 'says which version the bundle was updated to', async () => {
		wiki.records = [ installed( [ [ ID_1, FEMALE ] ] ) ];
		const importPlan = await plan();

		expect( resultHeadline( importPlan, outcome( importPlan ) )?.text ).toBe( 'neowiki-bundle-updatedVocabularies2' );
	} );

	it( 'says that the bundle was updated when the file gives no version', async () => {
		wiki.records = [ installed( [ [ ID_1, FEMALE ] ] ) ];
		const importPlan = await plan( '' );

		expect( resultHeadline( importPlan, outcome( importPlan ) )?.text ).toBe( 'neowiki-bundle-updated-unversionedVocabularies' );
	} );

	it( 'warns, saying how many items were not written, when a write failed', async () => {
		const importPlan = await plan();

		expect( resultHeadline( importPlan, outcome( importPlan, 'failed' ) ) ).toEqual( {
			type: 'warning',
			text: 'neowiki-bundle-installedVocabularies',
			note: 'neowiki-bundle-not-written1',
		} );
	} );

	it( 'heads nothing when the record could not be saved, which is told apart', async () => {
		const importPlan = await plan();

		expect( resultHeadline( importPlan, { ...outcome( importPlan ), record: { saved: false, reason: 'Edit conflict' } } ) ).toBeNull();
	} );

	it( 'heads nothing for a plain import', async () => {
		const importPlan = await planOn( wiki, bundleOf( [ [ ID_1, FEMALE ] ] ), null );

		expect( resultHeadline( importPlan, { ...outcome( importPlan ), record: null } ) ).toBeNull();
	} );

} );
