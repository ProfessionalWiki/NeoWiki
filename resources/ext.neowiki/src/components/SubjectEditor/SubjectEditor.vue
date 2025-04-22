<template>
	<div class="ext-neowiki-subject-editor">
		<div class="ext-neowiki-subject-editor__label">
			{{ props.initialLabel }}
		</div>

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
import { ref, watchEffect } from 'vue';
import { CdxField } from '@wikimedia/codex'; // Removed CdxTextInput
import { StatementList } from '@neo/domain/StatementList.ts';
import { Value } from '@neo/domain/Value.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = defineProps<{
	initialLabel: string;
	initialStatements: StatementList;
}>();

// Only statements are stored in the formData for now
const formData = ref<Record<string, Value | undefined>>( {} );

const updateStatementValue = ( propertyName: string, newValue: Value | undefined ): void => {
	formData.value[ propertyName ] = newValue;
};

watchEffect( () => {
	const initialData: Record<string, Value | undefined> = {};

	if ( props.initialStatements ) {
		for ( const statement of props.initialStatements ) {
			const propName = statement.propertyName.toString();
			initialData[ propName ] = statement.value;
		}
	}
	formData.value = initialData;
} );

const getSubjectData = (): Record<string, Value | undefined> => formData.value;

defineExpose( {
	getSubjectData
} );

</script>

<!-- Add some basic styling for the label display -->
<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-subject-editor__label {
	margin-block-end: $spacing-150;
	font-size: $font-size-large;
	font-weight: $font-weight-bold;
}
</style>
