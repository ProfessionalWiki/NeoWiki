<template>
	<div class="infobox">
		<div class="infobox-title">
			{{ subject.getLabel() }}
		</div>
		<div class="infobox-statements">
			<div class="infobox-statement">
				<div class="infobox-statement-property">
					{{ $i18n( 'neowiki-infobox-type' ).text() }}
				</div>
				<div class="infobox-statement-value">
					{{ schema.getName() }}
				</div>
			</div>
			<div
				v-for="( propertyDefinition, propertyName ) in propertiesToDisplay"
				:key="propertyName"
				class="infobox-statement"
			>
				<div class="infobox-statement-property">
					{{ propertyName }}
				</div>
				<div class="infobox-statement-value">
					<component
						:is="getComponent( propertyDefinition.format )"
						:value="subject.getStatementValue( propertyDefinition.name )"
						:property="propertyDefinition"
					/>
				</div>
			</div>
			<!-- TODO: statements not in schema -->
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, PropType } from 'vue';
import { Subject } from '@neo/domain/Subject';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { ValueFormatComponentRegistry } from '@/presentation/ValueFormatComponentRegistry';
import { Schema } from '@neo/domain/Schema';
import { Component } from 'vue';

const props = defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	schema: {
		type: Object as PropType<Schema>,
		required: true
	},
	valueFormatComponentRegistry: {
		type: Object as PropType<ValueFormatComponentRegistry>,
		required: true
	}
} );

const getComponent = ( formatName: string ): Component => props.valueFormatComponentRegistry.getComponent( formatName ).getInfoboxValueComponent();

const propertiesToDisplay = computed( (): Record<string, PropertyDefinition> => props.schema.getPropertyDefinitions()
	.withNames( props.subject.getNamesOfNonEmptyProperties() )
	.asRecord() );
</script>

<style scoped lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.infobox {
	border: $border-base;
	max-width: 300px;
}

.infobox-title {
	text-align: center;
	font-weight: bold;
	padding: 5px;
}

.infobox-statement {
	display: flex;
	padding: 5px;
}

.infobox-statement-property {
	font-weight: bold;
	margin-right: 5px;
}

.infobox-statement-value {
	flex: 1;
}

a {
	color: $color-progressive;
	text-decoration: none;

	&:hover {
		text-decoration: underline;
	}
}
</style>
