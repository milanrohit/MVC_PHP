<section class="space-y-8">
    <div>
        <h1 class="text-3xl font-bold tracking-tight">It works 🎉</h1>
        <p class="mt-2 text-slate-600">
            Custom PHP MVC · Vue 3 islands · jQuery · Tailwind v4.
        </p>
    </div>

    <!-- Vue island: any element with data-vue="ComponentName" is mounted -->
    <div data-vue="Counter" data-props='<?= e(json_encode(['start' => 0])) ?>'></div>

    <!-- jQuery-powered users demo (talks to the PDO-backed API) -->
    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold">Users</h2>
        <form id="user-form" class="mt-4 flex flex-wrap gap-2">
            <input name="name"  id="name_backend" label="label_name_backend" placeholder="Name" required class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="email" type="email" id="email_backend" label="label_email_backend" placeholder="Email" required class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Add</button>
        </form>
        <p id="user-error" class="mt-2 hidden text-sm text-red-600"></p>
        <ul id="user-list" class="mt-4 divide-y divide-slate-100 text-sm"></ul>
    </div>
</section>
