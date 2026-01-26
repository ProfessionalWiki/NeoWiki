<template>
	<div class="ext-neowiki-subject-editor-container">
		<CdxButton
			weight="quiet"
			:aria-label="$i18n( 'neowiki-infobox-edit-link' ).text()"
			@click="open = true"
		>
			<CdxIcon :icon="cdxIconEdit" />
		</CdxButton>
		<CdxDialog
			v-model:open="open"
			class="ext-neowiki-subject-editor-dialog"
			:title="$i18n( 'neowiki-subject-editor-title', props.subject.getLabel() ).text()"
			@default="open = false"
		>
			<template #header>
				<div class="cdx-dialog__header__title-group">
					<h2 class="cdx-dialog__header__title">
						{{ $i18n( 'neowiki-subject-editor-title', props.subject.getLabel() ).text() }}
					</h2>

					<p class="cdx-dialog__header__subtitle">
						<I18nSlot
							message-key="neowiki-schema-label"
							class="ext-neowiki-subject-editor-dialog-schema"
							text-class="ext-neowiki-subject-editor-dialog-schema__label"
						>
							<a
								v-if="canEditSchema && props.onSaveSchema"
								class="ext-neowiki-subject-editor-dialog-schema__link"
								href="#"
								@click.prevent="isSchemaEditorOpen = true"
							>
								{{ props.subject.getSchemaName() }}
							</a>
							<span
								v-else
								class="ext-neowiki-subject-editor-dialog-schema__name"
							>
								{{ props.subject.getSchemaName() }}
							</span>
						</I18nSlot>
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

			<SubjectEditor
				v-if="schemaStatements"
				ref="subjectEditorRef"
				:schema-statements="schemaStatements"
				:schema-properties="schemaProperties"
			/>
			<div v-else>
				Loading schema... <!-- Or some other loading indicator -->
			</div>

			<!-- TODO: We should make this into a component-->
			<template #footer>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-subject-editor-save' ).text()"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>

		<SchemaEditorDialog
			v-if="loadedSchema && props.onSaveSchema"
			v-model:open="isSchemaEditorOpen"
			:initial-schema="loadedSchema as Schema"
			:on-save="props.onSaveSchema"
			@saved="onSchemaSaved"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, nextTick, computed, onMounted } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit, cdxIconClose } from '@wikimedia/codex-icons';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

type SubjectSaveHandler = ( subject: Subject, comment: string ) => Promise<void>;
type SchemaSaveHandler = ( schema: Schema, comment: string ) => Promise<void>;

const props = defineProps<{
	subject: Subject;
	onSave: SubjectSaveHandler;
	onSaveSchema?: SchemaSaveHandler;
}>();

const schemaStore = useSchemaStore();

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const open = ref( false );
const isSchemaEditorOpen = ref( false );
const subjectEditorRef = ref<SubjectEditorInstance | null>( null );
const loadedSchema = ref<Schema | null>( null );
const canEditSchema = ref( false );

onMounted( async () => {
	if ( props.subject ) {
		await loadSchemaPermissions();
		await loadSchema();
	}
} );

async function loadSchemaPermissions(): Promise<void> {
	try {
		canEditSchema.value = await NeoWikiServices.getSchemaAuthorizer().canEditSchema( props.subject.getSchemaName() );
	} catch ( error ) {
		console.error( 'Failed to check schema permissions:', error );
		canEditSchema.value = false;
	}
}

async function loadSchema(): Promise<void> {
	try {
		loadedSchema.value = await schemaStore.getOrFetchSchema( props.subject.getSchemaName() );
	} catch ( error ) {
		console.error( 'Failed to load schema:', error );
		mw.notify(
			`Failed to load schema ${ props.subject.getSchemaName() }: ${ error instanceof Error ? error.message : String( error ) }`,
			{ type: 'error' }
		);
	}
}

const schemaProperties = computed( (): PropertyDefinitionList =>
	loadedSchema.value?.getPropertyDefinitions() ?? new PropertyDefinitionList( [] )
);

const schemaStatements = computed( (): StatementList | null => {
	if ( !schemaProperties.value ) {
		return null;
	}

	const existingStatements = props.subject.getStatements();
	const allStatements: Statement[] = [];

	for ( const propDef of schemaProperties.value ) {
		let existingStatement: Statement | undefined;
		for ( const stmt of existingStatements ) {
			if ( stmt.propertyName.toString() === propDef.name.toString() ) {
				existingStatement = stmt;
				break;
			}
		}

		if ( existingStatement ) {
			allStatements.push( existingStatement );
		} else {
			// Create a new empty statement based on the schema definition
			allStatements.push(
				new Statement(
					propDef.name,
					propDef.type,
					undefined
				)
			);
		}
	}

	return new StatementList( allStatements );
} );

const handleSave = async ( summary: string ): Promise<void> => {
	await nextTick();

	if ( !subjectEditorRef.value ) {
		return;
	}

	const updatedStatements = subjectEditorRef.value.getSubjectData();
	// Filter out statements that don't have a value set.
	const statementsToSave = [ ...updatedStatements ].filter( ( statement ) => statement.hasValue() );

	console.log( 'statementsToSave', statementsToSave );
	const updatedSubject = props.subject.withStatements( new StatementList( statementsToSave ) );
	const subjectName = updatedSubject.getLabel();
	const editSummary = summary || 'Update subject via NeoWiki UI'; // TODO: i18n

	try {
		await props.onSave( updatedSubject, editSummary );
		// TODO: i18n
		mw.notify( `Updated ${ subjectName }`, { type: 'success' } );
		open.value = false;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				// TODO: i18n
				title: `Failed to update ${ subjectName }.`,
				type: 'error'
			}
		);
	}
};

const onSchemaSaved = ( schema: Schema ): void => {
	loadedSchema.value = schema;
};

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@wikimedia/codex/mixins/link.less';

.ext-neowiki-subject-editor-dialog {
	&-schema {
		&__link {
			.cdx-mixin-link-base();
		}

		&__name {
			color: @color-base;
		}
	}

	/* Replicate the Codex default dialog header styles */
	.cdx-dialog__header {
		display: flex;
		align-items: baseline;
		justify-content: flex-end;
		box-sizing: @box-sizing-base;
		width: @size-full;
	}
}
</style>
