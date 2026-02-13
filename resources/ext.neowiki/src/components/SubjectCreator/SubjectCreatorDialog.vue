<template>
	<div class="ext-neowiki-subject-creator-container">
		<CdxButton
			class="ext-neowiki-subject-creator-trigger"
			@click="open = true"
		>
			{{ $i18n( 'neowiki-subject-creator-button-label' ).text() }}
		</CdxButton>
		<CdxDialog
			v-model:open="open"
			class="ext-neowiki-subject-creator-dialog"
			:class="{ 'ext-neowiki-subject-creator-dialog--wide': selectedSchemaOption === 'new' && !selectedSchemaName }"
			:title="$i18n( 'neowiki-subject-creator-title' ).text()"
			@default="open = false"
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
					@click="open = false"
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
					<CdxField
						class="ext-neowiki-subject-creator-schema-name-field"
						:status="schemaNameStatus"
						:messages="schemaNameError ? { error: schemaNameError } : {}"
					>
						<CdxTextInput
							ref="schemaNameInputRef"
							v-model="newSchemaName"
							:placeholder="$i18n( 'neowiki-subject-creator-schema-name-placeholder' ).text()"
						/>
						<template #label>
							{{ $i18n( 'neowiki-subject-creator-schema-name-field' ).text() }}
						</template>
					</CdxField>

					<SchemaEditor
						ref="schemaEditorRef"
						:initial-schema="newSchema"
					/>
				</div>
			</template>

			<template v-if="selectedSchemaName">
				<CdxField class="ext-neowiki-subject-creator-label-field">
					<CdxTextInput
						v-model="subjectLabel"
						:placeholder="$i18n( 'neowiki-subject-creator-label-placeholder' ).text()"
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
				/>
			</template>

			<template
				v-if="selectedSchemaOption === 'new' && !selectedSchemaName"
				#footer
			>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-subject-creator-create-schema' ).text()"
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
					@save="handleSave"
				/>
			</template>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { CdxButton, CdxDialog, CdxField, CdxIcon, CdxTextInput, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconClose, cdxIconSearch } from '@wikimedia/codex-icons';
import type { ButtonGroupItem, ValidationStatusType } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import type { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';

const open = ref( false );
const selectedSchemaOption = ref( 'existing' );
const selectedSchemaName = ref<string | null>( null );
const loadedSchema = ref<Schema | null>( null );
const subjectLabel = ref( '' );
const newSchemaName = ref( '' );
const schemaNameError = ref( '' );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );
const schemaEditorRef = ref<SchemaEditorExposes | null>( null );
const schemaNameInputRef = ref<InstanceType<typeof CdxTextInput> | null>( null );

const newSchema = new Schema( '', '', new PropertyDefinitionList( [] ) );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();

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

const schemaNameStatus = computed( (): ValidationStatusType =>
	schemaNameError.value ? 'error' : 'default'
);

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
	schemaNameError.value = '';
	focusInitialInput( newValue );
} );

async function focusInitialInput( schemaOption: string ): Promise<void> {
	await nextTick();
	if ( schemaOption === 'existing' && schemaLookupRef.value ) {
		schemaLookupRef.value.focus();
	} else if ( schemaOption === 'new' && schemaNameInputRef.value ) {
		schemaNameInputRef.value.focus();
	}
}

async function onSchemaSelected( schemaName: string ): Promise<void> {
	if ( !schemaName ) {
		return;
	}

	selectedSchemaName.value = schemaName;
	subjectLabel.value = String( mw.config.get( 'wgTitle' ) ?? '' );

	try {
		loadedSchema.value = await schemaStore.getOrFetchSchema( schemaName );
	} catch ( error ) {
		console.error( 'Failed to load schema:', error );
		loadedSchema.value = null;
	}
}

async function handleCreateSchema( summary: string ): Promise<void> {
	const name = newSchemaName.value.trim();

	if ( !name ) {
		schemaNameError.value = mw.msg( 'neowiki-subject-creator-schema-name-required' );
		return;
	}

	try {
		await schemaStore.getOrFetchSchema( name );
		schemaNameError.value = mw.msg( 'neowiki-subject-creator-schema-name-taken' );
		return;
	} catch {
		// TODO: Distinguish between schema not existing, and other errors during retrieval.
		// Schema not found -- name is available
	}

	const propertyDefinitions = schemaEditorRef.value ?
		schemaEditorRef.value.getSchema().getPropertyDefinitions() :
		new PropertyDefinitionList( [] );

	const schema = new Schema( name, '', propertyDefinitions );

	try {
		await schemaStore.saveSchema( schema, summary || undefined );
		mw.notify( mw.msg( 'neowiki-subject-creator-schema-created' ), { type: 'success' } );

		selectedSchemaName.value = name;
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
	newSchemaName.value = '';
	schemaNameError.value = '';
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
		open.value = false;
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

	&-schema-name-field {
		margin-bottom: @spacing-100;
	}

	&-label-field {
		margin-top: @spacing-100;
	}

	&-new {
		.ext-neowiki-schema-editor {
			margin-inline: -@spacing-100;

			@media ( min-width: @min-width-breakpoint-desktop ) {
				margin-inline: -@spacing-150;
			}
		}
	}
}
</style>
