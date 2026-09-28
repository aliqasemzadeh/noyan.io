<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
---
description:
alwaysApply: true
---

# Project Role & Context
You are an expert full-stack developer working on a Laravel project. Your task is to generate code that strictly adheres to the following project guidelines, tech stack, and architectural rules.

## 1. Tech Stack & Environment
*   **Backend:** PHP 8.4, Laravel 13
*   **Frontend:** TailwindCSS, AlpineJS (for UI interactions)
*   **Livewire:** Version 4 (Do NOT use Volt; use standard `Livewire\Component`)
*   **UI Library:** FluxUI (https://fluxui.dev/)
*   **Icons:** Lucide Icons (https://lucide.dev/icons). When you add new icons to the UI, you MUST automatically execute the terminal command to publish them. You can publish multiple icons in a single command, like this: `php artisan flux:icon icon1 icon2 icon3`.

## 2. Architecture & File Structure
*   **Single-File Component Architecture:** ALWAYS write Livewire components as Single-File Components. Place all PHP logic inside a `<?php ... ?>` block at the top of the `.blade.php` file.
*   **File Location:** Save these components directly in `resources/views/components/` or `resources/views/pages/`.
*   **Root Node:** Ensure the HTML portion of the component always has a single root wrapping `<div>`.
*   **Separation of Concerns:** Use AlpineJS for UI manipulation and Livewire strictly for Backend logic.
*   **Livewire Inclusions:** When loading a Livewire component inside a view, always pass a key: `<livewire:component-name :key="$componentId" />`.

## 3. Database & Eloquent Models
*   **Attributes:** Use PHP Attributes like `#[Fillable([])]` and `#[Hidden]` from `Illuminate\Database\Eloquent\Attributes` instead of the traditional `$fillable` or `$hidden` arrays.
*   **Relations:** Always explicitly define Eloquent relationships.
*   **Performance:** Heavily optimize queries and strictly avoid N+1 problems.

## 4. Livewire Logic & State Management
*   **Computed Properties:** Use `#[Computed]` attributes to load data (https://livewire.laravel.com/docs/4.x/attribute-computed).
*   **Forms:** For model forms, use Livewire Form Objects (https://livewire.laravel.com/docs/4.x/forms). Extract them using `php artisan livewire:form ModelForm`. Use a `setModel` method to populate data.
*   **Live Binding:** NEVER use `wire:model.live` in forms unless explicitly requested.
*   **Navigation:** Always use `wire:navigate` on internal links (`<a>` tags or Flux components with `href`) to ensure SPA-like page transitions without full page reloads.
*   **Events:** Never use `protected $listeners`. Use `Livewire\Attributes\On;` and `$this->dispatch('event-name');`.
*   **Event Naming:** Use full explicit names (e.g., `panels.administrator.learning-management.school.edit.assign-data`).
*   **Notifications:** After any Livewire action, trigger a toast notification: `Flux::toast('message');`.

## 5. FluxUI Component Rules & UI Innovation
*   **Innovative UI/UX:** Always strive for a modern, clean, and innovative user interface. Leverage FluxUI's capabilities creatively to build intuitive, aesthetically pleasing experiences (e.g., smart empty states, elegant loading transitions, clean alignments, and modern spacing).

### Layout & Pages
*   **Page Titles:** Use `<x-slot name="title">Page Title - {{ config('app.name') }}</x-slot>`.
*   **Breadcrumbs:** Always include `<flux:breadcrumbs>`.
*   **Cards:** Use `<flux:card>` for search and filter wrappers.

### Tables & Lists
*   **Component:** Use `<flux:table>` for lists. Implement pagination using `->paginate(config('general.per_page'))`.
*   **Searchable Fields:** Add search inputs at the top of `<flux:table.columns>`. ALWAYS use the `clearable` attribute on search fields: `<flux:input placeholder="Search orders" clearable />` or `<flux:input wire:model.live.debounce.300ms="search" icon="search" placeholder="{{ __('general.search') }}..." clearable />`.

### Modals
*   **Wrapper:** For modal components, do NOT add an outer `<div>`. Just use `<flux:modal>`.
*   **Naming Convention:** Modal names MUST use dot notation representing their context (e.g., `name="user.create"`), NOT hyphens.
*   **Styling:** Always use flyout right positioning for forms: `<flux:modal flyout position="right">`.
*   **Triggers:** Use `<flux:modal.trigger name="module.entity.action">` to open modals (especially if passing data).
*   **Buttons:** In Create/Edit modals, no "Cancel" buttons are needed; use full-width submit buttons (`w-full`). **EXCEPTION:** For Delete Confirmation modals, you MUST use the specific layout utilizing `<flux:spacer />` and `<flux:modal.close>` with a Ghost variant cancel button and a Danger variant submit button.
*   **Control via Livewire:** Open/close modals programmatically using `Flux::modal('module.entity.action')->show();` or `Flux::modals()->close();`.

### Forms & Inputs
*   **Input Features (Clearable, Viewable, Copyable):** Use Flux UI's built-in input modifiers when appropriate:
    *   For search fields or optional inputs: `<flux:input placeholder="{{ __('general.search') }}..." clearable />`
    *   For passwords or secret tokens: `<flux:input type="password" viewable />`
    *   For API keys or read-only generated tokens: `<flux:input icon="key" readonly copyable />`
*   **Prices & Masking:** Use Flux UI input masking for prices, currencies, or formatted numbers (https://fluxui.dev/components/input#input-masking).
*   **File Uploads:** Always use Flux UI's file upload component (https://fluxui.dev/components/file-upload). **Crucial constraint:** When placing a file upload inside a modal, you MUST use the **inline layout** (https://fluxui.dev/components/file-upload#inline-layout). Only use the standard/block file upload layout if the upload field is placed directly on a full Livewire page (outside of any modals).
*   **Selects:** Use `<flux:select searchable>` for standard searchable dropdowns. **CRUCIAL RULE:** The `variant="combobox"` attribute does NOT support the `searchable` feature. Never combine them. Use the backend-search component for dynamic database options (https://fluxui.dev/components/select#backend-search).
*   **Pillbox:** Use `https://fluxui.dev/components/pillbox#searchable` for multi-select/search.
*   **Numbers:** Use `<flux:input type="number" />`.
*   **Dates/Times:** Use `<flux:date-picker selectable-header />` and `<flux:time-picker selectable-header />`.
*   **Switches:** For boolean states (e.g., `is_active`), use an inline field:
    `<flux:field variant="inline"><flux:label>Label</flux:label><flux:switch wire:model.live="field_name" /><flux:error name="field_name" /></flux:field>`

### Buttons & Actions
*   **Responsive Page Actions (Mobile Overflow Prevention):** To prevent top-level action buttons from breaking out of the container on mobile screens:
    *   If there is **only one** action, use a standard single `<flux:button>`.
    *   If there are **multiple** actions, group them inside a `<flux:dropdown>` menu for a responsive and clean layout. Example:
    ```html
    <flux:dropdown>
        <flux:button icon:trailing="chevron-down">{{ __('general.options') }}</flux:button>
        <flux:menu>
            <flux:menu.item icon="plus">{{ __('general.create') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item variant="danger" icon="trash">{{ __('general.delete') }}</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
    ```
*   **Submit Buttons:** Use `<flux:button type="submit" variant="primary" color="teal">{{ __('general.save') }}</flux:button>`.
*   **Generic Buttons:** Only use `color="zinc"` for generic/neutral buttons.
*   **Icons & Tooltips:** Action buttons (edit/delete/import) MUST be wrapped in `<flux:tooltip>` and use small, icon-only variants (e.g., `size="xs" variant="primary" icon="pencil" icon:variant="outline"`).
*   **Colors/Variants:** Edit = `color="blue"`, Delete = `variant="danger"`, Import = `color="teal"`.

### Data Display
*   Use `<flux:callout icon="cube" variant="secondary" inline>` to display specific records (like permissions, roles, users) inside modals.

## 6. Localization & Permissions (STRICT RULES)
*   **General Translations:** ALL UI texts, actions, and general words MUST be translated using ONLY the `general.php` file (e.g., `{{ __('general.create_user') }}` or `{{ __('general.save') }}`). Do NOT use any other files (like `actions.php` or module-specific files) for standard interface texts.
*   **Permissions List:** Use Spatie Laravel Permission v6. The COMPLETE list of permissions MUST be stored strictly inside `/lang/fa/permissions.php` and `/lang/en/permissions.php`.
*   **Permissions Structure (Role-Based):** Inside the `permissions.php` file, all permissions MUST be grouped and categorized by roles as nested arrays.
    *Example Structure:*
    ```php
    return [
        'administrator' => [
            'user_create' => 'Create User',
            'user_edit' => 'Edit User',
        ],
        'manager' => [
            // ...
        ]
    ];
    ```

## 7. Reference Examples

**Table Example:**
```html
<div>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('general.users') }}</flux:heading>
            
            <!-- Example of a single action button -->
            <flux:modal.trigger name="user.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_user') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <flux:card>
            <div class="mb-4">
                <flux:input wire:model.live.debounce.300ms="search" icon="search" placeholder="{{ __('general.search') }}..." clearable />
            </div>

            <flux:table :paginate="$this->users">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.first_name') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell>{{ $user->first_name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button size="xs" variant="primary" color="blue" icon="pencil" icon:variant="outline" wire:click="$dispatch('panels.administrator.user.edit.assign-data', { user: {{ $user->id }} })" />
                                    </flux:tooltip>
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:modal.trigger name="user.delete.{{ $user->id }}">
                                            <flux:button size="xs" variant="danger" icon="trash" icon:variant="outline" />
                                        </flux:modal.trigger>
                                    </flux:tooltip>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
    <livewire:user.create />
    <livewire:user.edit />
</div>
```

**Delete Modal Implementation Example:**
```html
<flux:modal.trigger name="module.entity.delete">
    <flux:button variant="danger">{{ __('general.delete') }}</flux:button>
</flux:modal.trigger>

<flux:modal name="module.entity.delete" class="min-w-[22rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_warning_message') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.delete') }}</flux:button>
        </div>
    </div>
</flux:modal>
```

## 8. UI & CRUD Interaction Workflow
*   **Create & Edit (Flyout Modals):** Never redirect to separate routes/pages for creating or editing records. Always implement `Create` and `Edit` forms inside a FluxUI Flyout Modal (`<flux:modal flyout position="right">`).
*   **Delete Operations (Standard Modal):** Do not execute deletions instantly. Complex delete operations must trigger a **Standard Center-Aligned Modal** (`<flux:modal>`). See the "Delete Modal Implementation Example" section for the exact required structure (using `<flux:spacer />` and `<flux:modal.close>`).
*   **Event-Driven Table Refresh:** The main data table must refresh automatically after any successful Create, Edit, or Delete action without a full page reload.
    *   To do this, dispatch a context-specific Livewire event targeting the exact table page. For example: `$this->dispatch('panels.administrator.user.index.table');`.
    *   The main listing Livewire component must listen for this precise event using `#[On('panels.administrator.user.index.table')]` to re-fetch its data. Do not use generic names like `refresh-data`.


</laravel-boost-guidelines>
