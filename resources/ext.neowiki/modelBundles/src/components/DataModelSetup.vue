<template>
	<div class="ext-neowiki-data-model-setup">
		<SetupSteps
			v-if="step !== 'done'"
			:steps="barSteps"
			:current="step"
			:disabled="importer.running.value"
			@go="returnTo"
		/>

		<WikiSettingsEditor
			v-if="step === 'settings'"
			@done="next"
		/>

		<template v-else>
			<h2 class="ext-neowiki-data-model-setup__title">
				{{ $i18n( headings[ step ] ).text() }}
			</h2>

			<CdxMessage
				v-if="error !== null || importer.error.value !== null"
				type="error"
			>
				{{ error ?? importer.error.value }}
			</CdxMessage>

			<CdxProgressBar
				v-if="catalogs === null || reading || importer.planning.value"
				:inline="true"
			/>

			<template v-else-if="step === 'model'">
				<CdxMessage
					v-for="unreadable in catalogs.unreadable"
					:key="unreadable"
					type="warning"
					:inline="true"
				>
					{{ $i18n( 'neowiki-bundle-catalog-unreadable', unreadable ).text() }}
				</CdxMessage>
				<div
					v-for="entry in models"
					:key="entry.name"
					class="ext-neowiki-data-model-setup__option"
				>
					<CdxRadio
						v-model="modelName"
						:input-value="entry.name"
						name="ext-neowiki-data-model-setup-model"
					>
						{{ entry.name }}
						<template
							v-if="entry.description !== ''"
							#description
						>
							{{ entry.description }}
						</template>
					</CdxRadio>
				</div>
				<div class="ext-neowiki-data-model-setup__option">
					<CdxRadio
						v-model="modelName"
						:input-value="START_EMPTY"
						name="ext-neowiki-data-model-setup-model"
					>
						{{ $i18n( 'neowiki-bundle-setup-empty' ).text() }}
						<template #description>
							{{ $i18n( 'neowiki-bundle-setup-empty-description' ).text() }}
						</template>
					</CdxRadio>
				</div>
			</template>

			<template v-else-if="step === 'destinations'">
				<div
					v-for="entry in destinations"
					:key="entry.name"
					class="ext-neowiki-data-model-setup__option"
				>
					<CdxCheckbox
						v-model="destinationNames"
						:input-value="entry.name"
					>
						{{ entry.name }}
						<template
							v-if="entry.description !== ''"
							#description
						>
							{{ entry.description }}
						</template>
					</CdxCheckbox>
				</div>
			</template>

			<template v-else-if="plans !== null">
				<p v-if="importer.running.value">
					<CdxProgressBar :inline="true" />
					{{ $i18n( 'neowiki-bundle-progress', importer.progress.value.done, importer.progress.value.total ).text() }}
				</p>
				<BundlePlans
					v-model="importer.chosen.value"
					:plans="plans"
					:outcomes="importer.outcomes.value"
					:disabled="importer.running.value"
				/>
			</template>

			<p v-if="step === 'done'">
				<a :href="createSubjectUrl">{{ $i18n( 'neowiki-bundle-setup-create-subject' ).text() }}</a>
			</p>

			<div class="ext-neowiki-data-model-setup__actions">
				<CdxButton
					v-if="step === 'review'"
					action="progressive"
					weight="primary"
					:disabled="plans === null || importer.planning.value || importer.running.value"
					@click="install"
				>
					{{ $i18n( 'neowiki-bundle-install-button' ).text() }}
				</CdxButton>
				<CdxButton
					v-else-if="step !== 'done'"
					action="progressive"
					weight="primary"
					:disabled="catalogs === null || reading || ( step === 'model' && modelName === undefined )"
					@click="next"
				>
					{{ $i18n( 'neowiki-bundle-setup-continue' ).text() }}
				</CdxButton>
				<CdxButton
					v-if="previousStep !== null"
					:disabled="importer.running.value"
					@click="back"
				>
					{{ $i18n( 'neowiki-bundle-setup-back' ).text() }}
				</CdxButton>
				<a
					class="cdx-button cdx-button--fake-button"
					:class="importer.running.value ? 'cdx-button--fake-button--disabled' : 'cdx-button--fake-button--enabled'"
					:href="importer.running.value ? undefined : dataModelUrl"
					:aria-disabled="importer.running.value ? 'true' : undefined"
				>
					{{ step === 'done' ? $i18n( 'neowiki-bundle-back' ).text() : $i18n( 'cancel' ).text() }}
				</a>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { CdxButton, CdxCheckbox, CdxMessage, CdxProgressBar, CdxRadio } from '@wikimedia/codex';
import type { InstallRecord } from '../application/InstallRecord.ts';
import { type CatalogEntry, installOrder } from '../application/Catalog.ts';
import { sameBundleName } from '../application/bundleName.ts';
import { type Catalogs, readCatalogs, readEntryBundles } from '../persistence/catalogFile.ts';
import { asksHowTheWikiWorks } from '../presentation/setupConfig.ts';
import { cameFrom, enterStep, reloadPage, saveChoices, savedChoices, stepInUrl } from '../presentation/setupHistory.ts';
import { useBundleImport } from '../composables/useBundleImport.ts';
import BundlePlans from './BundlePlans.vue';
import SetupSteps from './SetupSteps.vue';
import WikiSettingsEditor from './WikiSettingsEditor.vue';

type Step = 'settings' | 'model' | 'destinations' | 'review' | 'done';
type BarStep = Exclude<Step, 'done'>;

const STEPS: readonly Step[] = [ 'settings', 'model', 'destinations', 'review', 'done' ];

const START_EMPTY = '';

const headings = {
	settings: 'neowiki-bundle-setup-settings',
	model: 'neowiki-bundle-setup-model',
	destinations: 'neowiki-bundle-setup-destinations',
	review: 'neowiki-bundle-setup-review',
	done: 'neowiki-bundle-setup-done'
} as const;

// Messages used here:
// * neowiki-bundle-setup-settings
// * neowiki-bundle-setup-step-model
// * neowiki-bundle-setup-step-destinations
// * neowiki-bundle-setup-review
const STEP_LABELS: Record<BarStep, string> = {
	settings: 'neowiki-bundle-setup-settings',
	model: 'neowiki-bundle-setup-step-model',
	destinations: 'neowiki-bundle-setup-step-destinations',
	review: 'neowiki-bundle-setup-review'
};

const props = defineProps<{
	records: readonly InstallRecord[];
}>();

const importer = useBundleImport();
const asksSettings = asksHowTheWikiWorks( props.records.length );
const firstStep: Step = asksSettings ? 'settings' : 'model';
const step = ref<Step>( initialStep() );
const catalogs = shallowRef<Catalogs | null>( null );
const reading = ref( false );
const error = ref<string | null>( null );
const preselection = ( mw.config.get( 'wgNeoWikiModelBundlePreselection' ) ?? [] ) as string[];
// Undefined until one is chosen, or preselected.
const modelName = ref<string | undefined>( undefined );
const destinationNames = ref<string[]>( [] );
// The names of the model and destinations the plans were made for.
const plannedFor = ref<string | null>( null );
const createSubjectUrl = mw.util.getUrl( 'Special:CreateSubject' );
const dataModelUrl = mw.util.getUrl( 'Special:DataModel' );

const entries = computed( () => catalogs.value?.entries ?? [] );
const models = computed( () => entries.value.filter( ( entry ) => entry.kind === 'model' ) );
const chosenModel = computed( () => models.value.find( ( entry ) => entry.name === modelName.value ) ?? null );
const destinations = computed( () => chosenModel.value === null ? [] :
	entries.value.filter( ( entry ) => entry.kind === 'destination' && isServedBy( entry, chosenModel.value as CatalogEntry ) ) );
const chosenEntries = computed( () => chosenModel.value === null ? [] :
	[ chosenModel.value, ...destinations.value.filter( ( entry ) => destinationNames.value.includes( entry.name ) ) ] );
const plans = computed( () => plannedFor.value === namesOf( chosenEntries.value ) ? importer.plans.value : null );

/**
 * The steps the choices made so far lead through.
 */
const flowSteps = computed( (): BarStep[] => [
	...( asksSettings ? [ 'settings' as const ] : [] ),
	'model',
	...( destinations.value.length > 0 ? [ 'destinations' as const ] : [] ),
	...( modelName.value === START_EMPTY ? [] : [ 'review' as const ] )
] );
const barSteps = computed( () => flowSteps.value.map( ( id ) => ( { id, label: mw.msg( STEP_LABELS[ id ] ) } ) ) );
const previousStep = computed( () => flowSteps.value[ flowIndex() - 1 ] ?? null );

function namedStep(): Step | undefined {
	return STEPS.find( ( candidate ) => candidate === stepInUrl() );
}

/**
 * What to show while the catalogs are read, which tell whether the step the URL names is in reach: that step,
 * unless it is the end, which a reload after an install does not return to.
 */
function initialStep(): Step {
	const named = namedStep();
	return named === undefined || named === 'done' ? firstStep : named;
}

/**
 * A destination is offered for a model that, with what it requires and what the wiki has installed, has all
 * that the destination requires.
 */
function isServedBy( destination: CatalogEntry, model: CatalogEntry ): boolean {
	const provided = [ ...props.records.map( ( record ) => record.name ), ...requiredBy( model ) ];
	return destination.requires.every( ( name ) => provided.some( ( providedName ) => sameBundleName( providedName, name ) ) );
}

function requiredBy( entry: CatalogEntry ): string[] {
	try {
		return installOrder( [ entry ], entries.value, [] ).map( ( required ) => required.name );
	} catch ( _error ) {
		return [ entry.name ];
	}
}

function isPreselected( entry: CatalogEntry ): boolean {
	return preselection.some( ( name ) => sameBundleName( name, entry.name ) );
}

function namesOf( chosen: readonly CatalogEntry[] ): string {
	return JSON.stringify( chosen.map( ( entry ) => entry.name ) );
}

// Where the step shown is among the steps the choices lead through: -1 for the end.
function flowIndex(): number {
	return flowSteps.value.findIndex( ( candidate ) => candidate === step.value );
}

function next(): void {
	goTo( flowSteps.value[ flowIndex() + 1 ] ?? 'done' );
}

function back(): void {
	const previous = previousStep.value;

	if ( previous !== null ) {
		returnTo( previous );
	}
}

/**
 * Shows the step in a new history entry, so that the browser's Back returns to the one shown before.
 */
async function goTo( target: Step ): Promise<void> {
	enterStep( target, 'push' );
	await show( target );
}

/**
 * Returns to an earlier step: back through the history when this step was entered from it, so that the
 * browser's Forward leads here again.
 */
function returnTo( target: Step ): void {
	if ( cameFrom( target ) ) {
		history.back();
	} else {
		goTo( target );
	}
}

async function show( target: Step ): Promise<void> {
	step.value = target;

	if ( target === 'review' && plans.value === null ) {
		await plan();
	}
}

/**
 * Shows the step the URL names, or the first one when the choices made do not lead there.
 */
async function showNamedStep(): Promise<void> {
	const named = namedStep();
	const target = named !== undefined && isReachable( named ) ? named : firstStep;

	if ( target !== named ) {
		enterStep( target, 'replace' );
	}

	await show( target );
}

function isReachable( target: Step ): boolean {
	return target === 'done' ? modelName.value === START_EMPTY : flowSteps.value.includes( target );
}

function onPopState(): void {
	if ( importer.running.value ) {
		return;
	}

	if ( importer.outcomes.value !== null ) {
		// The plans compare the wiki as it was before the install, and a reload reads it anew, the choices kept.
		reloadPage();
		return;
	}

	showNamedStep();
}

/**
 * One preview of the chosen model and destinations, with what they require that the wiki has not installed,
 * each after what it requires.
 */
async function plan(): Promise<void> {
	const chosen = chosenEntries.value;
	reading.value = true;
	error.value = null;

	try {
		const bundles = await readEntryBundles( installOrder( chosen, entries.value, props.records.map( ( record ) => record.name ) ) );
		await importer.prepare( bundles, props.records );
		plannedFor.value = namesOf( chosen );
	} catch ( readError ) {
		error.value = readError instanceof Error ? readError.message : String( readError );
	} finally {
		reading.value = false;
	}
}

async function install(): Promise<void> {
	await importer.run();

	if ( importer.outcomes.value !== null ) {
		// What was installed is not to be installed again from the review, so its end takes the review's place.
		enterStep( 'done', 'replace' );
		step.value = 'done';
	}
}

watch( [ modelName, destinationNames ], () => {
	saveChoices( { model: modelName.value, destinations: destinationNames.value } );
}, { deep: true } );

onMounted( async () => {
	window.addEventListener( 'popstate', onPopState );
	catalogs.value = await readCatalogs( ( mw.config.get( 'wgNeoWikiModelBundleCatalogs' ) ?? [] ) as string[] );

	// What was chosen before a reload, when the URL names a step.
	const saved = stepInUrl() === null ? null : savedChoices();
	modelName.value = saved === null ? models.value.find( isPreselected )?.name : saved.model;
	destinationNames.value = saved === null ?
		entries.value.filter( ( entry ) => entry.kind === 'destination' && isPreselected( entry ) ).map( ( entry ) => entry.name ) :
		[ ...saved.destinations ];

	await showNamedStep();
} );

onUnmounted( () => {
	window.removeEventListener( 'popstate', onPopState );
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-data-model-setup {
	max-width: @size-5600;

	&__title {
		margin-top: 0;
	}

	&__option {
		margin-top: @spacing-75;
		border: @border-subtle;
		border-radius: @border-radius-base;
		padding: @spacing-75;
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50;
		margin-top: @spacing-150;
	}
}
</style>
