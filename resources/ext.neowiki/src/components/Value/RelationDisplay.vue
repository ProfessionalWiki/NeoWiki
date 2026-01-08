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
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { ref, watch } from 'vue';
import { Value, RelationValue, Relation } from '@/domain/Value.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';

interface RelationDisplayValueData {
	text: string;
	url?: string;
	error?: string;
}

const props = defineProps<ValueDisplayProps<RelationProperty>>();

const subjectStore = useSubjectStore();
const displayedValues = ref<RelationDisplayValueData[]>( [] );

watch( () => props.value, ( newValue ) => {
	displayedValues.value = getDisplayedValues( newValue );
}, { immediate: true } );

function getDisplayedValues( value: Value | undefined ): RelationDisplayValueData[] {
	if ( !( value instanceof RelationValue ) ) {
		return [];
	}

	return value.relations.map( ( relation: Relation ): RelationDisplayValueData => {
		let subject: SubjectWithContext | undefined;
		try {
			subject = subjectStore.getSubject( relation.target ) as SubjectWithContext;
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
}

function getValueDisplay( subject: SubjectWithContext ): RelationDisplayValueData {
	return {
		text: subject.getLabel(),
		url: mw.util.getUrl( subject.getPageIdentifiers().getPageName() )
	};
}

function getInvalidValueDisplay( text: string, error?: string ): RelationDisplayValueData {
	return {
		text: text,
		error: error
	};
}

</script>
