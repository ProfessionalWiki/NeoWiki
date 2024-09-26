<template>
	<div class="neo-type-select-dropdown">
		<ul>
			<li
				v-for="type in types"
				:key="type.value"
				@click="selectType( type.value )">
				<CdxIcon :icon="type.icon" />
				<span class="item-label">{{ type.label }}</span>
			</li>
		</ul>
	</div>
</template>

<script setup lang="ts">
import { CdxIcon } from '@wikimedia/codex';

interface TypeOption {
	value: string;
	label: string;
	icon: string;
}

const props = defineProps<{
	types: TypeOption[];
}>();

const emit = defineEmits( [ 'select' ] );

const selectType = ( value: string ): void => {
	emit( 'select', value );
};
</script>

<style lang="scss" scoped>
.neo-type-select-dropdown {
	background: #f8f9fa;
	border: 1px solid #eaecf0;
	border-radius: 8px;
	box-shadow: 0 4px 12px rgba( 0, 0, 0, 0.1 );
	overflow: hidden;

	ul {
		list-style-type: none;
		padding: 0;
		margin: 0;
		max-height: 250px;
		overflow-y: auto;

		li {
			display: flex;
			align-items: center;
			padding: 12px 16px;
			cursor: pointer;
			transition: background-color 0.2s;
			color: #54595d;

			.item-label {
				margin-left: 15px;
			}

			&:hover {
				background-color: #eaecf0;
			}

			.icon-wrapper {
				display: inline-flex;
				margin-right: 12px;
				width: 24px;
				height: 24px;

				:deep( svg ) {
					width: 100%;
					height: 100%;
					fill: #72777d;
				}
			}
		}
	}
}
</style>
