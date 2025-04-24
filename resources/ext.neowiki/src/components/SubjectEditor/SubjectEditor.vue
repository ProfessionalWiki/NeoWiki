<template>
	<div class="ext-neowiki-subject-editor">
		<CdxField
			v-for="( statement, index ) in props.initialStatements"
			:key="statement.propertyName.toString()"
		>
			<template #label>
				{{ statement.propertyName.toString() }}
			</template>
			<component
				:is="NeoWikiServices.getComponentRegistry().getValueEditingComponent( statement.propertyType )"
				:ref="( el: any ) => { if ( el ) valueEditors[ index ] = el; }"
				:model-value="statement.value"
				:property="statement.propertyName"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { ref, onBeforeUpdate } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Statement } from '@neo/domain/Statement.ts';
import { Value } from '@neo/domain/Value.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

interface ValueEditorComponent {
	getCurrentValue: () => Value | undefined;
}

const props = defineProps<{
	initialStatements: StatementList;
}>();

onBeforeUpdate( () => {
	valueEditors.value = [];
} );

const valueEditors = ref<ValueEditorComponent[]>( [] );

const getSubjectData = (): StatementList => {
	const newStatements = [ ...props.initialStatements ].map( ( statement, index ) =>
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
