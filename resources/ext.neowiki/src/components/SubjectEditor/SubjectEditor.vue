<template>
	<div class="ext-neowiki-subject-editor">
		<CdxField
			v-for="( statement, index ) in props.schemaStatements"
			:key="statement.propertyName.toString()"
		>
			<component
				:is="NeoWikiServices.getComponentRegistry().getValueEditingComponent( statement.propertyType )"
				:ref="( el: any ) => { if ( el ) valueEditors[ index ] = el; }"
				:label="statement.propertyName.toString()"
				:model-value="statement.value"
				:property="props.schemaProperties.get( statement.propertyName )"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { ref, onBeforeUpdate } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Statement } from '@neo/domain/Statement.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
const props = defineProps<{
	schemaStatements: StatementList;
	schemaProperties: PropertyDefinitionList;
}>();

onBeforeUpdate( () => {
	valueEditors.value = [];
} );

const valueEditors = ref<ValueInputExposes[]>( [] );

const getSubjectData = (): StatementList => {
	const newStatements = [ ...props.schemaStatements ].map( ( statement, index ) =>
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
