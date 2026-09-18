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
import { RelationTargetUrlKey, ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { computed, inject } from 'vue';
import { Value, RelationValue, Relation } from '@/domain/Value.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { subjectPageUrl } from '@/presentation/subjectPageUrl.ts';
import { isSubjectFirst } from '@/presentation/wikiMode.ts';

interface RelationDisplayValueData {
	text: string;
	url?: string;
	error?: string;
}

const props = defineProps<ValueDisplayProps<RelationProperty>>();

const subjectStore = useSubjectStore();

// Where a relation leads, unless the host says otherwise: the page its target is stored on on a
// page-first wiki, the target Subject itself on a subject-first one (ADR 33).
const relationTargetUrl = inject( RelationTargetUrlKey, targetUrl );

function targetUrl( target: SubjectWithContext ): string {
	return isSubjectFirst() ?
		subjectPageUrl( target.getId().text ) :
		mw.util.getUrl( target.getPageIdentifiers().getPageName() );
}

// Computed, not resolved once: a target seeded after the first render then resolves.
const displayedValues = computed( () => getDisplayedValues( props.value ) );

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
		text: subjectDisplayName( subject ),
		url: relationTargetUrl( subject )
	};
}

function getInvalidValueDisplay( text: string, error?: string ): RelationDisplayValueData {
	return {
		text: text,
		error: error
	};
}

</script>
