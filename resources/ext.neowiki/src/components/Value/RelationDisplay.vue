<template>
	<div>
		<DependentSubjectTableDisplay
			v-for="table in tables"
			:key="table.schema.getName()"
			:schema="table.schema"
			:subjects="table.subjects"
			:property-name="props.property.name.toString()"
			:shows-schema-name="showsSchemaNames"
		/>
		<template v-for="( value, key ) in valuesOneByOne">
			<DependentSubjectDisplay
				v-if="value.dependentSubject !== undefined"
				:key="key + '-inside'"
				:subject="value.dependentSubject"
				:shows-schema-name="showsSchemaNames"
			/>
			<div
				v-else
				:key="key + '-linked'"
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
		</template>
	</div>
</template>

<script setup lang="ts">
import {
	EnclosingSubjectIdsKey,
	RelationTargetCreationKey,
	RelationTargetUrlKey,
	ValueDisplayProps
} from '@/components/Value/ValueDisplayContract.ts';
import { localTargetSchemaNames, RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { computed, inject, ref } from 'vue';
import { Value, RelationValue, Relation } from '@/domain/Value.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import DependentSubjectDisplay from '@/components/Value/DependentSubjectDisplay.vue';
import DependentSubjectTableDisplay from '@/components/Value/DependentSubjectTableDisplay.vue';
import { isDependentSubject } from '@/domain/dependentSubjects.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';
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
	dependentSubject?: Subject;
}

interface DependentSubjectTable {
	schema: Schema;
	subjects: Subject[];
}

const props = defineProps<ValueDisplayProps<RelationProperty>>();

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

const enclosingSubjectIds = inject( EnclosingSubjectIdsKey, ref( [] ) );

const showsSchemaNames = computed( (): boolean => localTargetSchemaNames( props.property ).length > 1 );

// Where a relation leads: to the target as a View links to one, unless the host says otherwise.
const relationTargetUrl = inject( RelationTargetUrlKey, subjectLinkUrl );

const targetCreationAllowed = inject( RelationTargetCreationKey, null );

// Computed, not resolved once: a target seeded after the first render then resolves.
const displayedValues = computed( () => getDisplayedValues( props.value ) );

// By the property's cardinality rather than by how many values it holds, so a second value keeps the format.
const tables = computed( (): DependentSubjectTable[] =>
	props.property.multiple === true ? tablesOf( displayedValues.value ) : [] );

const valuesOneByOne = computed( (): RelationDisplayValueData[] => props.property.multiple === true ?
	displayedValues.value.filter( ( value ) => value.dependentSubject === undefined ) :
	displayedValues.value );

function getDisplayedValues( value: Value | undefined ): RelationDisplayValueData[] {
	if ( !( value instanceof RelationValue ) ) {
		return [];
	}

	return value.relations.map( ( relation: Relation ): RelationDisplayValueData => {
		const subject = subjectStore.findSubject( relation.target );

		if ( subject !== undefined ) {
			return isShownInside( subject ) ? { text: '', dependentSubject: subject } : getValueDisplay( subject );
		}

		return subjectStore.wasNotFound( relation.target ) ?
			getNotFoundDisplay( relation.target ) :
			{ text: relation.target.text };
	} );
}

/**
 * A Dependent Subject whose Schema is not at hand, such as one that failed to load, is linked instead.
 */
function isShownInside( subject: Subject ): boolean {
	return isDependentSubject( subject, schemaStore.schemas ) && !enclosingSubjectIds.value.includes( subject.getId().text );
}

/**
 * One table per Schema, in the order of the property's target Schemas, each Schema's rows in the relation's order.
 */
function tablesOf( values: RelationDisplayValueData[] ): DependentSubjectTable[] {
	const subjectsBySchema = new Map<string, Subject[]>();

	for ( const { dependentSubject } of values ) {
		if ( dependentSubject !== undefined ) {
			const schemaName = dependentSubject.getSchemaName();
			subjectsBySchema.set( schemaName, [ ...( subjectsBySchema.get( schemaName ) ?? [] ), dependentSubject ] );
		}
	}

	const targetSchemaNames = localTargetSchemaNames( props.property );
	const position = ( schemaName: string ): number => {
		const index = targetSchemaNames.indexOf( schemaName );
		return index === -1 ? targetSchemaNames.length : index;
	};

	return [ ...subjectsBySchema ]
		.sort( ( [ first ], [ second ] ) => position( first ) - position( second ) )
		.map( ( [ schemaName, subjects ] ) => ( { schema: schemaStore.schemas.get( schemaName ) as Schema, subjects } ) );
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
