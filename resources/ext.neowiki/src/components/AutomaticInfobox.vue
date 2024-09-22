<template>
	<div class="infobox">
		<div class="infobox-title">
			{{ subject.getLabel() }}
		</div>
		<div class="infobox-statements">
			<div
				v-for="statement in subject.getStatements()"
				:key="statement.propertyName.toString()"
				class="infobox-statement"
			>
				<div class="infobox-statement-property">
					{{ statement.propertyName.toString() }}
				</div>
				<div class="infobox-statement-value">
					<template v-if="statement.value">
						<template v-if="statement.format === TextFormat.formatName">
							{{ ( statement.value as StringValue ).strings.join( ', ' ) }}
						</template>
						<template v-else-if="statement.format === NumberFormat.formatName">
							{{ ( statement.value as NumberValue ).number }}
						</template>
						<template v-else-if="statement.format === UrlFormat.formatName">
							<div v-for="( url, key ) in ( statement.value as StringValue ).strings" :key="key">
								<a :href="url">
									{{ url }}
								</a>
							</div>
						</template>
						<template v-else-if="statement.format === RelationFormat.formatName">
							<!-- TODO -->
						</template>
					</template>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { PropType } from 'vue';
import { Subject } from '@neo/domain/Subject';
import { TextFormat } from '@neo/domain/valueFormats/Text';
import { NumberFormat } from '@neo/domain/valueFormats/Number';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import { RelationFormat } from '@neo/domain/valueFormats/Relation';
import { StringValue, NumberValue } from '@neo/domain/Value';

defineProps( {
	subject: {
		type: Object as PropType<Subject>,
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
