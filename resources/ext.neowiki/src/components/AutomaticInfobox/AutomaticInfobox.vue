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
						:is="valueFormatComponentRegistry.getComponent( statement.format ).getInfoboxValueComponent()"
						:value="statement.value"
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
import { ValueFormatComponentRegistry } from '@/presentation/ValueFormatComponentRegistry.ts';

defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	valueFormatComponentRegistry: {
		type: Object as PropType<ValueFormatComponentRegistry>,
		required: true
	}
} );
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
