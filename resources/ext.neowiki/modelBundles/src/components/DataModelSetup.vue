<template>
	<div class="ext-neowiki-data-model-setup">
		<WikiSettingsEditor
			v-if="step === 'settings'"
			@done="step = 'model'"
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

			<template v-else-if="importer.plans.value !== null">
				<p v-if="importer.running.value">
					<CdxProgressBar :inline="true" />
					{{ $i18n( 'neowiki-bundle-progress', importer.progress.value.done, importer.progress.value.total ).text() }}
				</p>
				<BundlePlans
					v-model="importer.chosen.value"
					:plans="importer.plans.value"
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
					:disabled="importer.plans.value === null || importer.planning.value || importer.running.value"
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
					v-if="step === 'destinations' || step === 'review'"
					:disabled="importer.running.value"
					@click="back"
				>
					{{ $i18n( 'neowiki-bundle-setup-back' ).text() }}
				</CdxButton>
				<CdxButton
					:disabled="importer.running.value"
					@click="emit( 'close', importer.outcomes.value !== null )"
				>
					{{ step === 'done' ? $i18n( 'neowiki-bundle-back' ).text() : $i18n( 'cancel' ).text() }}
				</CdxButton>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, shallowRef } from 'vue';
import { CdxButton, CdxCheckbox, CdxMessage, CdxProgressBar, CdxRadio } from '@wikimedia/codex';
import type { InstallRecord } from '../application/InstallRecord.ts';
import { type CatalogEntry, installOrder } from '../application/Catalog.ts';
import { sameBundleName } from '../application/bundleName.ts';
import { type Catalogs, readCatalogs, readEntryBundles } from '../persistence/catalogFile.ts';
import { asksHowTheWikiWorks } from '../presentation/setupConfig.ts';
import { useBundleImport } from '../composables/useBundleImport.ts';
import BundlePlans from './BundlePlans.vue';
import WikiSettingsEditor from './WikiSettingsEditor.vue';

type Step = 'settings' | 'model' | 'destinations' | 'review' | 'done';

const START_EMPTY = '';

const headings = {
	settings: 'neowiki-bundle-setup-settings',
	model: 'neowiki-bundle-setup-model',
	destinations: 'neowiki-bundle-setup-destinations',
	review: 'neowiki-bundle-setup-review',
	done: 'neowiki-bundle-setup-done'
} as const;

const props = defineProps<{
	records: readonly InstallRecord[];
}>();

const emit = defineEmits<{
	/** Whether the setup installed anything, so that the page reads the wiki again. */
	close: [ installed: boolean ];
}>();

const importer = useBundleImport();
const step = ref<Step>( asksHowTheWikiWorks( props.records.length ) ? 'settings' : 'model' );
const catalogs = shallowRef<Catalogs | null>( null );
const reading = ref( false );
const error = ref<string | null>( null );
const preselection = ( mw.config.get( 'wgNeoWikiModelBundlePreselection' ) ?? [] ) as string[];
// Undefined until one is chosen, or preselected.
const modelName = ref<string | undefined>( undefined );
const destinationNames = ref<string[]>( [] );
const createSubjectUrl = mw.util.getUrl( 'Special:CreateSubject' );

const entries = computed( () => catalogs.value?.entries ?? [] );
const models = computed( () => entries.value.filter( ( entry ) => entry.kind === 'model' ) );
const chosenModel = computed( () => models.value.find( ( entry ) => entry.name === modelName.value ) ?? null );
const destinations = computed( () => chosenModel.value === null ? [] :
	entries.value.filter( ( entry ) => entry.kind === 'destination' && isServedBy( entry, chosenModel.value as CatalogEntry ) ) );

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

async function next(): Promise<void> {
	if ( step.value === 'model' ) {
		if ( chosenModel.value === null ) {
			step.value = 'done';
		} else if ( destinations.value.length > 0 ) {
			destinationNames.value = destinations.value.filter( isPreselected ).map( ( entry ) => entry.name );
			step.value = 'destinations';
		} else {
			await review();
		}
	} else if ( step.value === 'destinations' ) {
		await review();
	}
}

function back(): void {
	step.value = step.value === 'review' && destinations.value.length > 0 ? 'destinations' : 'model';
}

/**
 * One preview of the chosen model and destinations, with what they require that the wiki has not installed,
 * each after what it requires.
 */
async function review(): Promise<void> {
	reading.value = true;
	error.value = null;

	try {
		const chosen = [ chosenModel.value as CatalogEntry, ...destinations.value.filter( ( entry ) => destinationNames.value.includes( entry.name ) ) ];
		const bundles = await readEntryBundles( installOrder( chosen, entries.value, props.records.map( ( record ) => record.name ) ) );
		step.value = 'review';
		await importer.prepare( bundles, props.records );
	} catch ( readError ) {
		error.value = readError instanceof Error ? readError.message : String( readError );
	} finally {
		reading.value = false;
	}
}

async function install(): Promise<void> {
	await importer.run();

	if ( importer.outcomes.value !== null ) {
		step.value = 'done';
	}
}

onMounted( async () => {
	catalogs.value = await readCatalogs( ( mw.config.get( 'wgNeoWikiModelBundleCatalogs' ) ?? [] ) as string[] );
	modelName.value = models.value.find( isPreselected )?.name;
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
