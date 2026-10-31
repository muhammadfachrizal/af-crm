<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('laravel-crm::layouts.partials.meta')

        <title>{{ config('app.name') }}{{ ! empty($title ?? null) ? ' - ' . $title : '' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        {{ \Illuminate\Support\Facades\Vite::useBuildDirectory('vendor/laravel-crm')->withEntryPoints(['resources/css/app.css', 'resources/js/app.js']) }}

        {{-- Flatpickr  --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        
        <!-- Styles -->
        @livewireStyles

        @include('laravel-crm::layouts.partials.favicon')
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        />

        <script
            src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
        </script>
    </head>
    <body class="font-sans antialiased bg-base-200">
    
    <x-mary-nav sticky full-width>
        <x-slot:brand>
            <label for="main-drawer" class="lg:hidden mr-3">
                <x-mary-icon name="o-bars-3" class="cursor-pointer" />
            </label>

            <a
                class="navbar-brand text-2xl font-extrabold"
                href="{{ url(route('laravel-crm.dashboard')) }}"
            >
                <img
                    src="{{ asset('vendor/laravel-crm/img/laravel-crm-logo.png') }}"
                    width="215"
                    class="block dark:hidden"
                />

                <img
                    src="{{ asset('vendor/laravel-crm/img/laravel-crm-dark-logo.png') }}"
                    width="215"
                    class="hidden dark:inline"
                />
            </a>
        </x-slot:brand>
        
        <x-slot:actions>
            {{--<x-mary-input icon="o-magnifying-glass" placeholder="Search..." />--}}
            {{--<x-mary-button label="Messages" icon="o-envelope" link="###" class="btn-ghost btn-sm" responsive />
            <x-mary-button label="Notifications" icon="o-bell" link="###" class="btn-ghost btn-sm" responsive />--}}
            <x-mary-theme-toggle class="btn btn-ghost" />
            @if (class_exists('\Laravel\Jetstream\Jetstream') && Laravel\Jetstream\Jetstream::managesProfilePhotos())
                <x-mary-avatar :image="auth()->user()->profile_photo_url" alt="{{ Auth::user()->name }}" />
            @else    
            <x-mary-dropdown label="{{ auth()->user()->name }}" class="btn-neutral btn-sm" right>
                @if(Route::has('profile.show'))
                    <x-mary-menu-item href="{{ route('profile.show') }}" title="{{ __('Profile') }} ({{ __('Host') }})" />
                @else
                    <x-mary-menu-item href="{{ route('laravel-crm.profile') }}" title="{{ ucfirst(__('laravel-crm::lang.profile')) }}" />
                @endif
              {{--  @if (class_exists('\Laravel\Jetstream\Jetstream') && Laravel\Jetstream\Jetstream::hasApiFeatures())
                    <x-mary-menu-item href="{{ route('api-tokens.index') }}" title="{{ __('API Tokens') }}" />
                @endif--}}
                    <x-mary-menu-separator />
                    <form method="POST" action="{{ route('laravel-crm.logout') }}" x-data>
                        @csrf
                        <x-mary-menu-item href="{{ route('laravel-crm.logout') }}" @click.prevent="$root.submit();"  title="{{ __('Log Out') }}" />
                    </form>
            </x-mary-dropdown>
            @endif    
        </x-slot:actions>
    </x-mary-nav>
    
    <x-mary-main with-nav full-width>
        
       <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200">

        <div
            x-data="{
                search: '',

                menus: [
                    {
                        title: 'Dashboard',
                        parent: null,
                        url: '{{ url(route('laravel-crm.dashboard')) }}'
                    },

                    @can('view crm tasks')
                    {
                        title: 'Tasks',
                        parent: 'Daily Work',
                        url: '{{ url(route('laravel-crm.tasks.index')) }}'
                    },
                    @endcan

                    @can('view crm attendances')
                    {
                        title: 'Attendance',
                        parent: 'Daily Work',
                        url: '{{ url(route('laravel-crm.attendances.index')) }}'
                    },
                    @endcan

                    @can('view crm visits')
                    {
                        title: 'Visits',
                        parent: 'Daily Work',
                        url: '{{ url(route('laravel-crm.visits.index')) }}'
                    },
                    @endcan

                    @canany(['view crm activities', 'view crm tasks', 'view crm notes'])
                    {
                        title: 'Activities',
                        parent: 'Daily Work',
                        url: '{{ url(route('laravel-crm.activities.index')) }}'
                    },
                    @endcanany

                    @haschatenabled
                        @can('view crm chat')
                        {
                            title: 'Chat',
                            parent: 'Communication',
                            url: '{{ url(route('laravel-crm.chat.index')) }}'
                        },
                        @endcan
                    @endhaschatenabled

                    @hasemailmarketingenabled
                        @can('view crm email-campaigns')
                        {
                            title: 'Email Campaigns',
                            parent: 'Communication',
                            url: '{{ url(route('laravel-crm.email-campaigns.index')) }}'
                        },
                        @endcan
                    @endhasemailmarketingenabled

                    @hassmsmarketingenabled
                        @can('view crm sms-campaigns')
                        {
                            title: 'SMS Campaigns',
                            parent: 'Communication',
                            url: '{{ url(route('laravel-crm.sms-campaigns.index')) }}'
                        },
                        @endcan
                    @endhassmsmarketingenabled

                    @hasleadsenabled
                        @can('view crm leads')
                        {
                            title: 'Leads',
                            parent: 'Sales',
                            url: '{{ url(route('laravel-crm.leads.index')) }}'
                        },
                        @endcan
                    @endhasleadsenabled

                    @hasdealsenabled
                        @can('view crm deals')
                        {
                            title: 'Deals',
                            parent: 'Sales',
                            url: '{{ url(route('laravel-crm.deals.index')) }}'
                        },
                        @endcan
                    @endhasdealsenabled

                    @hasquotesenabled
                        @can('view crm quotes')
                        {
                            title: 'Quotes',
                            parent: 'Sales',
                            url: '{{ url(route('laravel-crm.quotes.index')) }}'
                        },
                        @endcan
                    @endhasquotesenabled

                    @hasordersenabled
                        @can('view crm orders')
                        {
                            title: 'Orders',
                            parent: 'Transactions',
                            url: '{{ url(route('laravel-crm.orders.index')) }}'
                        },
                        @endcan
                    @endhasordersenabled

                    @hasinvoicesenabled
                        @can('view crm invoices')
                        {
                            title: 'Invoices',
                            parent: 'Transactions',
                            url: '{{ url(route('laravel-crm.invoices.index')) }}'
                        },
                        @endcan
                    @endhasinvoicesenabled

                    @hasdeliveriesenabled
                        @can('view crm deliveries')
                        {
                            title: 'Deliveries',
                            parent: 'Transactions',
                            url: '{{ url(route('laravel-crm.deliveries.index')) }}'
                        },
                        @endcan
                    @endhasdeliveriesenabled

                    @haspurchaseordersenabled
                        @can('view crm purchase orders')
                        {
                            title: 'Purchase Orders',
                            parent: 'Transactions',
                            url: '{{ url(route('laravel-crm.purchase-orders.index')) }}'
                        },
                        @endcan
                    @endhaspurchaseordersenabled

                    @can('view crm people')
                    {
                        title: 'People',
                        parent: 'Contacts',
                        url: '{{ url(route('laravel-crm.people.index')) }}'
                    },
                    @endcan

                    @can('view crm organizations')
                    {
                        title: 'Organizations',
                        parent: 'Contacts',
                        url: '{{ url(route('laravel-crm.organizations.index')) }}'
                    },
                    @endcan

                    @can('view crm users')
                    {
                        title: 'Users',
                        parent: 'Administration',
                        url: '{{ url(route('laravel-crm.users.index')) }}'
                    },
                    @endcan

                    @hasteamsenabled
                        @can('view crm teams')
                        {
                            title: 'Teams',
                            parent: 'Administration',
                            url: '{{ url(route('laravel-crm.teams.index')) }}'
                        },
                        @endcan
                    @endhasteamsenabled

                    @can('view crm products')
                    {
                        title: 'Products',
                        parent: 'Catalog',
                        url: '{{ url(route('laravel-crm.products.index')) }}'
                    },
                    @endcan

                    @hasfeaturesenabled
                        @can('view crm features')
                        {
                            title: 'Features',
                            parent: 'System',
                            url: '{{ url(route('laravel-crm.features.index')) }}'
                        },
                        @endcan
                    @endhasfeaturesenabled

                    @hasmonitoringenabled
                        @can('view crm monitors')
                        {
                            title: 'Monitors',
                            parent: 'System',
                            url: '{{ url(route('laravel-crm.monitors.index')) }}'
                        },
                        @endcan
                    @endhasmonitoringenabled

                    @can('view crm settings')
                    {
                        title: 'General Settings',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.settings.edit')) }}'
                    },
                    @endcan

                    @can('view crm roles')
                    {
                        title: 'Roles & Permissions',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.roles.index')) }}'
                    },
                    @endcan

                    @can('view crm pipelines')
                    {
                        title: 'Pipelines',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.pipelines.index')) }}'
                    },
                    {
                        title: 'Pipeline Stages',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.pipeline-stages.index')) }}'
                    },
                    @endcan

                    @can('view crm product categories')
                    {
                        title: 'Product Categories',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.product-categories.index')) }}'
                    },
                    @endcan

                    @can('view crm tax rates')
                    {
                        title: 'Tax Rates',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.tax-rates.index')) }}'
                    },
                    @endcan

                    @can('view crm labels')
                    {
                        title: 'Labels',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.labels.index')) }}'
                    },
                    @endcan

                    @can('view crm lead sources')
                    {
                        title: 'Lead Sources',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.lead-sources.index')) }}'
                    },
                    @endcan

                    @can('view crm fields')
                    {
                        title: 'Custom Fields',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.fields.index')) }}'
                    },
                    {
                        title: 'Custom Field Groups',
                        parent: 'System',
                        url: '{{ url(route('laravel-crm.field-groups.index')) }}'
                    }
                    @endcan
                ],

                get filteredMenus() {
                    const query = this.search.trim().toLowerCase();

                    if (!query) {
                        return [];
                    }

                    return this.menus.filter(menu => {
                        const title = menu.title.toLowerCase();
                        const parent = (menu.parent || '').toLowerCase();

                        return title.includes(query) ||
                               parent.includes(query);
                    });
                },

                get groupedResults() {
                    const groups = {};

                    this.filteredMenus.forEach(menu => {
                        const parent = menu.parent || '';

                        if (!groups[parent]) {
                            groups[parent] = [];
                        }

                        groups[parent].push(menu);
                    });

                    return groups;
                }
            }"
        >

        {{-- Menu Search --}}
        <div class="px-3 pt-3 pb-2">
            <div
                class="flex items-center w-full h-9
                       rounded-lg border border-base-300
                       bg-base-100
                       focus-within:border-primary"
            >

                {{-- Search icon --}}
                <div class="flex-shrink-0 pl-3 pr-2">
                    <x-mary-icon
                        name="o-magnifying-glass"
                        class="w-4 h-4 text-base-content/50"
                    />
                </div>

                {{-- Search input --}}
                <input
                    type="text"
                    x-model="search"
                    placeholder="Search menu..."
                    class="flex-1 min-w-0 h-full
                           border-0 outline-none
                           bg-transparent
                           text-sm
                           px-1
                           focus:outline-none focus:ring-0"
                />

                {{-- Clear button --}}
                <button
                    type="button"
                    x-show="search"
                    x-cloak
                    @click="search = ''"
                    class="flex-shrink-0
                           flex items-center justify-center
                           w-8 h-full
                           mr-1
                           text-base-content/50
                           hover:text-base-content"
                >
                    <x-mary-icon
                        name="o-x-mark"
                        class="w-4 h-4"
                    />
                </button>

            </div>
        </div>

        {{-- Search Results --}}
        <div
            x-show="search"
            x-cloak
            class="px-2 pb-2"
        >
            <template x-if="filteredMenus.length > 0">
                <div class="bg-base-100 rounded-box">

                    <template x-for="(menus, parent) in groupedResults" :key="parent">

                        <div class="mb-2">

                            {{-- Parent --}}
                            <div
                                x-show="parent"
                                class="px-3 pt-2 pb-1
                                       text-xs font-semibold
                                       text-base-content/50 uppercase"
                                x-text="parent"
                            ></div>

                            {{-- Child menus --}}
                            <template
                                x-for="menu in menus"
                                :key="menu.url"
                            >
                                <a
                                    :href="menu.url"
                                    class="block px-3 py-2
                                           rounded-lg
                                           hover:bg-base-200
                                           text-sm"
                                >
                                    <span
                                        x-text="menu.title"
                                    ></span>
                                </a>
                            </template>

                        </div>

                    </template>

                </div>
            </template>

            <div
                x-show="filteredMenus.length === 0"
                class="px-3 py-4
                       text-sm
                       text-base-content/50
                       text-center"
            >
                No menu found.
            </div>
        </div>

        {{-- Normal Sidebar Menu --}}
        <div x-show="!search">

            <x-mary-menu activate-by-route class="w-full bg-base-100 rounded-none pt-1">

                {{-- Dashboard --}}
                <x-mary-menu-item
                    title="{{ ucfirst(__('laravel-crm::lang.dashboard')) }}"
                    icon="bxs.dashboard"
                    link="{{ url(route('laravel-crm.dashboard')) }}"
                />

                <hr class="my-2 border-t-[length:var(--border)] border-base-content/10">

                {{-- ========================================================= --}}
                {{-- DAILY WORK                                                --}}
                {{-- ========================================================= --}}

                @if(\VentureDrake\LaravelCrm\Support\CurrentTenant::isSuperAdmin())
                    <x-mary-menu-sub title="Super Admin" icon="fas.building" link="###">
                        <x-mary-menu-item title="Dashboard Tenant" icon="fas.chart-line" link="{{ url(route('laravel-crm.tenants.dashboard')) }}" />
                        <x-mary-menu-item title="Tenant" icon="fas.building" link="{{ url(route('laravel-crm.tenants.index')) }}" />
                    </x-mary-menu-sub>
                @endif

                @canany(['view crm tasks', 'view crm attendances', 'view crm visits', 'view crm activities'])
                    <x-mary-menu-sub
                        title="Daily Work"
                        icon="fas.briefcase"
                        link="###"
                    >

                        @can('view crm tasks')
                            <x-mary-menu-item
                                title="{{ ucfirst(__('laravel-crm::lang.tasks')) }}"
                                icon="fas.tasks"
                                link="{{ url(route('laravel-crm.tasks.index')) }}"
                            />
                        @endcan

                        @can('view crm attendances')
                            <x-mary-menu-item
                                title="{{ ucfirst(__('laravel-crm::lang.attendances')) }}"
                                icon="fas.fingerprint"
                                link="{{ url(route('laravel-crm.attendances.index')) }}"
                            />
                        @endcan

                        @can('view crm attendances')
                            <x-mary-menu-item
                                title="Lokasi Kerja"
                                icon="fas.location-dot"
                                link="{{ url(route('laravel-crm.work-locations.index')) }}"
                            />
                        @endcan

                        @can('view crm visits')
                            <x-mary-menu-item
                                title="{{ ucfirst(__('laravel-crm::lang.visits')) }}"
                                icon="fas.map-marker-alt"
                                link="{{ url(route('laravel-crm.visits.index')) }}"
                            />
                        @endcan

                        @canany(['view crm activities', 'view crm tasks', 'view crm notes'])
                            <x-mary-menu-item
                                title="{{ ucfirst(__('laravel-crm::lang.activity')) }}"
                                icon="fas.timeline"
                                link="{{ url(route('laravel-crm.activities.index')) }}"
                            />
                        @endcanany

                    </x-mary-menu-sub>
                @endcanany

                {{-- ========================================================= --}}
                {{-- COMMUNICATION                                             --}}
                {{-- ========================================================= --}}

                @if (
                    (config('laravel-crm.chat.enabled') ?? false) ||
                    (config('laravel-crm.email-marketing.enabled') ?? false) ||
                    (config('laravel-crm.sms-marketing.enabled') ?? false)
                )
                    <x-mary-menu-sub
                        title="Communication"
                        icon="fas.comments"
                        link="###"
                    >

                        @haschatenabled
                            @can('view crm chat')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.chat')) }}"
                                    icon="fas.comments"
                                    link="{{ url(route('laravel-crm.chat.index')) }}"
                                />
                            @endcan
                        @endhaschatenabled

                        @hasemailmarketingenabled
                            @can('view crm email-campaigns')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.email')) }}"
                                    icon="fas.envelope"
                                    link="{{ url(route('laravel-crm.email-campaigns.index')) }}"
                                />
                            @endcan
                        @endhasemailmarketingenabled

                        @hassmsmarketingenabled
                            @can('view crm sms-campaigns')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.sms')) }}"
                                    icon="fas.sms"
                                    link="{{ url(route('laravel-crm.sms-campaigns.index')) }}"
                                />
                            @endcan
                        @endhassmsmarketingenabled

                    </x-mary-menu-sub>
                @endif

                {{-- ========================================================= --}}
                {{-- SALES                                                     --}}
                {{-- ========================================================= --}}

                @canany(['view crm leads', 'view crm deals', 'view crm quotes'])
                    <x-mary-menu-sub
                        title="Sales"
                        icon="fas.chart-line"
                        link="###"
                    >

                        @hasleadsenabled
                            @can('view crm leads')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.leads')) }}"
                                    icon="fas.crosshairs"
                                    link="{{ url(route('laravel-crm.leads.index')) }}"
                                />
                            @endcan
                        @endhasleadsenabled

                        @hasdealsenabled
                            @can('view crm deals')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.deals')) }}"
                                    icon="fas.dollar-sign"
                                    link="{{ url(route('laravel-crm.deals.index')) }}"
                                />
                            @endcan
                        @endhasdealsenabled

                        @hasquotesenabled
                            @can('view crm quotes')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.quotes')) }}"
                                    icon="fas.file-lines"
                                    link="{{ url(route('laravel-crm.quotes.index')) }}"
                                />
                            @endcan
                        @endhasquotesenabled

                    </x-mary-menu-sub>
                @endcanany

                {{-- ========================================================= --}}
                {{-- TRANSACTIONS                                              --}}
                {{-- ========================================================= --}}

                @canany([
                    'view crm orders',
                    'view crm invoices',
                    'view crm deliveries',
                    'view crm purchase orders'
                ])
                    <x-mary-menu-sub
                        title="Transactions"
                        icon="fas.receipt"
                        link="###"
                    >

                        @hasordersenabled
                            @can('view crm orders')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.orders')) }}"
                                    icon="fas.shopping-cart"
                                    link="{{ url(route('laravel-crm.orders.index')) }}"
                                />
                            @endcan
                        @endhasordersenabled

                        @hasinvoicesenabled
                            @can('view crm invoices')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.invoices')) }}"
                                    icon="fas.file-invoice"
                                    link="{{ url(route('laravel-crm.invoices.index')) }}"
                                />
                            @endcan
                        @endhasinvoicesenabled

                        @hasdeliveriesenabled
                            @can('view crm deliveries')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.deliveries')) }}"
                                    icon="fas.shipping-fast"
                                    link="{{ url(route('laravel-crm.deliveries.index')) }}"
                                />
                            @endcan
                        @endhasdeliveriesenabled

                        @haspurchaseordersenabled
                            @can('view crm purchase orders')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.purchase_orders')) }}"
                                    icon="fas.file-invoice-dollar"
                                    link="{{ url(route('laravel-crm.purchase-orders.index')) }}"
                                />
                            @endcan
                        @endhaspurchaseordersenabled

                    </x-mary-menu-sub>
                @endcanany

                {{-- ========================================================= --}}
                {{-- CONTACTS                                                  --}}
                {{-- ========================================================= --}}

                @canany(['view crm people', 'view crm organizations'])
                    <x-mary-menu-sub
                        title="Contacts"
                        icon="fas.address-book"
                        link="###"
                    >

                        @can('view crm people')
                            <x-mary-menu-item
                                title="{{ ucfirst(__('laravel-crm::lang.people')) }}"
                                icon="fas.user-circle"
                                link="{{ url(route('laravel-crm.people.index')) }}"
                            />
                        @endcan

                        @can('view crm organizations')
                            <x-mary-menu-item
                                title="{{ ucfirst(__('laravel-crm::lang.organizations')) }}"
                                icon="fas.building"
                                link="{{ url(route('laravel-crm.organizations.index')) }}"
                            />
                        @endcan

                    </x-mary-menu-sub>
                @endcanany

                {{-- ========================================================= --}}
                {{-- ADMINISTRATION                                            --}}
                {{-- ========================================================= --}}

                @canany(['view crm users', 'view crm teams'])
                    <x-mary-menu-sub
                        title="Administration"
                        icon="fas.users-cog"
                        link="###"
                    >

                        @can('view crm users')
                            <x-mary-menu-item
                                title="{{ ucfirst(__('laravel-crm::lang.users')) }}"
                                icon="fas.user"
                                link="{{ url(route('laravel-crm.users.index')) }}"
                            />
                        @endcan

                        @hasteamsenabled
                            @can('view crm teams')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.teams')) }}"
                                    icon="fas.users"
                                    link="{{ url(route('laravel-crm.teams.index')) }}"
                                />
                            @endcan
                        @endhasteamsenabled

                    </x-mary-menu-sub>
                @endcanany

                {{-- ========================================================= --}}
                {{-- CATALOG                                                   --}}
                {{-- ========================================================= --}}

                @can('view crm products')
                    <x-mary-menu-sub
                        title="Catalog"
                        icon="fas.boxes"
                        link="###"
                    >

                        <x-mary-menu-item
                            title="{{ ucfirst(__('laravel-crm::lang.products')) }}"
                            icon="fas.tag"
                            link="{{ url(route('laravel-crm.products.index')) }}"
                        />

                    </x-mary-menu-sub>
                @endcan

                {{-- ========================================================= --}}
                {{-- SYSTEM                                                    --}}
                {{-- ========================================================= --}}

                @canany([
                    'view crm features',
                    'view crm monitors',
                    'view crm settings',
                    'view crm roles',
                    'view crm pipelines',
                    'view crm product categories',
                    'view crm tax rates',
                    'view crm labels',
                    'view crm lead sources',
                    'view crm fields',
                    'view crm integrations',
                    'manage crm chat widgets'
                ])
                    <x-mary-menu-sub
                        title="System"
                        icon="fas.cog"
                        link="###"
                    >

                        @hasfeaturesenabled
                            @can('view crm features')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.features')) }}"
                                    icon="fas.lightbulb"
                                    link="{{ url(route('laravel-crm.features.index')) }}"
                                />
                            @endcan
                        @endhasfeaturesenabled

                        @hasmonitoringenabled
                            @can('view crm monitors')
                                <x-mary-menu-item
                                    title="{{ ucfirst(__('laravel-crm::lang.monitors')) }}"
                                    icon="fas.heartbeat"
                                    link="{{ url(route('laravel-crm.monitors.index')) }}"
                                />
                            @endcan
                        @endhasmonitoringenabled

                        @can('view crm settings')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.settings.edit')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.general_settings')) }}"
                            />
                        @endcan

                        @can('view crm roles')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.roles.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.roles_and_permissions')) }}"
                            />
                        @endcan

                        @can('view crm pipelines')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.pipelines.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.pipelines')) }}"
                            />

                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.pipeline-stages.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.pipeline_stages')) }}"
                            />
                        @endcan

                        @can('view crm product categories')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.product-categories.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.product_categories')) }}"
                            />
                        @endcan

                        @can('view crm tax rates')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.tax-rates.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.tax_rates')) }}"
                            />
                        @endcan

                        @can('view crm labels')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.labels.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.labels')) }}"
                            />
                        @endcan

                        @can('view crm lead sources')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.lead-sources.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.lead_sources')) }}"
                            />
                        @endcan

                        @can('view crm fields')
                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.fields.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.custom_fields')) }}"
                            />

                            <x-mary-menu-item
                                link="{{ url(route('laravel-crm.field-groups.index')) }}"
                                title="{{ ucwords(__('laravel-crm::lang.custom_field_groups')) }}"
                            />
                        @endcan

                    </x-mary-menu-sub>
                @endcanany

            </x-mary-menu>

        </div>
    </div>

</x-slot:sidebar>
        <x-slot:content>
            <!-- Page Heading -->
            @if (isset($header))
                {{ $header }}
            @endif
            {{ $slot ?? null }}
        </x-slot:content>
    </x-mary-main>
    
    <x-mary-toast />

    @stack('modals')
    @livewireScripts
    @stack('livewire-js')
    </body>
</html>
