<template>
	<div v-if="subjectRef !== null" class="ext-neowiki-auto-infobox">
		<div class="ext-neowiki-auto-infobox__header">
			<div class="ext-neowiki-auto-infobox__header__text">
				<div
					class="ext-neowiki-auto-infobox__title"
					role="heading"
					aria-level="2"
				>
					{{ subjectRef.getLabel() }}
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
				:subject="subjectRef as Subject"
				@update:subject="handleSubjectUpdate"
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
						:key="`${propertyDefinition.name}${subjectRef?.getStatementValue( propertyDefinition.name )}-ext-neowiki-auto-infobox`"
						:value="subjectRef?.getStatementValue( propertyDefinition.name )"
						:property="propertyDefinition"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, PropType, onMounted } from 'vue';
import { Subject } from '@neo/domain/Subject.ts';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { Schema } from '@neo/domain/Schema.ts';
import { Component } from 'vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';

const props = defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	schema: {
		type: Object as PropType<Schema>,
		required: true
	},
	canEditSubject: {
		type: Boolean,
		required: true
	}
} );

const canEditSchema = ref( false );

const subjectRef = ref( props.subject );

const schemaStore = useSchemaStore();

const handleSubjectUpdate = ( newSubject: Subject ): void => {
	subjectRef.value = newSubject;
};

const getComponent = ( propertyType: string ): Component => NeoWikiServices.getComponentRegistry().getValueDisplayComponent( propertyType );

const propertiesToDisplay = computed( (): Record<string, PropertyDefinition> => {
	if ( !subjectRef.value ) {
		console.error( 'subjectRef is null or undefined' );
		return {};
	}

	const schemaName = subjectRef.value.getSchemaName();
	const schema = schemaStore.getSchema( schemaName );

	if ( !schema ) {
		console.error( `Schema not found for name: ${ schemaName }` );
		return {};
	}

	const nonEmptyProperties = subjectRef.value.getNamesOfNonEmptyProperties();

	return schema.getPropertyDefinitions()
		.withNames( nonEmptyProperties )
		.asRecord();
} );

onMounted( async (): Promise<void> => {
	canEditSchema.value = await NeoWikiServices.getSchemaAuthorizer().canEditSchema( props.schema.getName() );
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
