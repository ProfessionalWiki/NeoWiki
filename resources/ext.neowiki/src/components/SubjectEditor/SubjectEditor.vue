<template>
	<div class="ext-neowiki-ui ext-neowiki-subject-editor">
		<CdxField
			v-for="( statement, index ) in props.statements"
			:key="statement.propertyName.toString()"
		>
			<component
				:is="valueEditingComponent( statement )"
				:ref="( el: any ) => { if ( el ) valueEditors[ index ] = el; }"
				:label="statement.propertyName.toString()"
				:model-value="statement.value"
				:property="props.schema.getPropertyDefinition( statement.propertyName )"
				:server-violations="violationsFor( statement.propertyName.toString() )"
				@update:model-value="onValueChanged( statement )"
				@clear-server-violation="emit( 'clear-server-violation', $event )"
				@edit-relation-target="emit( 'edit-relation-target', $event )"
				@dependent-subject-change="emit( 'dependent-subject-change', $event )"
			/>
		</CdxField>
	</div>
</template>

<script lang="ts">
import { StatementList } from '@/domain/StatementList.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import type { Subject } from '@/domain/Subject.ts';

export interface SubjectEditorExposes {
	/**
	 * The statements as the fields currently hold them. A field showing text it
	 * cannot turn into a Value yields the last value it could read rather than
	 * that text — check saveBlocker() first and hold the save while it is non-null.
	 */
	getSubjectData(): StatementList;
	/**
	 * As their fields hold them, each followed by those inside it.
	 */
	getDependentSubjects(): Subject[];
	saveBlocker(): SaveBlocker | null;
}
</script>

<script setup lang="ts">
import { ref, onBeforeUpdate, inject, type Component } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { Statement } from '@/domain/Statement.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { RelationType, type RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';
import { Schema } from '@/domain/Schema.ts';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import type { SubjectId } from '@/domain/SubjectId';
import DependentSubjectsInput from '@/components/SubjectEditor/DependentSubjectsInput.vue';
import type { DependentSubjectsInputExposes } from '@/components/SubjectEditor/DependentSubjectsInput.vue';
import { DependentSubjectEditingKey } from '@/components/SubjectEditor/DependentSubjectEditing.ts';

const props = defineProps<{
	statements: StatementList;
	schema: Schema;
	serverViolations?: readonly SubjectViolation[];
}>();

const emit = defineEmits<{
	change: [];
	// Narrower than `change`: a listener can follow the edits that alter the Subject
	// graph without waking on every keystroke in a text field.
	'relation-change': [];
	// An edit inside a Dependent Subject, which changes no statement this editor holds.
	// True where it is to a relation.
	'dependent-subject-change': [ relationChanged: boolean ];
	'clear-server-violation': [ { propertyName: string; valuePartIndex: number | null } ];
	'edit-relation-target': [ SubjectId ];
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();
const dependentSubjectEditing = inject( DependentSubjectEditingKey, undefined );

function valueEditingComponent( statement: Statement ): Component {
	const property = props.schema.getPropertyDefinition( statement.propertyName );

	if (
		statement.propertyType === RelationType.typeName &&
		property?.type === RelationType.typeName &&
		dependentSubjectEditing?.targetsDependentSubjects( property as RelationProperty ) === true
	) {
		return DependentSubjectsInput;
	}

	return componentRegistry.getValueEditingComponent( statement.propertyType );
}

// Tested on the Statement's own property type, which is what selected the widget above and,
// unlike schema.getPropertyDefinition(), cannot throw on a name the Schema does not carry.
function onValueChanged( statement: Statement ): void {
	emit( 'change' );

	if ( statement.propertyType === RelationType.typeName ) {
		emit( 'relation-change' );
	}
}

function violationsFor( propertyName: string ): readonly SubjectViolation[] {
	if ( !props.serverViolations ) {
		return [];
	}
	return props.serverViolations.filter( ( v ) => v.propertyName === propertyName );
}

onBeforeUpdate( () => {
	valueEditors.value = [];
} );

const valueEditors = ref<( ValueInputExposes | DependentSubjectsInputExposes )[]>( [] );

const getSubjectData = (): StatementList => {
	const newStatements = [ ...props.statements ].map( ( statement, index ) =>
		new Statement(
			statement.propertyName,
			statement.propertyType,
			valueEditors.value[ index ]?.getCurrentValue?.()
		)
	);

	return new StatementList( newStatements );
};

const getDependentSubjects = (): Subject[] => {
	const subjects: Subject[] = [];

	valueEditors.value
		.filter( ( editor ): editor is DependentSubjectsInputExposes => editor !== undefined && 'dependentSubjects' in editor )
		.forEach( ( editor ) => subjects.push( ...editor.dependentSubjects() ) );

	return subjects;
};

/**
 * The first field showing the user text it cannot turn into a Value, so
 * getSubjectData() would silently drop it — named by its property and carrying
 * the message that field is showing. Null when every field can be read.
 * Callers hold the save while this is non-null and surface the message.
 */
const saveBlocker = (): SaveBlocker | null => {
	for ( const [ index, statement ] of [ ...props.statements ].entries() ) {
		const message = valueEditors.value[ index ]?.unparseableInputMessage?.() ?? null;

		if ( message !== null ) {
			return { propertyName: statement.propertyName.toString(), message };
		}
	}

	return null;
};

defineExpose<SubjectEditorExposes>( { getSubjectData, getDependentSubjects, saveBlocker } );

</script>
