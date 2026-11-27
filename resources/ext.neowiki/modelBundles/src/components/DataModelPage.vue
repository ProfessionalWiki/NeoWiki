<template>
	<div
		ref="root"
		class="ext-neowiki-data-model"
	>
		<BundleInstaller
			v-if="shown === 'import' && records !== null"
			:records="records"
			:updating="updating"
			:from-source="fromSource"
			@close="onInstallClosed"
		/>

		<BundleExporter
			v-else-if="shown === 'export'"
			@close="show( 'overview' )"
		/>

		<DataModelSetup
			v-else-if="shown === 'setup' && records !== null"
			:records="records"
		/>

		<template v-else>
			<p class="ext-neowiki-data-model__intro">
				{{ $i18n( 'neowiki-data-model-intro' ).text() }}
			</p>

			<div class="ext-neowiki-data-model__toolbar">
				<CdxButton
					v-if="canInstall"
					action="progressive"
					:weight="offersSetup ? 'normal' : 'primary'"
					@click="openInstall( null, false )"
				>
					<CdxIcon :icon="cdxIconUpload" />
					{{ $i18n( 'neowiki-bundle-install-title' ).text() }}
				</CdxButton>
				<CdxButton
					v-if="!wikiIsEmpty"
					@click="show( 'export' )"
				>
					<CdxIcon :icon="cdxIconDownload" />
					{{ $i18n( 'neowiki-bundle-export-title' ).text() }}
				</CdxButton>
			</div>

			<CdxMessage
				v-if="loadError !== null"
				type="error"
			>
				{{ loadError }}
			</CdxMessage>

			<CdxMessage
				v-else-if="canInstall && wikiIsEmpty"
				class="ext-neowiki-data-model__setup"
			>
				<p>{{ $i18n( 'neowiki-bundle-setup-empty-state' ).text() }}</p>
				<a
					v-if="offersSetup"
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--action-progressive cdx-button--weight-primary"
					:href="setupUrl"
				>
					{{ $i18n( 'neowiki-bundle-setup-title' ).text() }}
				</a>
			</CdxMessage>

			<CdxProgressBar
				v-else-if="records === null"
				:inline="true"
			/>

			<div v-else>
				<BundleGroup
					v-for="record in records"
					:key="record.name"
					:record="record"
					:can-install="canInstall"
					:load-items="() => readOwnedItems( record, reader )"
					@update="openInstall( record, $event )"
				/>
				<BundleGroup
					:record="null"
					:can-install="canInstall"
					:load-items="loadUnownedItems"
				/>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, shallowRef } from 'vue';
import { CdxButton, CdxIcon, CdxMessage, CdxProgressBar } from '@wikimedia/codex';
import { cdxIconDownload, cdxIconUpload } from '@wikimedia/codex-icons';
import { ModelBundleServices } from '../ModelBundleServices.ts';
import type { InstallRecord } from '../application/InstallRecord.ts';
import { CurrentStateReader } from '../application/CurrentStateReader.ts';
import { type DataModelItem, readOwnedItems, readUnownedItems } from '../application/bundleStatus.ts';
import BundleGroup from './BundleGroup.vue';
import BundleInstaller from './BundleInstaller.vue';
import BundleExporter from './BundleExporter.vue';
import DataModelSetup from './DataModelSetup.vue';
import { setupConfig } from '../presentation/setupConfig.ts';

const props = defineProps<{
	/** The part of the page to open: "setup", or null for the overview. */
	view: string | null;
}>();

const repository = ModelBundleServices.getDataModelRepository();
const recordRepository = ModelBundleServices.getInstallRecordRepository();
const permissionHints = ModelBundleServices.getModelBundlePermissionHints();

const root = ref<HTMLElement | null>( null );
const shown = ref<'overview' | 'import' | 'export' | 'setup'>( 'overview' );
const records = shallowRef<InstallRecord[] | null>( null );
const loadError = ref<string | null>( null );
const canInstall = ref( false );
const updating = shallowRef<InstallRecord | null>( null );
const fromSource = ref( false );
// A new reader for each load, so that what an install changed is read anew.
const reader = shallowRef( new CurrentStateReader( repository ) );
const setupUrl = mw.util.getUrl( 'Special:DataModel/setup' );
const catalogUrls = ( mw.config.get( 'wgNeoWikiModelBundleCatalogs' ) ?? [] ) as string[];

async function loadRecords(): Promise<void> {
	records.value = null;
	loadError.value = null;
	reader.value = new CurrentStateReader( repository );

	try {
		records.value = await recordRepository.getInstallRecords();
	} catch ( error ) {
		loadError.value = error instanceof Error ? error.message : String( error );
	}
}

function loadUnownedItems(): Promise<DataModelItem[]> {
	return readUnownedItems( records.value ?? [], reader.value );
}

function openInstall( record: InstallRecord | null, readFromSource: boolean ): void {
	updating.value = record;
	fromSource.value = readFromSource;
	show( 'import' );
}

function onInstallClosed( installed: boolean ): void {
	show( 'overview' );

	if ( installed ) {
		loadRecords();
	}
}

// A wiki without a data model.
const wikiIsEmpty = computed( () => records.value?.length === 0 && !setupConfig().wikiHasSchemas );

// The setup installs from the wiki's catalogs, which only users who may install bundles are told of.
const offersSetup = computed( () => canInstall.value && wikiIsEmpty.value && catalogUrls.length > 0 );

async function show( part: 'overview' | 'import' | 'export' | 'setup' ): Promise<void> {
	shown.value = part;
	await nextTick();
	root.value?.scrollIntoView?.( { block: 'start' } );
}

onMounted( async () => {
	loadRecords();

	try {
		canInstall.value = await permissionHints.canInstallModelBundles();
	} catch ( _error ) {
		canInstall.value = false;
	}

	if ( props.view === 'setup' && canInstall.value && catalogUrls.length > 0 ) {
		show( 'setup' );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-data-model {
	&__intro {
		margin-top: 0;
	}

	&__toolbar {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50;
		margin-bottom: @spacing-100;
	}
}
</style>
