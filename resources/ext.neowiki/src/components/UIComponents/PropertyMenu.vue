<template>
	<CdxField
		:status="validationStatus"
		:messages="validationMessages"
		:required="required"
		class="neo-text-field"
		:class="{ 'neo-text-field--success': validationStatus === 'success' }"
	>
		<div class="neo-text-field__wrapper">
			<CdxTextInput
				v-model="inputValue"
				input-type="text"
				:class="{ 'cdx-text-input--status-success': validationStatus === 'success' }"
				@input="validateInput"
			/>
			<div class="neo-text-field__end-icon">
				<CdxMenuButton
					:selected="null"
					:menu-items="menuItems"
					class="neo-text-field__menu-button"
					@update:selected="onMenuSelect"
				>
					<CdxIcon :icon="cdxIconMenu" />
				</CdxMenuButton>
			</div>
		</div>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
	CdxField,
	CdxTextInput,
	CdxMenuButton,
	ValidationStatusType,
	MenuButtonItemData,
	CdxIcon
} from '@wikimedia/codex';
import { cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import { cdxIconMenu } from '@/assets/CustomIcons';

const props = defineProps( {
	modelValue: {
		type: String,
		required: true
	},
	required: {
		type: Boolean,
		default: false
	}
} );

const emit = defineEmits( [ 'update:modelValue', 'validation', 'edit', 'delete' ] );

const inputValue = ref( props.modelValue );
const validationStatus = ref<ValidationStatusType>( 'default' );
interface ValidationMessages {
	[key: string]: string;
}

const validationMessages = ref<ValidationMessages>( {} );

const validateInput = ( event: Event ): void => {
	const value = ( event.target as HTMLInputElement ).value;
	emit( 'update:modelValue', value );

	const messages: { [key: string]: string } = {};

	if ( props.required && !value ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	}

	if ( Object.keys( messages ).length > 0 ) {
		validationStatus.value = 'error';
	} else {
		validationStatus.value = 'default';
	}

	validationMessages.value = messages;
	emit( 'validation', Object.keys( messages ).length === 0 );
};

const menuItems = computed<MenuButtonItemData[]>( () => [
	{
		value: 'edit',
		label: 'Edit Property',
		icon: cdxIconEdit
	},
	{
		value: 'delete',
		label: 'Delete Property',
		icon: cdxIconTrash,
		action: 'destructive'
	}
] );
const onMenuSelect = ( value: string ): void => {
	if ( value === 'edit' ) {
		emit( 'edit' );
	} else if ( value === 'delete' ) {
		emit( 'delete' );
	}
};

watch( validationMessages, ( newMessages ) => {
	emit( 'validation', Object.keys( newMessages ).length === 0 );
} );

watch( () => props.modelValue, ( newValue ) => {
	inputValue.value = newValue;
} );
</script>

<style lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.neo-text-field__wrapper {
	position: relative;
	display: flex;

	.cdx-menu {
		top: -1px !important;
		left: -150px !important;
		max-width: 150px !important;

		.cdx-menu-item__text__label {
			bdi {
				font-size: small !important;
			}
		}
	}

	.cdx-menu-item {
		.cdx-icon {
			svg {
				height: 15px !important;
				width: 15px !important;
			}
		}

		&:nth-child( 1 ) {
			.cdx-icon svg {
				fill: #54595d !important;
			}
		}
	}
}

.neo-text-field__end-icon {
	position: absolute;
	right: 0;
	top: 50%;
	transform: translateY( -50% );
	cursor: pointer;
	z-index: 1;
	padding: 1px;
	background-color: #c8ccd147;
}
</style>
