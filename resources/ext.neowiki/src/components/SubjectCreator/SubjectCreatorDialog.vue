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
			:title="$i18n( 'neowiki-subject-creator-title' ).text()"
			:use-close-button="true"
			@default="open = false"
		>
			<template v-if="!selectedSchemaName">
				<p>
					{{ $i18n( 'neowiki-subject-creator-schema-title' ).text() }}
				</p>

				<CdxToggleButtonGroup
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

				<div v-if="selectedSchemaOption === 'new'">
					TODO: New schema UI
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
				v-if="selectedSchemaName"
				#footer
			>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-subject-creator-save' ).text()"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { CdxButton, CdxDialog, CdxField, CdxTextInput, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconSearch, cdxIconAdd } from '@wikimedia/codex-icons';
import type { ButtonGroupItem } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';

const open = ref( false );
const selectedSchemaOption = ref( 'existing' );
const selectedSchemaName = ref<string | null>( null );
const loadedSchema = ref<Schema | null>( null );
const subjectLabel = ref( '' );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const subjectEditorRef = ref<SubjectEditorInstance | null>( null );

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

onMounted( () => {
	focusSchemaLookup( selectedSchemaOption.value );
} );

watch( selectedSchemaOption, focusSchemaLookup );

async function focusSchemaLookup( newValue: string ): Promise<void> {
	await nextTick();
	if ( newValue === 'existing' && schemaLookupRef.value ) {
		schemaLookupRef.value.focus();
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

watch( open, ( isOpen ) => {
	if ( !isOpen ) {
		resetForm();
	}
} );

function resetForm(): void {
	selectedSchemaName.value = null;
	loadedSchema.value = null;
	subjectLabel.value = '';
	selectedSchemaOption.value = 'existing';
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
	&-schema-options.cdx-toggle-button-group {
		width: inherit;
		display: flex;
		flex-wrap: wrap;

		.cdx-toggle-button {
			flex-grow: 1;
		}
	}

	&-existing {
		margin-top: @spacing-100;
	}

	&-label-field {
		margin-top: @spacing-100;
	}
}
</style>
