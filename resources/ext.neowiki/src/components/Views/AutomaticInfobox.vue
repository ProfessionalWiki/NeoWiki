<template>
	<div v-if="subject !== null" class="ext-neowiki-auto-infobox">
		<div class="ext-neowiki-auto-infobox__header">
			<div class="ext-neowiki-auto-infobox__header__text">
				<div
					class="ext-neowiki-auto-infobox__title"
					role="heading"
					aria-level="2"
				>
					{{ subject.getLabel() }}
				</div>
				<div
					class="ext-neowiki-auto-infobox__schema"
					role="heading"
					aria-level="3"
				>
					{{ schema.getName() }}
				</div>
			</div>
			<SubjectEditorDialog
				v-if="canEditSubject"
				:subject="subject as Subject"
				@update:subject="onSubjectUpdated"
			/>
		</div>
		<div class="ext-neowiki-auto-infobox__content">
			<div
				v-for="( propertyDefinition, propertyName ) in propertiesToDisplay"
				:key="propertyName"
				class="ext-neowiki-auto-infobox__item"
			>
				<div class="ext-neowiki-auto-infobox__property">
					{{ propertyName }}
				</div>
				<div class="ext-neowiki-auto-infobox__value">
					<component
						:is="getComponent( propertyDefinition.type )"
						:key="`${propertyDefinition.name}${subject?.getStatementValue( propertyDefinition.name )}-ext-neowiki-auto-infobox`"
						:value="subject?.getStatementValue( propertyDefinition.name )"
						:property="propertyDefinition"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Component, computed } from 'vue';
import { Subject } from '@neo/domain/Subject.ts';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectId } from '@neo/domain/SubjectId.ts';

const props = defineProps( {
	subjectId: {
		type: SubjectId,
		required: true
	},
	canEditSubject: {
		type: Boolean,
		required: true
	}
} );

const subjectStore = useSubjectStore();

const subject = computed( () => subjectStore.getSubject( props.subjectId ) as Subject ); // TODO: handle not found
const schema = useSchemaStore().getSchema( subject.value.getSchemaName() ); // TODO: handle not found

if ( !schema ) {
	console.error( `Schema not found for name: ${ subject.value.getSchemaName() }` );
}

const onSubjectUpdated = ( newSubject: Subject ): void => {
	subjectStore.updateSubject( newSubject );
};

function getComponent( propertyType: string ): Component {
	return NeoWikiServices.getComponentRegistry().getValueDisplayComponent( propertyType );
}

const propertiesToDisplay = computed( function(): Record<string, PropertyDefinition> {
	return schema.getPropertyDefinitions()
		.withNames( subject.value.getNamesOfNonEmptyProperties() )
		.asRecord();
} );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-auto-infobox {
	margin-inline: auto;
	margin-bottom: $spacing-100;
	max-width: 20rem;
	width: 100%;
	border: $border-base;
	border-radius: $border-radius-base;
	color: $color-base;
	background-color: $background-color-base;
	line-height: $line-height-small;

	@media ( min-width: $min-width-breakpoint-tablet ) {
		clear: both;
		float: right;
		margin-inline: $spacing-100 $spacing-0;
	}

	&__header {
		padding: $spacing-100 $spacing-75;
		display: flex;
		align-items: flex-start;

		&__text {
			flex-grow: 1;
		}
	}

	&__title {
		font-size: $font-size-x-large;
		font-weight: $font-weight-bold;
	}

	&__schema {
		color: $color-subtle;
		font-size: $font-size-small;
	}

	&__content {
		padding: $spacing-75;
	}

	&__item {
		display: flex;
		align-items: flex-start;
		margin-bottom: $spacing-75;
		padding-bottom: $spacing-75;
		border-bottom: $border-subtle;
		column-gap: $spacing-150;

		&:last-child {
			border-bottom: none;
			margin-bottom: $spacing-0;
			padding-bottom: $spacing-0;
		}
	}

	&__property {
		flex: 0 0 40%;
		font-weight: $font-weight-bold;
		color: $color-emphasized;
	}

	&__value {
		flex: 0 1 60%;
		overflow-wrap: anywhere;
		word-break: break-word;
	}
}
</style>
