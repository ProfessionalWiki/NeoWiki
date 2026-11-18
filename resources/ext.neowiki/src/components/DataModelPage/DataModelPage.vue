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
			@close="onImportClosed"
		/>

		<BundleExporter
			v-else-if="shown === 'export'"
			@close="show( 'overview' )"
		/>

		<template v-else>
			<p class="ext-neowiki-data-model__intro">
				{{ $i18n( 'neowiki-data-model-intro' ).text() }}
			</p>

			<div class="ext-neowiki-data-model__toolbar">
				<CdxButton
					v-if="canInstall"
					action="progressive"
					weight="primary"
					@click="openImport( null, false )"
				>
					<CdxIcon :icon="cdxIconUpload" />
					{{ $i18n( 'neowiki-bundle-install-title' ).text() }}
				</CdxButton>
				<CdxButton @click="show( 'export' )">
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
					@update="openImport( record, $event )"
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
import { nextTick, onMounted, ref, shallowRef } from 'vue';
import { CdxButton, CdxIcon, CdxMessage, CdxProgressBar } from '@wikimedia/codex';
import { cdxIconDownload, cdxIconUpload } from '@wikimedia/codex-icons';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { InstallRecord } from '@/application/modelBundle/InstallRecord.ts';
import { CurrentStateReader } from '@/application/modelBundle/CurrentStateReader.ts';
import { type DataModelItem, readOwnedItems, readUnownedItems } from '@/application/modelBundle/bundleStatus.ts';
import BundleGroup from '@/components/DataModelPage/BundleGroup.vue';
import BundleInstaller from '@/components/DataModelPage/BundleInstaller.vue';
import BundleExporter from '@/components/DataModelPage/BundleExporter.vue';

const repository = NeoWikiServices.getDataModelRepository();
const recordRepository = NeoWikiServices.getInstallRecordRepository();
const permissionHints = NeoWikiServices.getModelBundlePermissionHints();

const root = ref<HTMLElement | null>( null );
const shown = ref<'overview' | 'import' | 'export'>( 'overview' );
const records = shallowRef<InstallRecord[] | null>( null );
const loadError = ref<string | null>( null );
const canInstall = ref( false );
const updating = shallowRef<InstallRecord | null>( null );
const fromSource = ref( false );
// A new reader for each load, so that what an import changed is read anew.
const reader = shallowRef( new CurrentStateReader( repository ) );

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

function openImport( record: InstallRecord | null, readFromSource: boolean ): void {
	updating.value = record;
	fromSource.value = readFromSource;
	show( 'import' );
}

function onImportClosed( imported: boolean ): void {
	show( 'overview' );

	if ( imported ) {
		loadRecords();
	}
}

async function show( part: 'overview' | 'import' | 'export' ): Promise<void> {
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
