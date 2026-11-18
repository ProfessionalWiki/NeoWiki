<template>
	<div
		v-if="!settingsDone"
		class="ext-neowiki-bundle-import"
	>
		<h2 class="ext-neowiki-bundle-import__title">
			{{ $i18n( 'neowiki-bundle-install-title' ).text() }}
		</h2>
		<WikiSettingsEditor @done="settingsDone = true">
			<template #actions>
				<CdxButton @click="emit( 'close', false )">
					{{ $i18n( 'cancel' ).text() }}
				</CdxButton>
			</template>
		</WikiSettingsEditor>
	</div>

	<div
		v-else
		class="ext-neowiki-bundle-import"
	>
		<h2 class="ext-neowiki-bundle-import__title">
			{{ updated === null ?
				$i18n( 'neowiki-bundle-install-title' ).text() :
				$i18n( 'neowiki-bundle-update-title', updated.name ).text() }}
		</h2>

		<CdxMessage
			v-if="readError !== null || importer.error.value !== null"
			type="error"
		>
			{{ readError ?? importer.error.value }}
		</CdxMessage>

		<div
			v-if="bundle === null"
			class="ext-neowiki-bundle-import__sources"
		>
			<p v-if="reading">
				<CdxProgressBar :inline="true" />
			</p>
			<template v-else>
				<CdxField
					v-if="catalogUrls.length > 0 && updating === null"
					:is-fieldset="true"
				>
					<template #label>
						{{ $i18n( 'neowiki-bundle-source-catalog' ).text() }}
					</template>
					<CdxProgressBar
						v-if="catalogs === null"
						:inline="true"
					/>
					<template v-else>
						<CdxMessage
							v-for="unreadable in catalogs.unreadable"
							:key="unreadable.url"
							type="warning"
							:inline="true"
						>
							{{ unreadable.reason }}
						</CdxMessage>
						<p v-if="catalogs.entries.length === 0 && catalogs.unreadable.length === 0">
							{{ $i18n( 'neowiki-bundle-catalog-empty' ).text() }}
						</p>
						<CatalogEntryPicker
							:entries="catalogs.entries"
							:records="records"
							:disabled="reading"
							@choose="readEntry"
						/>
					</template>
				</CdxField>
				<CdxField>
					<template #label>
						{{ $i18n( 'neowiki-bundle-source-file' ).text() }}
					</template>
					<CdxButton @click="fileInput?.click()">
						<CdxIcon :icon="cdxIconUpload" />
						{{ $i18n( 'neowiki-bundle-source-file-button' ).text() }}
					</CdxButton>
				</CdxField>
				<input
					ref="fileInput"
					type="file"
					accept=".json,application/json"
					hidden
					@change="onFileChosen"
				>
				<CdxField>
					<template #label>
						{{ $i18n( 'neowiki-bundle-source-url' ).text() }}
					</template>
					<div class="ext-neowiki-bundle-import__url">
						<CdxTextInput
							v-model="url"
							input-type="url"
							placeholder="https://"
							@keydown.enter="readUrl"
						/>
						<CdxButton
							:disabled="url.trim() === ''"
							@click="readUrl"
						>
							{{ $i18n( 'neowiki-bundle-source-url-button' ).text() }}
						</CdxButton>
					</div>
				</CdxField>
			</template>
		</div>

		<template v-else>
			<div class="ext-neowiki-bundle-import__summary">
				<CdxField
					v-if="bundle.metadata === null && updating === null"
					:status="nameIsUsable ? 'default' : 'error'"
					:messages="nameIsUsable ? {} : { error: $i18n( 'neowiki-bundle-name-unusable' ).text() }"
				>
					<template #label>
						{{ $i18n( 'neowiki-bundle-name' ).text() }}
					</template>
					<template #help-text>
						{{ $i18n( 'neowiki-bundle-name-help' ).text() }}
					</template>
					<CdxTextInput
						v-model="name"
						:disabled="importer.running.value || importer.outcomes.value !== null"
						@change="replan"
					/>
				</CdxField>
				<p
					v-else
					class="ext-neowiki-bundle-import__name"
				>
					<strong>{{ name }}</strong>
					<span v-if="bundle.metadata !== null && bundle.metadata.version !== ''">
						{{ $i18n( 'neowiki-bundle-version', bundle.metadata.version ).text() }}
					</span>
				</p>
				<CdxMessage
					v-if="( bundle.metadata !== null || updating !== null ) && !nameIsUsable"
					type="error"
					:inline="true"
				>
					{{ $i18n( 'neowiki-bundle-name-unusable' ).text() }}
				</CdxMessage>
				<p v-if="bundle.metadata?.description">
					{{ bundle.metadata.description }}
				</p>
				<p v-if="mainPlan?.record">
					{{ mainPlan.record.version === '' ?
						$i18n( 'neowiki-bundle-updates-unversioned' ).text() :
						$i18n( 'neowiki-bundle-updates', mainPlan.record.version ).text() }}
				</p>
			</div>

			<p v-if="importer.planning.value">
				<CdxProgressBar :inline="true" />
				{{ $i18n( 'neowiki-bundle-comparing' ).text() }}
			</p>

			<p v-if="importer.running.value">
				<CdxProgressBar :inline="true" />
				{{ $i18n( 'neowiki-bundle-progress', importer.progress.value.done, importer.progress.value.total ).text() }}
			</p>

			<BundlePlans
				v-if="importer.plans.value !== null && !importer.planning.value"
				v-model="importer.chosen.value"
				:plans="importer.plans.value"
				:outcomes="importer.outcomes.value"
				:required-by="requiredBy"
				:disabled="importer.running.value"
			/>
		</template>

		<div class="ext-neowiki-bundle-import__actions">
			<CdxButton
				v-if="bundle !== null && importer.outcomes.value === null"
				action="progressive"
				weight="primary"
				:disabled="!canRun"
				@click="importer.run()"
			>
				{{ mainPlan?.record ?
					$i18n( 'neowiki-bundle-update-button' ).text() :
					$i18n( 'neowiki-bundle-install-button' ).text() }}
			</CdxButton>
			<CdxButton
				:disabled="importer.running.value"
				@click="emit( 'close', importer.outcomes.value !== null )"
			>
				{{ importer.outcomes.value === null ?
					$i18n( 'cancel' ).text() :
					$i18n( 'neowiki-bundle-back' ).text() }}
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, shallowRef } from 'vue';
import { CdxButton, CdxField, CdxIcon, CdxMessage, CdxProgressBar, CdxTextInput } from '@wikimedia/codex';
import { cdxIconUpload } from '@wikimedia/codex-icons';
import { parseBundleFile } from '../persistence/bundleFile.ts';
import type { BundleToInstall } from '../application/ModelBundle.ts';
import { type CatalogEntry, installOrder, requirersIn } from '../application/Catalog.ts';
import { type Catalogs, readCatalogs, readEntryBundles } from '../persistence/catalogFile.ts';
import { FILE_SOURCE, type InstallRecord } from '../application/InstallRecord.ts';
import { isUsableBundleName, sameBundleName } from '../application/bundleName.ts';
import { fetchBundleFile } from '../persistence/fetchBundleFile.ts';
import { useBundleImport } from '../composables/useBundleImport.ts';
import BundlePlans from './BundlePlans.vue';
import CatalogEntryPicker from './CatalogEntryPicker.vue';
import WikiSettingsEditor from './WikiSettingsEditor.vue';
import { asksHowTheWikiWorks } from '../presentation/setupConfig.ts';

const props = defineProps<{
	records: readonly InstallRecord[];
	/** The installed bundle to update; null to install one. */
	updating: InstallRecord | null;
	/** Whether to read the bundle from the URL it was installed from, at once. */
	fromSource: boolean;
}>();

const emit = defineEmits<{
	/** Whether the import wrote anything, so that the page reads the wiki again. */
	close: [ imported: boolean ];
}>();

const importer = useBundleImport();
const settingsDone = ref( props.updating !== null || !asksHowTheWikiWorks( props.records.length ) );
const fileInput = ref<HTMLInputElement | null>( null );
const url = ref( '' );
const reading = ref( false );
const readError = ref<string | null>( null );
// The bundle chosen, last, after what it requires from a catalog that the wiki has not installed.
const toInstall = shallowRef<BundleToInstall[]>( [] );
const bundle = computed( () => toInstall.value[ toInstall.value.length - 1 ]?.bundle ?? null );
const requiredBy = shallowRef<ReadonlyMap<string, string[]>>( new Map() );
const name = ref( '' );
const catalogUrls = ( mw.config.get( 'wgNeoWikiModelBundleCatalogs' ) ?? [] ) as string[];
const catalogs = shallowRef<Catalogs | null>( null );

const nameIsUsable = computed( () => isUsableBundleName( name.value ) );

// The bundle chosen, after those it requires.
const mainPlan = computed( () => importer.plans.value?.[ importer.plans.value.length - 1 ] ?? null );

// Installing a file whose bundle the wiki has installed updates that bundle.
const updated = computed( () => props.updating ?? mainPlan.value?.record ?? null );

// A bundle install writes its record even when no item changes, as for a wiki that already has the items.
const canRun = computed( () => importer.plans.value !== null && !importer.planning.value && !importer.running.value &&
	nameIsUsable.value );

async function onFileChosen( event: Event ): Promise<void> {
	const input = event.target as HTMLInputElement;
	const file = input.files?.[ 0 ];

	// Cleared so that choosing the same file again still fires a change event.
	input.value = '';

	if ( file !== undefined ) {
		await read( () => readFile( () => file.text(), FILE_SOURCE, file.name.replace( /\.json$/i, '' ) ) );
	}
}

async function readUrl(): Promise<void> {
	const address = url.value.trim();

	if ( address !== '' ) {
		await read( () => readFile( () => fetchBundleFile( address ), address, '' ) );
	}
}

function readEntry( entry: CatalogEntry ): Promise<void> {
	return read( async () => {
		const order = installOrder( [ entry ], catalogs.value?.entries ?? [], props.records.map( ( record ) => record.name ) );
		return { bundles: await readEntryBundles( order ), requiredBy: requirersIn( order, [ entry ] ) };
	} );
}

async function readFile( text: () => Promise<string>, source: string, fileName: string ): Promise<Read> {
	const parsed = parseBundleFile( await text() );
	const metadataName = parsed.metadata?.name ?? null;

	if ( props.updating !== null && metadataName !== null && !sameBundleName( metadataName, props.updating.name ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-other-bundle', metadataName, props.updating.name ) );
	}

	return { bundles: [ { bundle: parsed, name: metadataName ?? props.updating?.name ?? fileName, source } ], requiredBy: new Map() };
}

interface Read {
	readonly bundles: BundleToInstall[];
	readonly requiredBy: ReadonlyMap<string, string[]>;
}

async function read( load: () => Promise<Read> ): Promise<void> {
	reading.value = true;
	readError.value = null;

	try {
		const loaded = await load();
		name.value = loaded.bundles[ loaded.bundles.length - 1 ].name;
		toInstall.value = loaded.bundles;
		requiredBy.value = loaded.requiredBy;
		await replan();
	} catch ( error ) {
		readError.value = error instanceof Error ? error.message : String( error );
	} finally {
		reading.value = false;
	}
}

async function replan(): Promise<void> {
	const chosen = toInstall.value[ toInstall.value.length - 1 ];

	if ( chosen !== undefined && nameIsUsable.value ) {
		await importer.prepare( [ ...toInstall.value.slice( 0, -1 ), { ...chosen, name: name.value.trim() } ], props.records );
	}
}

onMounted( async () => {
	if ( props.updating !== null && props.fromSource ) {
		url.value = props.updating.source;
		readUrl();
	}

	if ( props.updating === null && catalogUrls.length > 0 ) {
		catalogs.value = await readCatalogs( catalogUrls );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-bundle-import {
	&__title {
		margin-top: 0;
	}

	&__url {
		display: flex;
		gap: @spacing-50;
		max-width: @size-4000;

		.cdx-text-input {
			flex-grow: 1;
		}
	}

	&__summary {
		margin-bottom: @spacing-150;
	}

	&__name span {
		margin-inline-start: @spacing-50;
		color: @color-subtle;
	}

	&__actions {
		display: flex;
		gap: @spacing-50;
		margin-top: @spacing-150;
	}
}
</style>
