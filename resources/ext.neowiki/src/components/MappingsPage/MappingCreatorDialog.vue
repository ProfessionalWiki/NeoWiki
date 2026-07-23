<template>
	<CdxDialog
		:open="props.open"
		:use-close-button="true"
		class="ext-neowiki-mapping-creator-dialog"
		:title="$i18n( 'neowiki-mapping-creator-title' ).text()"
		@update:open="onDialogUpdateOpen"
	>
		<CdxField
			:status="nameStatus"
			:messages="nameError ? { error: nameError } : {}"
		>
			<CdxTextInput
				v-model="mappingName"
				:placeholder="$i18n( 'neowiki-mapping-creator-name-placeholder' ).text()"
				@input="onNameInput"
				@keyup.enter="onNameEnter"
			/>
			<template #label>
				{{ $i18n( 'neowiki-mapping-creator-name-field' ).text() }}
			</template>
		</CdxField>

		<template #footer>
			<SummaryAction
				ref="summaryActionRef"
				help-text=""
				:save-button-label="$i18n( 'neowiki-mapping-creator-save' ).text()"
				:save-disabled="saving"
				@save="handleSave"
			/>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxDialog, CdxField, CdxTextInput } from '@wikimedia/codex';
import type { ValidationStatusType } from '@wikimedia/codex';
import SummaryAction from '@/components/common/SummaryAction.vue';

// The reserved projection name (RdfPageProjector::PROJECTION). The backend rejects a Mapping:Native
// save (MappingName via MappingContentHandler::validateSave, case-insensitively); this client check
// is a nicer inline error that avoids the round-trip, not the authoritative guard.
const RESERVED_NAME = 'native';

// The minimal valid empty Mapping, matching MappingContentHandler::makeEmptyContent. The user then
// edits the raw JSON on the created page — there is no mapping editor UI.
const MAPPING_SKELETON = '{"version": 1, "prefixes": {}, "schemas": {}}';

const props = defineProps<{
	open: boolean;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'created': [ name: string ];
}>();

const mappingName = ref( '' );
const nameError = ref( '' );
const nameStatus = ref<ValidationStatusType>( 'default' );
const saving = ref( false );
const summaryActionRef = ref<InstanceType<typeof SummaryAction> | null>( null );

function close(): void {
	emit( 'update:open', false );
}

// Enter in the name field saves through the SummaryAction so the currently entered summary is used,
// exactly as clicking its save button does.
function onNameEnter(): void {
	summaryActionRef.value?.submit();
}

function onDialogUpdateOpen( value: boolean ): void {
	if ( !value ) {
		close();
	}
}

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		mappingName.value = '';
		nameError.value = '';
		nameStatus.value = 'default';
		saving.value = false;
	}
} );

function onNameInput(): void {
	nameError.value = '';
	nameStatus.value = 'default';
}

function setNameError( message: string ): void {
	nameError.value = message;
	nameStatus.value = 'error';
}

async function handleSave( summary: string ): Promise<void> {
	if ( saving.value ) {
		return;
	}

	const name = mappingName.value.trim();

	if ( !name ) {
		setNameError( mw.msg( 'neowiki-mapping-creator-name-required' ) );
		return;
	}

	if ( name.toLowerCase() === RESERVED_NAME ) {
		setNameError( mw.msg( 'neowiki-mapping-creator-name-reserved' ) );
		return;
	}

	saving.value = true;

	try {
		// No content model is passed: the Mapping namespace defaults to NeoWikiMapping
		// (extension.json defaultcontentmodel), so a new page in it gets the right model.
		await new mw.Api().create(
			`Mapping:${ name }`,
			{ summary: summary || mw.msg( 'neowiki-mapping-creator-summary-default' ) },
			MAPPING_SKELETON
		);
		emit( 'created', name );
		close();
	} catch ( error ) {
		const code = typeof error === 'string' ? error : ( error as { code?: string } )?.code;

		if ( code === 'articleexists' ) {
			setNameError( mw.msg( 'neowiki-mapping-creator-name-taken' ) );
		} else {
			mw.notify(
				error instanceof Error ? error.message : String( error ),
				{
					title: mw.msg( 'neowiki-mapping-creator-error', name ),
					type: 'error'
				}
			);
		}
	} finally {
		saving.value = false;
	}
}
</script>

<style lang="less">
.ext-neowiki-mapping-creator-dialog {
	&.cdx-dialog {
		max-width: 32rem;
	}
}
</style>
