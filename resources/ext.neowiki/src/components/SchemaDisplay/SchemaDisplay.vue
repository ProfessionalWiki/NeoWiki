<template>
	<div class="ext-neowiki-schema-display">
		<p
			v-if="schema.getDescription()"
			class="ext-neowiki-schema-display__description"
		>
			{{ schema.getDescription() }}
		</p>

		<table
			v-if="hasProperties"
			class="ext-neowiki-schema-display__table"
		>
			<thead>
				<tr>
					<th>{{ $i18n( 'neowiki-schema-display-property-name' ).text() }}</th>
					<th>{{ $i18n( 'neowiki-schema-display-property-type' ).text() }}</th>
					<th>{{ $i18n( 'neowiki-schema-display-property-required' ).text() }}</th>
					<th>{{ $i18n( 'neowiki-schema-display-property-default' ).text() }}</th>
					<th>{{ $i18n( 'neowiki-schema-display-property-description' ).text() }}</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="property in properties"
					:key="property.name.toString()"
				>
					<td>{{ property.name.toString() }}</td>
					<td>
						<span class="ext-neowiki-schema-display__type-cell">
							<CdxIcon
								:icon="getIcon( property.type )"
								size="small"
							/>
							{{ getTypeLabel( property.type ) }}
						</span>
					</td>
					<td>
						{{ property.required ?
							$i18n( 'neowiki-schema-display-required-yes' ).text() :
							$i18n( 'neowiki-schema-display-required-no' ).text()
						}}
					</td>
					<td>
						<component
							:is="componentRegistry.getValueDisplayComponent( property.type )"
							v-if="property.default !== undefined"
							:value="property.default"
							:property="property"
						/>
					</td>
					<td>{{ property.description }}</td>
				</tr>
			</tbody>
		</table>

		<p
			v-else
			class="ext-neowiki-schema-display__empty"
		>
			{{ $i18n( 'neowiki-schema-display-no-properties' ).text() }}
		</p>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Schema } from '@/domain/Schema.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { CdxIcon } from '@wikimedia/codex';
import type { Icon } from '@wikimedia/codex-icons';

const props = defineProps( {
	schema: {
		type: Schema,
		required: true
	}
} );

const componentRegistry = NeoWikiServices.getComponentRegistry();

const properties = computed( () => [ ...props.schema.getPropertyDefinitions() ] );
const hasProperties = computed( () => properties.value.length > 0 );

function getIcon( propertyType: string ): Icon {
	return componentRegistry.getIcon( propertyType );
}

function getTypeLabel( propertyType: string ): string {
	return mw.msg( componentRegistry.getLabel( propertyType ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-display {
	max-width: 48rem;

	&__description {
		color: @color-subtle;
		margin-bottom: @spacing-100;
	}

	&__table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		border: @border-base;
		border-radius: @border-radius-base;
		line-height: @line-height-small;

		th,
		td {
			padding: @spacing-50 @spacing-75;
			text-align: left;
			border-bottom: @border-subtle;
		}

		th {
			font-weight: @font-weight-bold;
			color: @color-emphasized;
			background-color: @background-color-interactive-subtle;
		}

		tr:last-child td {
			border-bottom: none;
		}
	}

	&__type-cell {
		display: inline-flex;
		align-items: center;
		gap: @spacing-25;
	}

	&__empty {
		color: @color-subtle;
	}
}
</style>
