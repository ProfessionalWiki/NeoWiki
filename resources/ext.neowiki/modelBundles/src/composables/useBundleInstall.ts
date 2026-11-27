import { ref, shallowRef, type Ref, type ShallowRef } from 'vue';
import { ModelBundleServices } from '../ModelBundleServices.ts';
import type { BundleToInstall } from '../application/ModelBundle.ts';
import type { InstallRecord } from '../application/InstallRecord.ts';
import { CurrentStateReader } from '../application/CurrentStateReader.ts';
import { defaultChoice, planInstalls, type InstallPlan } from '../application/installPlan.ts';
import { runInstalls, type InstallOutcome } from '../application/installRun.ts';

interface BundleInstall {
	/** One for each bundle, in the order they are installed. */
	plans: ShallowRef<InstallPlan[] | null>;
	/** What is ticked, for each plan. */
	chosen: Ref<Set<string>[]>;
	planning: Ref<boolean>;
	running: Ref<boolean>;
	progress: Ref<{ done: number; total: number }>;
	outcomes: ShallowRef<InstallOutcome[] | null>;
	error: Ref<string | null>;
	/**
	 * Compares the files with the wiki, putting each bundle after those before it in the list; again for other
	 * names, without reading the wiki again. New Subjects get their pages as on a wiki of the type given.
	 */
	prepare: ( bundles: readonly BundleToInstall[], records: readonly InstallRecord[], subjectFirst: boolean ) => Promise<void>;
	run: () => Promise<void>;
}

export function useBundleInstall(): BundleInstall {
	const repository = ModelBundleServices.getDataModelRepository();
	const recordRepository = ModelBundleServices.getInstallRecordRepository();
	const reader = new CurrentStateReader( repository );

	const plans = shallowRef<InstallPlan[] | null>( null );
	const chosen = ref<Set<string>[]>( [] );
	const planning = ref( false );
	const running = ref( false );
	const progress = ref( { done: 0, total: 0 } );
	const outcomes = shallowRef<InstallOutcome[] | null>( null );
	const error = ref<string | null>( null );
	// Numbers the comparisons asked for, so one answered after a later one cannot replace its newer plans.
	let preparation = 0;
	// The wiki type the plans give pages by, which the run gives them by too.
	let plannedSubjectFirst = false;

	async function prepare( bundles: readonly BundleToInstall[], records: readonly InstallRecord[], subjectFirst: boolean ): Promise<void> {
		const sequence = ++preparation;
		planning.value = true;
		error.value = null;

		try {
			const prepared = await planInstalls( bundles, { reader, repository, records, subjectFirst } );

			if ( sequence === preparation ) {
				plans.value = prepared;
				chosen.value = prepared.map( defaultChoice );
				plannedSubjectFirst = subjectFirst;
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
			outcomes.value = await runInstalls(
				preparedPlans.map( ( plan, index ) => ( {
					plan,
					chosen: chosen.value[ index ],
					context: { repository, recordRepository, comment: summaryOf( plan ), subjectFirst: plannedSubjectFirst },
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

function summaryOf( plan: InstallPlan ): string {
	return mw.msg( plan.record === null ? 'neowiki-bundle-install-summary' : 'neowiki-bundle-update-summary', plan.bundleName );
}
