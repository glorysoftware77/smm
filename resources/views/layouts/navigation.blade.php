<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-surface-border/80 bg-[#0b090a]/80 backdrop-blur-xl">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-9 w-9 rounded-lg ring-1 ring-white/10" />
                    <div class="leading-tight">
                        <div class="text-sm font-semibold tracking-tight text-white">Glory SMM</div>
                        <div class="hidden text-[10px] uppercase tracking-[0.16em] text-zinc-500 sm:block">Publish hub</div>
                    </div>
                </a>

                <div class="hidden items-center gap-1 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('posts.create')" :active="request()->routeIs('posts.*')">
                        {{ __('Create Post') }}
                    </x-nav-link>
                    <x-nav-link :href="route('insights.index')" :active="request()->routeIs('insights.*')">
                        {{ __('Insights') }}
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-1.5 text-sm font-medium text-zinc-300 transition hover:border-surface-border hover:bg-white/[0.03] hover:text-white focus:outline-none">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-glory-700/40 text-xs font-semibold text-glory-200 ring-1 ring-glory-500/30">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </span>
                            <span>{{ Auth::user()->name }}</span>
                            <svg class="h-4 w-4 text-zinc-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-lg p-2 text-zinc-400 transition hover:bg-white/[0.04] hover:text-zinc-200 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-surface-border sm:hidden">
        <div class="space-y-1 px-2 py-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('posts.create')" :active="request()->routeIs('posts.*')">
                {{ __('Create Post') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('insights.index')" :active="request()->routeIs('insights.*')">
                {{ __('Insights') }}
            </x-responsive-nav-link>
        </div>

        <div class="border-t border-surface-border px-4 py-4">
            <div class="font-medium text-zinc-100">{{ Auth::user()->name }}</div>
            <div class="text-sm text-zinc-500">{{ Auth::user()->email }}</div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
