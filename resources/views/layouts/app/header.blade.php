<!DOCTYPE html>
<html class="dark"
      lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-zinc-50 dark:bg-zinc-800">
    <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
                 container>
        <flux:sidebar.toggle class="mr-2 lg:hidden"
                             icon="bars-2"
                             inset="left" />

        <x-app-logo href="{{ route('dashboard') }}"
                    wire:navigate />

        <flux:spacer />

        <x-desktop-user-menu />
    </flux:header>

    <!-- Mobile Menu -->
    <flux:sidebar class="border-e border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-700 dark:bg-zinc-900"
                  collapsible="mobile"
                  sticky>
        <flux:sidebar.header>
            <x-app-logo href="{{ route('dashboard') }}"
                        :sidebar="true"
                        wire:navigate />
            <flux:sidebar.collapse
                                   class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')">
                <flux:sidebar.item icon="layout-grid"
                                   :href="route('dashboard')"
                                   :current="request()->routeIs('dashboard')"
                                   wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item href="https://github.com/laravel/livewire-starter-kit"
                               icon="folder-git-2"
                               target="_blank">
                {{ __('Repository') }}
            </flux:sidebar.item>
            <flux:sidebar.item href="https://laravel.com/docs/starter-kits#livewire"
                               icon="book-open-text"
                               target="_blank">
                {{ __('Documentation') }}
            </flux:sidebar.item>
        </flux:sidebar.nav>
    </flux:sidebar>

    {{ $slot }}

    @fluxScripts
</body>

</html>
