<template>
	<div class="ext-neowiki-bundle-import">
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
						:disabled="importer.running.value || importer.outcome.value !== null"
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
				<p v-if="importer.plan.value?.record">
					{{ importer.plan.value.record.version === '' ?
						$i18n( 'neowiki-bundle-updates-unversioned' ).text() :
						$i18n( 'neowiki-bundle-updates', importer.plan.value.record.version ).text() }}
				</p>
			</div>

			<p v-if="importer.planning.value">
				<CdxProgressBar :inline="true" />
				{{ $i18n( 'neowiki-bundle-comparing' ).text() }}
			</p>

			<BundlePreview
				v-else-if="importer.plan.value !== null && importer.outcome.value === null"
				v-model="importer.chosen.value"
				:plan="importer.plan.value"
				:disabled="importer.running.value"
			/>

			<p v-if="importer.running.value">
				<CdxProgressBar :inline="true" />
				{{ $i18n( 'neowiki-bundle-progress', importer.progress.value.done, importer.progress.value.total ).text() }}
			</p>

			<ImportResults
				v-if="importer.outcome.value !== null && importer.plan.value !== null"
				:plan="importer.plan.value"
				:outcome="importer.outcome.value"
			/>
		</template>

		<div class="ext-neowiki-bundle-import__actions">
			<CdxButton
				v-if="bundle !== null && importer.outcome.value === null"
				action="progressive"
				weight="primary"
				:disabled="!canRun"
				@click="importer.run( source )"
			>
				{{ importer.plan.value?.record ?
					$i18n( 'neowiki-bundle-update-button' ).text() :
					$i18n( 'neowiki-bundle-install-button' ).text() }}
			</CdxButton>
			<CdxButton
				:disabled="importer.running.value"
				@click="emit( 'close', importer.outcome.value !== null )"
			>
				{{ importer.outcome.value === null ?
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
import type { ModelBundle } from '../application/ModelBundle.ts';
import { FILE_SOURCE, type InstallRecord } from '../application/InstallRecord.ts';
import { isUsableBundleName, sameBundleName } from '../application/bundleName.ts';
import { fetchBundleFile } from '../persistence/fetchBundleFile.ts';
import { useBundleImport } from '../composables/useBundleImport.ts';
import BundlePreview from './BundlePreview.vue';
import ImportResults from './ImportResults.vue';

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
const fileInput = ref<HTMLInputElement | null>( null );
const url = ref( '' );
const reading = ref( false );
const readError = ref<string | null>( null );
const bundle = shallowRef<ModelBundle | null>( null );
const source = ref( FILE_SOURCE );
const name = ref( '' );

const nameIsUsable = computed( () => isUsableBundleName( name.value ) );

// Installing a file whose bundle the wiki has installed updates that bundle.
const updated = computed( () => props.updating ?? importer.plan.value?.record ?? null );

// A bundle install writes its record even when no item changes, as for a wiki that already has the items.
const canRun = computed( () => importer.plan.value !== null && !importer.planning.value && !importer.running.value &&
	nameIsUsable.value );

async function onFileChosen( event: Event ): Promise<void> {
	const input = event.target as HTMLInputElement;
	const file = input.files?.[ 0 ];

	// Cleared so that choosing the same file again still fires a change event.
	input.value = '';

	if ( file !== undefined ) {
		await read( () => file.text(), FILE_SOURCE, file.name.replace( /\.json$/i, '' ) );
	}
}

async function readUrl(): Promise<void> {
	const address = url.value.trim();

	if ( address !== '' ) {
		await read( () => fetchBundleFile( address ), address, '' );
	}
}

async function read( text: () => Promise<string>, from: string, fileName: string ): Promise<void> {
	reading.value = true;
	readError.value = null;

	try {
		const parsed = parseBundleFile( await text() );
		const metadataName = parsed.metadata?.name ?? null;

		if ( props.updating !== null && metadataName !== null && !sameBundleName( metadataName, props.updating.name ) ) {
			throw new Error( mw.msg( 'neowiki-bundle-other-bundle', metadataName, props.updating.name ) );
		}

		name.value = metadataName ?? props.updating?.name ?? fileName;
		source.value = from;
		bundle.value = parsed;
		await replan();
	} catch ( error ) {
		readError.value = error instanceof Error ? error.message : String( error );
	} finally {
		reading.value = false;
	}
}

async function replan(): Promise<void> {
	if ( bundle.value !== null && nameIsUsable.value ) {
		await importer.prepare( bundle.value, name.value.trim(), props.records );
	}
}

onMounted( () => {
	if ( props.updating !== null && props.fromSource ) {
		url.value = props.updating.source;
		readUrl();
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
		max-width: 40rem;

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
