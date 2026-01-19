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
						{{ props.subject.getSchemaName() }}
						<a
							class="ext-neowiki-subject-editor-dialog__schema-link"
							href="#"
							@click.prevent="isSchemaEditorOpen = true"
						>
							{{ $i18n( 'neowiki-edit-schema' ).text() }}
						</a>
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
			v-if="loadedSchema"
			v-model:open="isSchemaEditorOpen"
			:initial-schema="loadedSchema as Schema"
			@saved="onSchemaSaved"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, nextTick, computed, onMounted } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit, cdxIconClose } from '@wikimedia/codex-icons';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';

const props = defineProps<{
	subject: Subject;
}>();

const emit = defineEmits<( e: 'update:subject', subject: Subject ) => void>();

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const open = ref( false );
const isSchemaEditorOpen = ref( false );
const subjectEditorRef = ref<SubjectEditorInstance | null>( null );
const loadedSchema = ref<Schema | null>( null );

onMounted( async () => {
	if ( props.subject ) {
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
} );

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
	try {
		await subjectStore.updateSubject( updatedSubject );
		mw.notify(
			summary || 'No edit summary provided.',
			{
				title: `Updated ${ subjectName }`,
				type: 'success'
			}
		);
		emit( 'update:subject', updatedSubject );
		open.value = false;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
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
	&__schema-link {
		.cdx-mixin-link-base();
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
