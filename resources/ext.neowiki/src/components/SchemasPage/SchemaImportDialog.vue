<template>
	<CdxDialog
		:open="true"
		class="ext-neowiki-ui ext-neowiki-schema-import-dialog"
		:title="title"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="runImport"
		@default="emit( 'close' )"
		@update:open="onUpdateOpen"
	>
		<template #header>
			<DialogHeader
				:title="title"
				:use-close-button="!importer.running.value"
				@close="onUpdateOpen( false )"
			>
				<template
					v-if="bundle.metadata !== null && bundle.metadata.version !== ''"
					#supporting-text
				>
					{{ $i18n( 'neowiki-bundle-version', bundle.metadata.version ).text() }}
				</template>
			</DialogHeader>
		</template>

		<CdxMessage
			v-if="importer.error.value !== null"
			type="error"
		>
			{{ importer.error.value }}
		</CdxMessage>

		<p v-else-if="importer.plan.value === null">
			<CdxProgressBar :inline="true" />
			{{ $i18n( 'neowiki-bundle-comparing' ).text() }}
		</p>

		<BundlePreview
			v-else-if="importer.outcome.value === null"
			v-model="importer.chosen.value"
			:plan="importer.plan.value"
			:disabled="importer.running.value"
		/>

		<p v-if="importer.running.value">
			<CdxProgressBar :inline="true" />
			{{ $i18n( 'neowiki-bundle-progress', importer.progress.value.done, importer.progress.value.total ).text() }}
		</p>

		<div
			v-if="importer.outcome.value !== null && importer.plan.value !== null"
			ref="outcomeElement"
			tabindex="-1"
		>
			<ImportResults
				:plan="importer.plan.value"
				:outcome="importer.outcome.value"
			/>
		</div>
	</CdxDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { CdxDialog, CdxMessage, CdxProgressBar } from '@wikimedia/codex';
import type { DialogAction, PrimaryDialogAction } from '@wikimedia/codex';
import DialogHeader from '@/components/common/DialogHeader.vue';
import BundlePreview from '@/components/DataModelPage/BundlePreview.vue';
import ImportResults from '@/components/DataModelPage/ImportResults.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useBundleImport } from '@/composables/useBundleImport.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { ModelBundle } from '@/application/modelBundle/ModelBundle.ts';
import { FILE_SOURCE } from '@/application/modelBundle/InstallRecord.ts';

const props = defineProps<{
	/** With metadata, a Model bundle to install or update; without, items to import as they are. */
	bundle: ModelBundle;
}>();

const emit = defineEmits<{
	close: [];
	imported: [];
}>();

const schemaStore = useSchemaStore();
const recordRepository = NeoWikiServices.getInstallRecordRepository();
const importer = useBundleImport();
const outcomeElement = ref<HTMLElement | null>( null );

const title = computed( () => {
	if ( props.bundle.metadata === null ) {
		return mw.msg( 'neowiki-schemas-import-title' );
	}

	return mw.msg( importer.plan.value?.record ? 'neowiki-bundle-update-title' : 'neowiki-bundle-install-title-named', props.bundle.metadata.name );
} );

// A bundle install writes its record even when no item changes; a plain import with none chosen does nothing.
const primaryAction = computed( (): PrimaryDialogAction | undefined => {
	const plan = importer.plan.value;

	if ( plan === null || importer.outcome.value !== null ) {
		return undefined;
	}

	return {
		label: mw.msg( plan.bundleName === null ?
			'neowiki-schemas-import-confirm' :
			( plan.record === null ? 'neowiki-bundle-install-button' : 'neowiki-bundle-update-button' ) ),
		actionType: 'progressive',
		disabled: importer.running.value || importer.planning.value ||
			( plan.bundleName === null && importer.chosen.value.size === 0 )
	};
} );

const defaultAction = computed( (): DialogAction => ( {
	label: mw.msg( importer.outcome.value === null ? 'cancel' : 'cdx-dialog-close-button-label' ),
	disabled: importer.running.value
} ) );

// Closing mid-run would hide which writes went through.
function onUpdateOpen( open: boolean ): void {
	if ( !open && !importer.running.value ) {
		emit( 'close' );
	}
}

async function runImport(): Promise<void> {
	await importer.run( FILE_SOURCE );

	// The store keeps the Schemas it read; those the import wrote are read anew when next needed.
	for ( const outcome of importer.outcome.value?.items ?? [] ) {
		if ( outcome.item.type === 'schema' && outcome.result !== 'failed' ) {
			schemaStore.removeSchema( outcome.item.key );
		}
	}

	emit( 'imported' );

	// Focus left with the import action. The outcome takes it, so the keyboard can close the dialog.
	await nextTick();
	outcomeElement.value?.focus();
}

onMounted( async () => {
	try {
		const records = await recordRepository.getInstallRecords();
		await importer.prepare( props.bundle, props.bundle.metadata?.name ?? null, records );
	} catch ( error ) {
		importer.error.value = error instanceof Error ? error.message : String( error );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-schema-import-dialog {
	&.cdx-dialog {
		max-width: @size-5600;
		max-height: @ext-neowiki-dialog-height-limit;

		@media ( min-width: @min-width-breakpoint-desktop ) {
			height: 40rem;
		}

		/* Codex 2 does not grow the body of a dialog given a height, as Codex 1 does. */
		.cdx-dialog__body {
			flex-grow: 1;
		}
	}
}
</style>
