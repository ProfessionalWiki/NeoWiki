<template>
	<div class="infobox">
		<div class="infobox-title">
			{{ subject.getLabel() }}
		</div>
		<div class="infobox-statements">
			<div
				v-for="statement in subject.getStatements() as Iterable<Statement>"
				:key="statement.propertyName.toString()"
				class="infobox-statement"
			>
				<div class="infobox-statement-property">
					{{ statement.propertyName.toString() }}
				</div>
				<div class="infobox-statement-value">
					<component
						:is="getComponentName( statement.format )"
						:statement="statement"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { PropType } from 'vue';
import { Subject } from '@neo/domain/Subject';
import { Statement } from '@neo/domain/Statement.ts';
import NumberValue from '@/components/AutomaticInfobox/Values/NumberValue.vue';
import TextValue from '@/components/AutomaticInfobox/Values/TextValue.vue';
import UrlValue from '@/components/AutomaticInfobox/Values/UrlValue.vue';
import RelationValue from '@/components/AutomaticInfobox/Values/RelationValue.vue';
import { Component } from 'vue';
import { ValueFormatRegistry } from '@neo/domain/ValueFormat';

const props = defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	valueFormatRegistry: {
		type: Object as PropType<ValueFormatRegistry>,
		required: true
	}
} );

const valueComponents = {
	NumberValue,
	TextValue,
	UrlValue,
	RelationValue
};

const getComponentName = ( format: string ): Component|null => {
	const componentName = props.valueFormatRegistry.getFormat( format ).getInfoboxValueComponentName() as keyof typeof valueComponents;
	// TODO: handle unknown value format?
	return valueComponents[ componentName ] || null;
};
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
