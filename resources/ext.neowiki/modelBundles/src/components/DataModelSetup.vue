<template>
	<div class="ext-neowiki-data-model-setup">
		<SetupSteps
			v-if="step !== 'done'"
			:steps="barSteps"
			:current="step"
			:disabled="installer.running.value"
			@go="returnTo"
		/>

		<h2 class="ext-neowiki-data-model-setup__title">
			{{ $i18n( headings[ step ] ).text() }}
		</h2>

		<WikiSettingsEditor
			v-if="step === 'settings'"
			v-model="settings"
			@continue="next"
		>
			<template #actions>
				<a
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled"
					:href="dataModelUrl"
				>
					{{ $i18n( 'cancel' ).text() }}
				</a>
			</template>
		</WikiSettingsEditor>

		<template v-else>
			<CdxMessage
				v-if="error !== null || installer.error.value !== null"
				type="error"
			>
				{{ error ?? installer.error.value }}
			</CdxMessage>

			<CdxProgressBar
				v-if="catalogs === null || reading || installer.planning.value"
				:inline="true"
			/>

			<template v-else-if="step === 'model'">
				<CdxMessage
					v-for="unreadable in catalogs.unreadable"
					:key="unreadable.url"
					type="warning"
					:inline="true"
				>
					{{ unreadable.reason }}
				</CdxMessage>
				<p v-if="catalogs.unreadable.length === catalogUrls.length">
					{{ $i18n( 'neowiki-bundle-setup-no-catalog-read' ).text() }}
				</p>
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
					<CdxInfoChip
						v-if="isPreselected( entry )"
						class="ext-neowiki-data-model-setup__recommended"
					>
						{{ $i18n( 'neowiki-bundle-setup-recommended' ).text() }}
					</CdxInfoChip>
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
					<CdxInfoChip
						v-if="isPreselected( entry )"
						class="ext-neowiki-data-model-setup__recommended"
					>
						{{ $i18n( 'neowiki-bundle-setup-recommended' ).text() }}
					</CdxInfoChip>
				</div>
			</template>

			<template v-else-if="plans !== null">
				<p
					v-if="asksSettings"
					class="ext-neowiki-data-model-setup__settings"
				>
					{{ settingsLine( settings ) }}
				</p>
				<p v-if="installer.running.value">
					<CdxProgressBar :inline="true" />
					{{ $i18n( 'neowiki-bundle-progress', installer.progress.value.done, installer.progress.value.total ).text() }}
				</p>
				<BundlePlans
					v-model="installer.chosen.value"
					:plans="plans"
					:outcomes="installer.outcomes.value"
					:required-by="requirers"
					:disabled="installer.running.value"
				/>
			</template>

			<p v-if="step === 'done' && modelName === START_EMPTY">
				{{ $i18n( 'neowiki-bundle-setup-nothing-installed' ).text() }}
			</p>

			<div class="ext-neowiki-data-model-setup__actions">
				<CdxButton
					v-if="step === 'review'"
					action="progressive"
					weight="primary"
					:disabled="plans === null || installer.planning.value || installer.running.value || saving"
					@click="install"
				>
					{{ $i18n( 'neowiki-bundle-install-button' ).text() }}
				</CdxButton>
				<CdxButton
					v-else-if="step !== 'done'"
					action="progressive"
					weight="primary"
					:disabled="catalogs === null || reading || saving || ( step === 'model' && modelName === undefined )"
					@click="next"
				>
					{{ step === 'model' && modelName === START_EMPTY ?
						$i18n( 'neowiki-bundle-setup-finish' ).text() :
						$i18n( 'neowiki-bundle-setup-continue' ).text() }}
				</CdxButton>
				<CdxButton
					v-if="previousStep !== null"
					:disabled="installer.running.value"
					@click="back"
				>
					{{ $i18n( 'neowiki-bundle-setup-back' ).text() }}
				</CdxButton>
				<template v-if="step === 'done'">
					<a
						class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--action-progressive cdx-button--weight-primary"
						:href="modelName === START_EMPTY ? schemasUrl : createSubjectUrl"
					>
						{{ modelName === START_EMPTY ?
							$i18n( 'neowiki-bundle-setup-create-schema' ).text() :
							$i18n( 'neowiki-bundle-setup-create-subject' ).text() }}
					</a>
					<a
						class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled"
						:href="dataModelUrl"
					>
						{{ $i18n( 'neowiki-bundle-setup-open-data-model' ).text() }}
					</a>
				</template>
				<a
					v-else
					class="cdx-button cdx-button--fake-button"
					:class="installer.running.value ? 'cdx-button--fake-button--disabled' : 'cdx-button--fake-button--enabled'"
					:href="installer.running.value ? undefined : dataModelUrl"
					:aria-disabled="installer.running.value ? 'true' : undefined"
				>
					{{ $i18n( 'cancel' ).text() }}
				</a>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { CdxButton, CdxCheckbox, CdxInfoChip, CdxMessage, CdxProgressBar, CdxRadio } from '@wikimedia/codex';
import type { InstallRecord } from '../application/InstallRecord.ts';
import { type CatalogEntry, installOrder, requirersIn } from '../application/Catalog.ts';
import { sameBundleName } from '../application/bundleName.ts';
import { type Catalogs, readCatalogs, readEntryBundles } from '../persistence/catalogFile.ts';
import type { PageValues } from 'ext.neowiki';
import { asksHowTheWikiWorks, setupConfig } from '../presentation/setupConfig.ts';
import { saveSettings, settingsLine, valueInEffect } from '../presentation/wikiSettings.ts';
import { ModelBundleServices } from '../ModelBundleServices.ts';
import { cameFrom, enterStep, reloadPage, saveChoices, savedChoices, stepInUrl } from '../presentation/setupHistory.ts';
import { useBundleInstall } from '../composables/useBundleInstall.ts';
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

const installer = useBundleInstall();
const pageSaver = ModelBundleServices.getPageSaver();
const asksSettings = asksHowTheWikiWorks( props.records.length );
// What MediaWiki:NeoWiki will set, which installing, or finishing without, saves first.
const settings = ref<PageValues>( { ...setupConfig().settings.pageValues } );
const saving = ref( false );
const firstStep: Step = asksSettings ? 'settings' : 'model';
const step = ref<Step>( initialStep() );
const catalogs = shallowRef<Catalogs | null>( null );
const reading = ref( false );
const error = ref<string | null>( null );
const catalogUrls = ( mw.config.get( 'wgNeoWikiModelBundleCatalogs' ) ?? [] ) as string[];
const preselection = ( mw.config.get( 'wgNeoWikiModelBundlePreselection' ) ?? [] ) as string[];
// Undefined until one is chosen, or preselected.
const modelName = ref<string | undefined>( undefined );
const destinationNames = ref<string[]>( [] );
// The names of the model and destinations the plans were made for.
const plannedFor = ref<string | null>( null );
// The bundles of the plans that the chosen ones pulled in, with the names of those that require each.
const requirers = shallowRef<ReadonlyMap<string, string[]>>( new Map() );
const createSubjectUrl = mw.util.getUrl( 'Special:CreateSubject' );
const schemasUrl = mw.util.getUrl( 'Special:Schemas' );
const dataModelUrl = mw.util.getUrl( 'Special:DataModel' );

const entries = computed( () => catalogs.value?.entries ?? [] );
const models = computed( () => entries.value.filter( ( entry ) => entry.kind === 'model' ) );
const chosenModel = computed( () => models.value.find( ( entry ) => entry.name === modelName.value ) ?? null );
const destinations = computed( () => chosenModel.value === null ? [] :
	entries.value.filter( ( entry ) => entry.kind === 'destination' && isServedBy( entry, chosenModel.value as CatalogEntry ) ) );
const chosenEntries = computed( () => chosenModel.value === null ? [] :
	[ chosenModel.value, ...destinations.value.filter( ( entry ) => destinationNames.value.includes( entry.name ) ) ] );
const subjectFirst = computed( () => valueInEffect( settings.value, 'subjectFirst' ) );
const plans = computed( () => plannedFor.value === planKey( chosenEntries.value ) ? installer.plans.value : null );

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

// The model and destinations a plan is of, and the wiki type it gives pages by.
function planKey( chosen: readonly CatalogEntry[] ): string {
	return JSON.stringify( [ chosen.map( ( entry ) => entry.name ), subjectFirst.value ] );
}

// Where the step shown is among the steps the choices lead through: -1 for the end.
function flowIndex(): number {
	return flowSteps.value.findIndex( ( candidate ) => candidate === step.value );
}

async function next(): Promise<void> {
	const following = flowSteps.value[ flowIndex() + 1 ] as BarStep | undefined;
	const target: Step = following ?? 'done';

	// Starting empty ends the setup here, which then saves what the first step asked for.
	if ( target === 'done' && !await savedSettings() ) {
		return;
	}

	await goTo( target );
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
	if ( installer.running.value ) {
		return;
	}

	if ( installer.outcomes.value !== null ) {
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
		const order = installOrder( chosen, entries.value, props.records.map( ( record ) => record.name ) );
		const key = planKey( chosen );
		await installer.prepare( await readEntryBundles( order ), props.records, subjectFirst.value );
		requirers.value = requirersIn( order, chosen );
		plannedFor.value = key;
	} catch ( readError ) {
		error.value = readError instanceof Error ? readError.message : String( readError );
	} finally {
		reading.value = false;
	}
}

async function install(): Promise<void> {
	if ( !await savedSettings() ) {
		return;
	}

	await installer.run();

	if ( installer.outcomes.value !== null ) {
		// What was installed is not to be installed again from the review, so its end takes the review's place.
		enterStep( 'done', 'replace' );
		step.value = 'done';
	}
}

/**
 * Saves the settings the first step asked for, before anything else is written. Whether that went well.
 */
async function savedSettings(): Promise<boolean> {
	saving.value = true;
	error.value = await saveSettings( settings.value, pageSaver );
	saving.value = false;
	return error.value === null;
}

watch( [ modelName, destinationNames, settings ], () => {
	saveChoices( { model: modelName.value, destinations: destinationNames.value, settings: settings.value } );
}, { deep: true } );

onMounted( async () => {
	window.addEventListener( 'popstate', onPopState );
	catalogs.value = await readCatalogs( catalogUrls );

	// What was chosen before a reload, when the URL names a step.
	const saved = stepInUrl() === null ? null : savedChoices();
	modelName.value = saved === null ? models.value.find( isPreselected )?.name : saved.model;
	destinationNames.value = saved === null ?
		entries.value.filter( ( entry ) => entry.kind === 'destination' && isPreselected( entry ) ).map( ( entry ) => entry.name ) :
		[ ...saved.destinations ];
	settings.value = saved?.settings ?? settings.value;

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
		display: flex;
		align-items: flex-start;
		gap: @spacing-50;
		margin-top: @spacing-75;
		border: @border-subtle;
		border-radius: @border-radius-base;
		padding: @spacing-75;

		> .cdx-radio,
		> .cdx-checkbox {
			flex-grow: 1;
		}
	}

	&__recommended {
		flex-shrink: 0;
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50;
		margin-top: @spacing-150;
	}
}
</style>
