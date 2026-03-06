<script setup>
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-surface-900">
            <nav class="border-b border-border bg-surface-800">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-12 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="text-sm font-bold tracking-wide text-white uppercase">
                                    SkyblockHub
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    :href="route('bazaar')"
                                    :active="route().current('bazaar')"
                                >
                                    Bazaar
                                </NavLink>
                                <NavLink
                                    :href="route('profile-stats')"
                                    :active="route().current('profile-stats')"
                                >
                                    Profile Stats
                                </NavLink>
                                <NavLink
                                    :href="route('dungeon-party')"
                                    :active="route().current('dungeon-party')"
                                >
                                    Party Finder
                                </NavLink>
                                <NavLink
                                    :href="route('portfolio')"
                                    :active="route().current('portfolio')"
                                >
                                    Portfolio
                                </NavLink>
                                <NavLink
                                    :href="route('crafting')"
                                    :active="route().current('crafting')"
                                >
                                    Crafting
                                </NavLink>
                                <NavLink
                                    :href="route('bin-sniper')"
                                    :active="route().current('bin-sniper')"
                                >
                                    BIN Sniper
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48" content-classes="py-1 bg-surface-700 border border-border">
                                    <template #trigger>
                                        <span class="inline-flex">
                                            <button
                                                type="button"
                                                class="inline-flex items-center border border-transparent px-3 py-1.5 text-xs font-medium text-neutral hover:text-white focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <svg class="-me-0.5 ms-1.5 h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 text-neutral hover:text-white focus:outline-none"
                            >
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('bazaar')" :active="route().current('bazaar')">
                            Bazaar
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('profile-stats')" :active="route().current('profile-stats')">
                            Profile Stats
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('dungeon-party')" :active="route().current('dungeon-party')">
                            Party Finder
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('portfolio')" :active="route().current('portfolio')">
                            Portfolio
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('crafting')" :active="route().current('crafting')">
                            Crafting
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('bin-sniper')" :active="route().current('bin-sniper')">
                            BIN Sniper
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-border pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-sm font-medium text-white">{{ $page.props.auth.user.name }}</div>
                            <div class="text-xs text-neutral">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="border-b border-border bg-surface-800" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
