import { ref, shallowRef, type Ref, type ShallowRef } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { isSubjectFirst } from '@/wikiMode.ts';
import type { ModelBundle } from '@/application/modelBundle/ModelBundle.ts';
import type { InstallRecord } from '@/application/modelBundle/InstallRecord.ts';
import { CurrentStateReader } from '@/application/modelBundle/CurrentStateReader.ts';
import { planImport, type ImportPlan } from '@/application/modelBundle/importPlan.ts';
import { runImport, type ImportOutcome } from '@/application/modelBundle/importRun.ts';

interface BundleImport {
	plan: ShallowRef<ImportPlan | null>;
	chosen: Ref<Set<string>>;
	planning: Ref<boolean>;
	running: Ref<boolean>;
	progress: Ref<{ done: number; total: number }>;
	outcome: ShallowRef<ImportOutcome | null>;
	error: Ref<string | null>;
	/** Compares the file with the wiki; again for another bundle name, without reading the wiki again. */
	prepare: ( bundle: ModelBundle, bundleName: string | null, records: readonly InstallRecord[] ) => Promise<void>;
	/** Writes the chosen items. Source is where the bundle was read from: its URL, or FILE_SOURCE. */
	run: ( source: string ) => Promise<void>;
}

export function useBundleImport(): BundleImport {
	const repository = NeoWikiServices.getDataModelRepository();
	const recordRepository = NeoWikiServices.getInstallRecordRepository();
	const reader = new CurrentStateReader( repository );

	const plan = shallowRef<ImportPlan | null>( null );
	const chosen = ref( new Set<string>() );
	const planning = ref( false );
	const running = ref( false );
	const progress = ref( { done: 0, total: 0 } );
	const outcome = shallowRef<ImportOutcome | null>( null );
	const error = ref<string | null>( null );
	// Numbers the comparisons asked for, so one answered after a later one cannot replace its newer plan.
	let preparation = 0;

	async function prepare( bundle: ModelBundle, bundleName: string | null, records: readonly InstallRecord[] ): Promise<void> {
		const sequence = ++preparation;
		planning.value = true;
		error.value = null;

		try {
			const prepared = await planImport( bundle, bundleName, {
				reader,
				repository,
				records,
				subjectFirst: isSubjectFirst(),
			} );

			if ( sequence === preparation ) {
				plan.value = prepared;
				chosen.value = new Set( prepared.items.filter( ( item ) => item.selectedByDefault ).map( ( item ) => item.id ) );
			}
		} catch ( planError ) {
			if ( sequence === preparation ) {
				plan.value = null;
				error.value = planError instanceof Error ? planError.message : String( planError );
			}
		} finally {
			if ( sequence === preparation ) {
				planning.value = false;
			}
		}
	}

	async function run( source: string ): Promise<void> {
		const preparedPlan = plan.value;

		if ( preparedPlan === null ) {
			return;
		}

		running.value = true;

		try {
			outcome.value = await runImport(
				preparedPlan,
				chosen.value,
				{ repository, recordRepository, comment: summaryOf( preparedPlan ), source, subjectFirst: isSubjectFirst() },
				( done, total ) => {
					progress.value = { done, total };
				},
			);
		} catch ( runError ) {
			error.value = runError instanceof Error ? runError.message : String( runError );
		} finally {
			running.value = false;
		}
	}

	return { plan, chosen, planning, running, progress, outcome, error, prepare, run };
}

function summaryOf( plan: ImportPlan ): string {
	if ( plan.bundleName === null ) {
		return mw.msg( 'neowiki-bundle-import-summary' );
	}

	return mw.msg( plan.record === null ? 'neowiki-bundle-install-summary' : 'neowiki-bundle-update-summary', plan.bundleName );
}
