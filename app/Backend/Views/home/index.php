<div class="flex min-h-screen bg-slate-50 text-slate-900 antialiased">
    
    <!-- 1. RESPONSIVE SIDEBAR NAVIGATION (Hidden on mobile, block on md+) -->
    <aside class="fixed inset-y-0 left-0 z-20 hidden w-64 border-r border-slate-200 bg-white md:block">
        <div class="flex h-16 items-center px-6 border-b border-slate-100">
            <span class="text-xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-indigo-600 animate-pulse"></span>
                AdminPanel
            </span>
        </div>
        <nav class="space-y-1 p-4">
            <a href="#" class="flex items-center gap-3 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white shadow-xs">
                📊 Dashboard
            </a>
            <a href="#" class="flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                👥 Users Management
            </a>
            <a href="#" class="flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                ⚙️ Configurations
            </a>
        </nav>
    </aside>

    <!-- 2. MAIN APPLICATION CONTENT VIEW (Offset by sidebar width on desktop screens) -->
    <div class="flex-1 md:pl-64 flex flex-col">
        
        <!-- TOP APPBAR / HEADER CONTROL -->
        <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white/80 px-4 backdrop-blur-md sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <!-- Mobile Menu Button (Toggleable via jQuery/JS) -->
                <button class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 md:hidden" aria-label="Toggle Sidebar">
                    ☰
                </button>
                <h2 class="text-sm font-medium text-slate-500">Overview / Metrics</h2>
            </div>
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-slate-200 ring-2 ring-slate-100 flex items-center justify-between text-xs font-semibold p-2">AD</div>
            </div>
        </header>

        <!-- CONTAINER GRID LAYOUT -->
        <main class="flex-1 space-y-8 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
            
            <!-- HEADER BLOCK TITLE -->
            <div class="border-b border-slate-200 pb-5">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">System Dashboard 🎉</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Engineered running <span class="font-medium text-slate-700">Custom PHP MVC</span> · <span class="font-medium text-slate-700">Vue 3 Islands</span> · <span class="font-medium text-slate-700">jQuery Core APIs</span> · <span class="font-medium text-slate-700">Tailwind v4</span>.
                </p>
            </div>

            <!-- 3. HIGHLIGHT METRIC COUNTERS CARDS -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Vue Island Live Counter Metric Widget Wrapper -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500">Live Counter Session State</span>
                        <span class="text-xs bg-indigo-50 text-indigo-700 font-medium px-2 py-0.5 rounded-full">Vue 3 Engine</span>
                    </div>
                    <div class="mt-4">
                        <div data-vue="Counter" data-props='<?= e(json_encode(['start' => 0])) ?>'></div>
                    </div>
                </div>

                <!-- Total Users Summary Count Widget -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500">System Database Pool</span>
                        <span class="text-xs bg-emerald-50 text-emerald-700 font-medium px-2 py-0.5 rounded-full">Active PDO</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-bold tracking-tight text-slate-900">1,248</span>
                        <span class="text-xs text-emerald-600 font-medium">↑ 12% today</span>
                    </div>
                </div>
            </div>

            <!-- 4. APPLICATION MANAGEMENT INTERACTION PANELS -->
            <div class="grid gap-8 lg:grid-cols-3 items-start">
                
                <!-- NEW USER ENTRY CREATION FORM CONTAINER BLOCK (1-Column Span) -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs lg:col-span-1">
                    <h3 class="text-base font-semibold text-slate-900">Add Account Profile</h3>
                    <p class="mt-1 text-xs text-slate-500">Insert custom access definitions instantly to target tables.</p>
                    
                    <form id="user-form" class="mt-6 space-y-4">
                        <div>
                            <label for="name_backend" class="block text-xs font-medium text-slate-700 mb-1.5">Full Name</label>
                            <input name="name" id="name_backend" placeholder="e.g. John Doe" required
                                   class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-2 text-sm text-slate-900 shadow-xs placeholder-slate-400 focus:border-slate-900 focus:bg-white focus:outline-hidden transition-colors">
                        </div>
                        <div>
                            <label for="email_backend" class="block text-xs font-medium text-slate-700 mb-1.5">Email Address</label>
                            <input name="email" type="email" id="email_backend" placeholder="name@domain.com" required
                                   class="w-full rounded-lg border border-slate-300 bg-slate-50/50 px-3 py-2 text-sm text-slate-900 shadow-xs placeholder-slate-400 focus:border-slate-900 focus:bg-white focus:outline-hidden transition-colors">
                        </div>
                        
                        <button class="w-full rounded-lg bg-slate-900 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 transition-colors cursor-pointer">
                            Save Record
                        </button>
                    </form>
                    
                    <!-- Form validation error prompt block -->
                    <div id="user-error" class="mt-3 hidden rounded-lg bg-red-50 p-3 text-xs text-red-700 font-medium"></div>
                </div>

                <!-- DYNAMIC REGISTERED DATA LISTING CONTAINER (2-Column Span Table Layout) -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-xs lg:col-span-2 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Active Directory Listing</h3>
                            <p class="mt-1 text-xs text-slate-500">Live asynchronous data ingestion linked via backend routes.</p>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm text-slate-500">
                            <thead class="bg-slate-50 text-xs font-semibold text-slate-700 uppercase tracking-wider border-b border-slate-100">
                                <tr>
                                    <th scope="col" class="px-6 py-3.5">User Profile Identifier Information</th>
                                    <th scope="col" class="px-6 py-3.5">Security Status</th>
                                </tr>
                            </thead>
                            <!-- Hook target for jQuery async generation rows -->
                            <tbody id="user-list" class="divide-y divide-slate-100 bg-white text-slate-700 font-medium">
                                <!-- Example output block generated inside your jQuery handler:
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-900">Milan R.</div>
                                        <div class="text-xs text-slate-400">milan@example.com</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/10 ring-inset">Active Verified</span>
                                    </td>
                                </tr>
                                -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
