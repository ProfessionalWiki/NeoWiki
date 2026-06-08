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
				@update:model-value="emit( 'change' )"
				@clear-server-violation="emit( 'clear-server-violation', $event )"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { ref, onBeforeUpdate } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';
import { Schema } from '@/domain/Schema.ts';
import type { SubjectViolation } from '@/domain/SubjectViolation';

const props = defineProps<{
	statements: StatementList;
	schema: Schema;
	serverViolations?: readonly SubjectViolation[];
}>();

const emit = defineEmits<{
	change: [];
	'clear-server-violation': [ { propertyName: string; valuePartIndex: number | null } ];
}>();

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

defineExpose( {
	getSubjectData
} );

</script>
