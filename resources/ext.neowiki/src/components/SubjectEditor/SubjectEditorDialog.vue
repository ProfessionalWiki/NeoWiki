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
			class="ext-neowiki-dialog"
			:title="$i18n( 'neowiki-subject-editor-title', props.subject.getLabel() ).text()"
			:use-close-button="true"
			@default="open = false"
		>
			<SubjectEditor
				v-if="schemaStatements"
				ref="subjectEditorRef"
				:schema-statements="schemaStatements"
			/>
			<div v-else>
				Loading schema... <!-- Or some other loading indicator -->
			</div>

			<!-- TODO: We should make this into a component-->
			<template #footer>
				<DialogFooter @save="handleSave" />
			</template>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import { ref, nextTick, computed, onMounted } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import DialogFooter from '@/components/common/DialogFooter.vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Subject } from '@neo/domain/Subject.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@neo/domain/Schema.ts';
import { Statement } from '@neo/domain/Statement.ts';

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
const subjectEditorRef = ref<SubjectEditorInstance | null>( null );
const loadedSchema = ref<Schema | null>( null );

onMounted( async () => {
	if ( props.subject ) {
		try {
			loadedSchema.value = await schemaStore.getOrFetchSchema( props.subject.getSchemaName() );
		} catch ( error ) {
			console.error( 'Failed to load schema:', error );
			// Optionally notify the user
			mw.notify(
				`Failed to load schema ${ props.subject.getSchemaName() }: ${ error instanceof Error ? error.message : String( error ) }`,
				{ type: 'error' }
			);
		}
	}
} );

const schemaStatements = computed( (): StatementList | null => {
	if ( !loadedSchema.value ) {
		return null; // Return null or an empty StatementList while loading/if error
	}

	const schemaProperties = loadedSchema.value.getPropertyDefinitions();
	const existingStatements = props.subject.getStatements();
	const allStatements: Statement[] = [];

	for ( const propDef of schemaProperties ) {
		// Find existing statement by iterating (assuming StatementList is iterable)
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
					propDef.name, // Use the PropertyName object directly
					propDef.type, // Use the type string directly
					undefined // Or potentially a default Value based on propDef.type?
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

</script>
