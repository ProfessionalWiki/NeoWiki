import { ref, shallowRef, type Ref, type ShallowRef } from 'vue';
import { ModelBundleServices } from '../ModelBundleServices.ts';
import { isSubjectFirst } from 'ext.neowiki';
import type { BundleToInstall } from '../application/ModelBundle.ts';
import type { InstallRecord } from '../application/InstallRecord.ts';
import { CurrentStateReader } from '../application/CurrentStateReader.ts';
import { planImports, type ImportPlan } from '../application/importPlan.ts';
import { runImports, type ImportOutcome } from '../application/importRun.ts';

interface BundleImport {
	/** One for each bundle, in the order they are installed. */
	plans: ShallowRef<ImportPlan[] | null>;
	/** What is ticked, for each plan. */
	chosen: Ref<Set<string>[]>;
	planning: Ref<boolean>;
	running: Ref<boolean>;
	progress: Ref<{ done: number; total: number }>;
	outcomes: ShallowRef<ImportOutcome[] | null>;
	error: Ref<string | null>;
	/**
	 * Compares the files with the wiki, putting each bundle after those before it in the list; again for other
	 * names, without reading the wiki again.
	 */
	prepare: ( bundles: readonly BundleToInstall[], records: readonly InstallRecord[] ) => Promise<void>;
	run: () => Promise<void>;
}

export function useBundleImport(): BundleImport {
	const repository = ModelBundleServices.getDataModelRepository();
	const recordRepository = ModelBundleServices.getInstallRecordRepository();
	const reader = new CurrentStateReader( repository );

	const plans = shallowRef<ImportPlan[] | null>( null );
	const chosen = ref<Set<string>[]>( [] );
	const planning = ref( false );
	const running = ref( false );
	const progress = ref( { done: 0, total: 0 } );
	const outcomes = shallowRef<ImportOutcome[] | null>( null );
	const error = ref<string | null>( null );
	let sources: string[] = [];
	// Numbers the comparisons asked for, so one answered after a later one cannot replace its newer plans.
	let preparation = 0;

	async function prepare( bundles: readonly BundleToInstall[], records: readonly InstallRecord[] ): Promise<void> {
		const sequence = ++preparation;
		planning.value = true;
		error.value = null;

		try {
			const prepared = await planImports( bundles, { reader, repository, records, subjectFirst: isSubjectFirst() } );

			if ( sequence === preparation ) {
				plans.value = prepared;
				chosen.value = prepared.map( ( plan ) => new Set( plan.items.filter( ( item ) => item.selectedByDefault ).map( ( item ) => item.id ) ) );
				sources = bundles.map( ( bundle ) => bundle.source );
			}
		} catch ( planError ) {
			if ( sequence === preparation ) {
				plans.value = null;
				error.value = planError instanceof Error ? planError.message : String( planError );
			}
		} finally {
			if ( sequence === preparation ) {
				planning.value = false;
			}
		}
	}

	async function run(): Promise<void> {
		const preparedPlans = plans.value;

		if ( preparedPlans === null ) {
			return;
		}

		running.value = true;

		try {
			outcomes.value = await runImports(
				preparedPlans.map( ( plan, index ) => ( {
					plan,
					chosen: chosen.value[ index ],
					context: { repository, recordRepository, comment: summaryOf( plan ), source: sources[ index ], subjectFirst: isSubjectFirst() },
				} ) ),
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

	return { plans, chosen, planning, running, progress, outcomes, error, prepare, run };
}

function summaryOf( plan: ImportPlan ): string {
	return mw.msg( plan.record === null ? 'neowiki-bundle-install-summary' : 'neowiki-bundle-update-summary', plan.bundleName );
}
