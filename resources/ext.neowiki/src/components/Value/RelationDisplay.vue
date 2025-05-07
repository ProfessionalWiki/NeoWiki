<template>
	<div>
		<div
			v-for="( value, key ) in displayedValues"
			:key="key"
		>
			<a
				v-if="value.url"
				:href="value.url"
			>
				{{ value.text }}
			</a>
			<span
				v-else
				:class="value.error ? 'error' : ''"
				:title="value.error ? value.error : ''"
			>
				{{ value.text }}
			</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { RelationProperty } from '@neo/domain/propertyTypes/Relation.ts';
import { ref, watch } from 'vue';
import { RelationValue, Relation } from '@neo/domain/Value.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectWithContext } from '@neo/domain/SubjectWithContext.ts';

interface RelationDisplayValue {
	text: string;
	url?: string;
	error?: string;
}

const props = defineProps<ValueDisplayProps<RelationProperty>>();

const subjectStore = useSubjectStore();
const displayedValues = ref<RelationDisplayValue[]>( [] );

watch( () => props.value, async ( newValue ) => {
	if ( newValue instanceof RelationValue ) {
		const promises = newValue.relations.map( async ( relation: Relation ): Promise<RelationDisplayValue> => {
			let subject: SubjectWithContext | undefined;
			try {
				subject = await subjectStore.getOrFetchSubject( relation.target ) as SubjectWithContext;
				if ( !subject ) {
					return getInvalidValueDisplay(
						relation.target.text,
						`Subject not found: ${ relation.target.text }`
					);
				}
				return getValueDisplay( subject );
			} catch ( error: unknown ) {
				return getInvalidValueDisplay(
					relation.target.text,
					`${ error instanceof Error ? error.name : 'Unknown error' }: ${ error instanceof Error ? error.message : String( error ) }`
				);
			}
		} );
		displayedValues.value = await Promise.all( promises );
	} else {
		displayedValues.value = [];
	}
}, { immediate: true } );

function getValueDisplay( subject: SubjectWithContext ): RelationDisplayValue {
	return {
		text: subject.getLabel(),
		url: mw.util.getUrl( subject.getPageIdentifiers().getPageName() )
	};
}

function getInvalidValueDisplay( text: string, error?: string ): RelationDisplayValue {
	return {
		text: text,
		error: error
	};
}

</script>
