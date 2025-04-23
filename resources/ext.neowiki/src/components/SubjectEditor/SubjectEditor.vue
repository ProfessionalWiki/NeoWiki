<template>
	<div class="ext-neowiki-subject-editor">
		<CdxField
			v-for="statement in props.initialStatements"
			:key="statement.propertyName.toString()"
		>
			<template #label>
				{{ statement.propertyName.toString() }}
			</template>
			<component
				:is="NeoWikiServices.getComponentRegistry().getValueEditingComponent( statement.propertyType )"
				:model-value="formData[ statement.propertyName.toString() ]"
				:property="statement.propertyName"
				@update:model-value="( newValue: Value | undefined ) => updateStatementValue( statement.propertyName.toString(), newValue )"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxField } from '@wikimedia/codex'; // Removed CdxTextInput
import { StatementList } from '@neo/domain/StatementList.ts';
import { Value } from '@neo/domain/Value.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { Statement } from '@neo/domain/Statement.ts';

const props = defineProps<{
	initialStatements: StatementList;
}>();

const formData = ref<Record<string, Value | undefined>>( {} );

const updateStatementValue = ( propertyName: string, newValue: Value | undefined ): void => {
	formData.value[ propertyName ] = newValue;
};

const initialData: Record<string, Value | undefined> = {};
if ( props.initialStatements ) {
	for ( const statement of props.initialStatements ) {
		const propName = statement.propertyName.toString();
		initialData[ propName ] = statement.value;
	}
}
formData.value = initialData;

const getSubjectData = (): StatementList => {
	const newStatements: Statement[] = [];
	for ( const initialStatement of props.initialStatements ) {
		const propName = initialStatement.propertyName;
		const propType = initialStatement.propertyType;
		const currentValue = formData.value[ propName.toString() ];

		newStatements.push( new Statement( propName, propType, currentValue ) );
	}
	return new StatementList( newStatements );
};

defineExpose( {
	getSubjectData
} );

</script>
