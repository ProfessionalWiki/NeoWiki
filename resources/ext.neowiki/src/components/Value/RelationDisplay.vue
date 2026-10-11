<template>
	<div>
		<div
			v-for="( value, key ) in displayedValues"
			:key="key"
		>
			<!-- The class MediaWiki gives a link to a page nobody has written, so the skin styles it as one. -->
			<a
				v-if="value.notFoundTarget !== undefined"
				class="new"
				:href="value.url"
				:title="value.title"
				@click="createNotFoundTarget( $event, value.notFoundTarget )"
			>
				{{ value.text }}
			</a>
			<a
				v-else-if="value.url"
				:href="value.url"
			>
				{{ value.text }}
			</a>
			<!-- A target not read, or whose read failed, which may well exist. -->
			<span
				v-else
				class="error"
			>
				{{ value.text }}
			</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { RelationTargetCreationKey, RelationTargetUrlKey, ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { localTargetSchemaNames, RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { computed, inject } from 'vue';
import { Value, RelationValue, Relation } from '@/domain/Value.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { SubjectId } from '@/domain/SubjectId.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { subjectLinkUrl } from '@/presentation/subjectLinks.ts';
import { subjectPageUrl } from '@/presentation/subjectPageUrl.ts';
import { isPlainLeftClick } from '@/presentation/plainLeftClick.ts';

interface RelationDisplayValueData {
	text: string;
	url?: string;
	title?: string;
	notFoundTarget?: SubjectId;
}

const props = defineProps<ValueDisplayProps<RelationProperty>>();

const subjectStore = useSubjectStore();

// Where a relation leads: to the target as a View links to one, unless the host says otherwise.
const relationTargetUrl = inject( RelationTargetUrlKey, subjectLinkUrl );

const targetCreationAllowed = inject( RelationTargetCreationKey, null );

// Computed, not resolved once: a target seeded after the first render then resolves.
const displayedValues = computed( () => getDisplayedValues( props.value ) );

function getDisplayedValues( value: Value | undefined ): RelationDisplayValueData[] {
	if ( !( value instanceof RelationValue ) ) {
		return [];
	}

	return value.relations.map( ( relation: Relation ): RelationDisplayValueData => {
		const subject = subjectStore.findSubject( relation.target );

		if ( subject !== undefined ) {
			return getValueDisplay( subject );
		}

		return subjectStore.wasNotFound( relation.target ) ?
			getNotFoundDisplay( relation.target ) :
			{ text: relation.target.text };
	} );
}

function getValueDisplay( subject: Subject ): RelationDisplayValueData {
	return {
		text: subjectDisplayName( subject ),
		url: relationTargetUrl( subject )
	};
}

function getNotFoundDisplay( target: SubjectId ): RelationDisplayValueData {
	return {
		text: target.text,
		url: subjectPageUrl( target.text ),
		title: mw.message( 'neowiki-relation-target-not-found-title', target.text ).text(),
		notFoundTarget: target
	};
}

/**
 * Opens the Subject creator in place of following a red link. A click it does not take leaves the link to
 * the browser, so the target's Special:Subject page stays reachable.
 */
function createNotFoundTarget( event: MouseEvent, target: SubjectId ): void {
	const schemaNames = localTargetSchemaNames( props.property );

	if ( targetCreationAllowed?.value !== true || !target.isLocal() || schemaNames.length === 0 || !isPlainLeftClick( event ) ) {
		return;
	}

	event.preventDefault();
	subjectStore.openSubjectCreatorFor( target, schemaNames );
}

</script>
