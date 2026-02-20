<template>
	<div class="ext-neowiki-subject-creator-container">
		<CdxButton
			class="ext-neowiki-subject-creator-trigger"
			@click="open = true"
		>
			{{ $i18n( 'neowiki-subject-creator-button-label' ).text() }}
		</CdxButton>
		<CdxDialog
			:open="open"
			class="ext-neowiki-subject-creator-dialog"
			:class="{ 'ext-neowiki-subject-creator-dialog--wide': selectedSchemaOption === 'new' && !selectedSchemaName }"
			:title="$i18n( 'neowiki-subject-creator-title' ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<div class="cdx-dialog__header__title-group">
					<h2 class="cdx-dialog__header__title">
						{{ $i18n( 'neowiki-subject-creator-title' ).text() }}
					</h2>

					<p
						v-if="headerSubtitle"
						class="cdx-dialog__header__subtitle"
					>
						{{ headerSubtitle }}
					</p>
				</div>

				<CdxButton
					class="cdx-dialog__header__close-button"
					weight="quiet"
					type="button"
					:aria-label="$i18n( 'cdx-dialog-close-button-label' ).text()"
					@click="requestClose"
				>
					<CdxIcon :icon="cdxIconClose" />
				</CdxButton>
			</template>
			<template v-if="!selectedSchemaName">
				<p>
					{{ $i18n( 'neowiki-subject-creator-schema-title' ).text() }}
				</p>

				<CdxToggleButtonGroup
					v-if="canCreateSchemas"
					v-model="selectedSchemaOption"
					class="ext-neowiki-subject-creator-schema-options"
					:buttons="toggleButtons"
				/>

				<div
					v-if="selectedSchemaOption === 'existing'"
					class="ext-neowiki-subject-creator-existing"
				>
					<SchemaLookup
						ref="schemaLookupRef"
						@select="onSchemaSelected"
					/>
				</div>

				<div
					v-if="selectedSchemaOption === 'new'"
					class="ext-neowiki-subject-creator-new"
				>
					<SchemaCreator
						ref="schemaCreatorRef"
						@change="markChanged"
					/>
				</div>
			</template>

			<template v-if="selectedSchemaName">
				<CdxField class="ext-neowiki-subject-creator-label-field">
					<CdxTextInput
						v-model="subjectLabel"
						:placeholder="$i18n( 'neowiki-subject-creator-label-placeholder' ).text()"
						@input="markChanged"
					/>
					<template #label>
						{{ $i18n( 'neowiki-subject-creator-label-field' ).text() }}
					</template>
				</CdxField>

				<SubjectEditor
					v-if="schemaStatements"
					ref="subjectEditorRef"
					:schema-statements="schemaStatements"
					:schema-properties="schemaProperties"
					@change="markChanged"
				/>
			</template>

			<template
				v-if="selectedSchemaOption === 'new' && !selectedSchemaName"
				#footer
			>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-subject-creator-create-schema' ).text()"
					:save-disabled="!hasChanged"
					@save="handleCreateSchema"
				/>
			</template>
			<template
				v-else-if="selectedSchemaName"
				#footer
			>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-subject-creator-save-with-schema', selectedSchemaName ).text()"
					:save-disabled="!hasChanged"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>

		<CloseConfirmationDialog
			:open="confirmationOpen"
			@discard="confirmClose"
			@keep-editing="cancelClose"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { CdxButton, CdxDialog, CdxField, CdxIcon, CdxTextInput, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconClose, cdxIconSearch } from '@wikimedia/codex-icons';
import type { ButtonGroupItem } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import type { SchemaCreatorExposes } from '@/components/SchemaCreator/SchemaCreator.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';

const open = ref( false );
const selectedSchemaOption = ref( 'existing' );
const selectedSchemaName = ref<string | null>( null );
const loadedSchema = ref<Schema | null>( null );
const subjectLabel = ref( '' );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );
const schemaCreatorRef = ref<SchemaCreatorExposes | null>( null );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

function close(): void {
	open.value = false;
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasChanged, close );

function onDialogUpdateOpen( value: boolean ): void {
	if ( !value ) {
		requestClose();
	}
}

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const subjectEditorRef = ref<SubjectEditorInstance | null>( null );

const headerSubtitle = computed( (): string | null => {
	if ( selectedSchemaOption.value === 'new' && !selectedSchemaName.value ) {
		return mw.msg( 'neowiki-subject-creator-creating-schema' );
	}

	if ( selectedSchemaName.value ) {
		return mw.msg( 'neowiki-schema-label', selectedSchemaName.value );
	}

	return null;
} );

const toggleButtons = [
	{
		value: 'existing',
		label: mw.msg( 'neowiki-subject-creator-existing-schema' ),
		icon: cdxIconSearch
	},
	{
		value: 'new',
		label: mw.msg( 'neowiki-subject-creator-new-schema' ),
		icon: cdxIconAdd
	}
] as ButtonGroupItem[];

onMounted( async () => {
	await checkCreatePermission();
} );

watch( selectedSchemaOption, ( newValue: string ) => {
	focusInitialInput( newValue );
} );

async function focusInitialInput( schemaOption: string ): Promise<void> {
	await nextTick();
	if ( schemaOption === 'existing' && schemaLookupRef.value ) {
		schemaLookupRef.value.focus();
	} else if ( schemaOption === 'new' && schemaCreatorRef.value ) {
		schemaCreatorRef.value.focus();
	}
}

async function onSchemaSelected( schemaName: string ): Promise<void> {
	if ( !schemaName ) {
		return;
	}

	selectedSchemaName.value = schemaName;
	subjectLabel.value = String( mw.config.get( 'wgTitle' ) ?? '' );
	markChanged();

	try {
		loadedSchema.value = await schemaStore.getOrFetchSchema( schemaName );
	} catch ( error ) {
		console.error( 'Failed to load schema:', error );
		loadedSchema.value = null;
	}
}

async function handleCreateSchema( summary: string ): Promise<void> {
	if ( !schemaCreatorRef.value ) {
		return;
	}

	const valid = await schemaCreatorRef.value.validate();

	if ( !valid ) {
		return;
	}

	const schema = schemaCreatorRef.value.getSchema();

	if ( !schema ) {
		return;
	}

	try {
		await schemaStore.saveSchema( schema, summary || undefined );
		mw.notify( mw.msg( 'neowiki-subject-creator-schema-created' ), { type: 'success' } );

		selectedSchemaName.value = schema.getName();
		loadedSchema.value = schema;
		subjectLabel.value = String( mw.config.get( 'wgTitle' ) ?? '' );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-subject-creator-error' ),
				type: 'error'
			}
		);
	}
}

const schemaProperties = computed( (): PropertyDefinitionList =>
	loadedSchema.value?.getPropertyDefinitions() ?? new PropertyDefinitionList( [] )
);

const schemaStatements = computed( (): StatementList | null => {
	if ( !loadedSchema.value ) {
		return null;
	}

	const statements: Statement[] = [];

	for ( const propDef of schemaProperties.value ) {
		statements.push(
			new Statement(
				propDef.name,
				propDef.type,
				undefined
			)
		);
	}

	return new StatementList( statements );
} );

watch( open, async ( isOpen ) => {
	if ( isOpen ) {
		await nextTick();
		focusInitialInput( selectedSchemaOption.value );
	} else {
		resetForm();
	}
} );

function resetForm(): void {
	selectedSchemaName.value = null;
	loadedSchema.value = null;
	subjectLabel.value = '';
	selectedSchemaOption.value = 'existing';
	schemaCreatorRef.value?.reset();
	resetChanged();
}

const handleSave = async ( _summary: string ): Promise<void> => {
	await nextTick();

	const label = subjectLabel.value.trim();

	if ( !label ) {
		mw.notify( mw.msg( 'neowiki-subject-creator-error' ), { type: 'error' } );
		return;
	}

	if ( !subjectEditorRef.value || !selectedSchemaName.value ) {
		return;
	}

	const updatedStatements = subjectEditorRef.value.getSubjectData();
	const statementsToSave = [ ...updatedStatements ].filter( ( statement ) => statement.hasValue() );

	try {
		await subjectStore.createMainSubject(
			mw.config.get( 'wgArticleId' ),
			label,
			selectedSchemaName.value,
			new StatementList( statementsToSave )
		);
		mw.notify( mw.msg( 'neowiki-subject-creator-success' ), { type: 'success' } );
		close();
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-subject-creator-error' ),
				type: 'error'
			}
		);
	}
};

defineExpose( { hasChanged } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-creator {
	&-dialog.cdx-dialog {
		/* Replicate the Codex default dialog header styles */
		.cdx-dialog__header {
			display: flex;
			align-items: baseline;
			justify-content: flex-end;
			box-sizing: @box-sizing-base;
			width: @size-full;
		}
	}

	&-dialog--wide.cdx-dialog {
		max-width: @size-5600;
	}

	&-schema-options.cdx-toggle-button-group {
		margin-bottom: @spacing-150;
		width: inherit;
		display: flex;
		flex-wrap: wrap;

		.cdx-toggle-button {
			flex-grow: 1;
		}
	}

	&-label-field {
		margin-top: @spacing-100;
	}

	&-new {
		.ext-neowiki-schema-creator {
			margin-inline: -@spacing-100;

			@media ( min-width: @min-width-breakpoint-desktop ) {
				margin-inline: -@spacing-150;
			}
		}
	}
}
</style>
