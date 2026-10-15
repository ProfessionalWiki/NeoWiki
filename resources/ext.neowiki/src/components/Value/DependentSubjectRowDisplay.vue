<template>
	<tr>
		<td
			v-for="cell in cells"
			:key="cell.property.name.toString()"
			class="ext-neowiki-dependent-subject-table__cell"
			:class="{ 'ext-neowiki-dependent-subject-table__cell--number': cell.property.type === NumberType.typeName }"
		>
			<StatementDisplay
				v-if="cell.value !== undefined"
				:value="cell.value"
				:property="cell.property"
			/>
		</td>
	</tr>
</template>

<script setup lang="ts">
import { computed, inject, provide, ref } from 'vue';
import type { Subject } from '@/domain/Subject';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Value } from '@/domain/Value';
import StatementDisplay from '@/components/Value/StatementDisplay.vue';
import { EnclosingSubjectIdsKey } from '@/components/Value/ValueDisplayContract.ts';
import { NumberType } from '@/domain/propertyTypes/Number';

const props = defineProps<{
	subject: Subject;
	columns: readonly PropertyDefinition[];
}>();

const enclosingSubjectIds = inject( EnclosingSubjectIdsKey, ref( [] ) );

provide( EnclosingSubjectIdsKey, computed( () => [ ...enclosingSubjectIds.value, props.subject.getId().text ] ) );

const cells = computed( (): { property: PropertyDefinition; value: Value | undefined }[] => {
	const shownNames = new Set( props.subject.getNamesOfNonEmptyProperties().map( ( name ) => name.toString() ) );

	return props.columns.map( ( property ) => ( {
		property,
		value: shownNames.has( property.name.toString() ) ? props.subject.getStatementValue( property.name ) : undefined
	} ) );
} );
</script>
