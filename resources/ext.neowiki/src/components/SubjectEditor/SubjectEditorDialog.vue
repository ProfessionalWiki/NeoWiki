<template>
	<div class="ext-neowiki-subject-editor-container">
		<CdxDialog
			:open="open"
			class="ext-neowiki-subject-editor-dialog"
			:title="$i18n( 'neowiki-subject-editor-title', props.subject.getLabel() ).text()"
			@update:open="emit( 'update:open', $event )"
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
								v-if="canEditSchema"
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
					@click="emit( 'update:open', false )"
				>
					<CdxIcon :icon="cdxIconClose" />
				</CdxButton>
			</template>

			<SubjectEditor
				v-if="schemaStatements"
				ref="subjectEditorRef"
				:schema-statements="schemaStatements"
				:schema-properties="schemaProperties"
				@change="markChanged"
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
			:open="isSchemaEditorOpen"
			:initial-schema="loadedSchema as Schema"
			:on-save="props.onSaveSchema"
			@saved="onSchemaSaved"
			@update:open="isSchemaEditorOpen = $event"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, nextTick, computed, watch } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import type { SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';

type SubjectSaveHandler = ( subject: Subject, comment: string ) => Promise<void>;

const props = defineProps<{
	subject: Subject;
	onSave: SubjectSaveHandler;
	onSaveSchema: SchemaSaveHandler;
	open: boolean;
}>();

const emit = defineEmits( [ 'update:open' ] );

const schemaStore = useSchemaStore();

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const isSchemaEditorOpen = ref( false );
const subjectEditorRef = ref<SubjectEditorInstance | null>( null );
const loadedSchema = ref<Schema | null>( null );
const { canEditSchema, checkEditPermission } = useSchemaPermissions();
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		resetChanged();
	}
} );

watch( () => props.subject, async ( newSubject ) => {
	if ( newSubject ) {
		await checkEditPermission( newSubject.getSchemaName() );
		await loadSchema();
	}
}, { immediate: true } );

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
	const editSummary = summary || mw.msg( 'neowiki-subject-editor-summary-default' );

	try {
		await props.onSave( updatedSubject, editSummary );
		mw.notify( mw.msg( 'neowiki-subject-editor-success', subjectName ), { type: 'success' } );
		emit( 'update:open', false );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-subject-editor-error', subjectName ),
				type: 'error'
			}
		);
	}
};

const onSchemaSaved = ( schema: Schema ): void => {
	loadedSchema.value = schema;
};

defineExpose( { hasChanged } );

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
