<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import DelegatedGroupSelect from './DelegatedGroupSelect.vue'
import { CLASS_NAME_ADMIN_DELEGATION } from '../utils/constants.js'
</script>

<template>
	<DelegatedGroupSelect :delegationClass="CLASS_NAME_ADMIN_DELEGATION" />
</template>
