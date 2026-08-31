<template>
	<div class="ext-neowiki-subject-editor">
		<CdxField
			v-for="( statement, index ) in props.statements"
			:key="statement.propertyName.toString()"
		>
			<component
				:is="NeoWikiServices.getComponentRegistry().getValueEditingComponent( statement.propertyType )"
				:ref="( el: any ) => { if ( el ) valueEditors[ index ] = el; }"
				:label="statement.propertyName.toString()"
				:model-value="statement.value"
				:property="props.schema.getPropertyDefinition( statement.propertyName )"
				:server-violations="violationsFor( statement.propertyName.toString() )"
				@update:model-value="onValueChanged( statement )"
				@clear-server-violation="emit( 'clear-server-violation', $event )"
				@edit-relation-target="emit( 'edit-relation-target', $event )"
			/>
		</CdxField>
	</div>
</template>

<script lang="ts">
import { StatementList } from '@/domain/StatementList.ts';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';

export interface SubjectEditorExposes {
	/**
	 * The statements as the fields currently hold them. A field showing text it
	 * cannot turn into a Value yields a statement with no value and that text is
	 * lost — check unparseableInput() first and hold the save while it is non-null.
	 */
	getSubjectData(): StatementList;
	unparseableInput(): UnparseableInput | null;
}
</script>

<script setup lang="ts">
import { ref, onBeforeUpdate } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { Statement } from '@/domain/Statement.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';
import { Schema } from '@/domain/Schema.ts';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import type { SubjectId } from '@/domain/SubjectId';

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
	'clear-server-violation': [ { propertyName: string; valuePartIndex: number | null } ];
	'edit-relation-target': [ SubjectId ];
}>();

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

const valueEditors = ref<ValueInputExposes[]>( [] );

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

/**
 * The first field showing the user text it cannot turn into a Value, so
 * getSubjectData() would silently drop it — named by its property and carrying
 * the message that field is showing. Null when every field can be read.
 * Callers hold the save while this is non-null and surface the message.
 */
const unparseableInput = (): UnparseableInput | null => {
	for ( const [ index, statement ] of [ ...props.statements ].entries() ) {
		const message = valueEditors.value[ index ]?.unparseableInputMessage?.() ?? null;

		if ( message !== null ) {
			return { propertyName: statement.propertyName.toString(), message };
		}
	}

	return null;
};

defineExpose<SubjectEditorExposes>( { getSubjectData, unparseableInput } );

</script>
