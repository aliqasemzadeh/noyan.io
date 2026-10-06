@props([
    'label' => null,
    'placeholder' => null,
    'min' => null,
    'max' => null,
    'startModel' => null,
    'endModel' => null,
    'start' => null,
    'end' => null,
])

@php
    $startModel ??= $attributes->get('wire:model.start');
    $endModel ??= $attributes->get('wire:model.end');
    $placeholder ??= __('general.date_range_placeholder');
    $label ??= __('general.date_range');
@endphp

<div
    class="w-full"
    x-data="window.jalaliDateRange({
        start: @js($start),
        end: @js($end),
        startModel: @js($startModel),
        endModel: @js($endModel),
        min: @js($min),
        max: @js($max),
        placeholder: @js($placeholder),
        fromToLabel: @js(__('general.date_range_from_to')),
        selectStartHint: @js(__('general.select_range_start')),
        selectEndHint: @js(__('general.select_range_end')),
    })"
    x-cloak
>
    <flux:field>
        @if ($label)
            <flux:label>{{ $label }}</flux:label>
        @endif

        <div class="relative w-full">
            <flux:input
                readonly
                x-on:click="showDatepicker = !showDatepicker"
                x-on:keydown.escape="showDatepicker = false"
                x-bind:value="displayValue"
                :placeholder="$placeholder"
                class="cursor-pointer"
                dir="ltr"
            >
                <x-slot name="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                        <rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect>
                        <line x1="16" x2="16" y1="2" y2="6"></line>
                        <line x1="8" x2="8" y1="2" y2="6"></line>
                        <line x1="3" x2="21" y1="10" y2="10"></line>
                    </svg>
                </x-slot>

                <x-slot name="iconTrailing">
                    <template x-if="start || end || draftStart">
                        <button
                            type="button"
                            x-on:click.stop="clearRange()"
                            class="h-4 w-4 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                            :title="@js(__('general.clear_date_range'))"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </template>
                </x-slot>
            </flux:input>

            <div
                class="absolute z-50 mt-2 w-[300px] rounded-md border border-zinc-200 bg-white p-3 text-zinc-950 shadow-md outline-none dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50"
                x-show="showDatepicker"
                x-on:click.away="showDatepicker = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                style="display: none;"
            >
                <div class="flex items-center justify-between pb-4">
                    <button type="button" x-on:click="previousMonth()" :disabled="!canGoPrevious()" class="h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100 transition-opacity disabled:opacity-20 disabled:pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m9 18 6-6-6-6"/></svg>
                    </button>

                    <div class="text-sm font-medium">
                        <span x-text="MONTH_NAMES[month - 1]"></span>
                        <span x-text="year"></span>
                    </div>

                    <button type="button" x-on:click="nextMonth()" :disabled="!canGoNext()" class="h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100 transition-opacity disabled:opacity-20 disabled:pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center">
                    <template x-for="day in DAYS" :key="day">
                        <div class="text-zinc-500 dark:text-zinc-400 rounded-md w-9 h-9 flex items-center justify-center text-[0.8rem] font-normal" x-text="day"></div>
                    </template>

                    <template x-for="blankday in blankdays" :key="'b'+blankday">
                        <div class="w-9 h-9"></div>
                    </template>

                    <template x-for="date in no_of_days" :key="date">
                        <button
                            type="button"
                            x-on:click="selectDay(date)"
                            :disabled="isDisabled(date)"
                            class="h-9 w-9 p-0 font-normal transition-colors text-sm flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 dark:focus-visible:ring-zinc-300 disabled:opacity-30 disabled:pointer-events-none"
                            :class="{
                                'rounded-md bg-zinc-900 text-zinc-50 dark:bg-zinc-50 dark:text-zinc-900': isRangeEdge(date),
                                'rounded-none bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-50': isInRange(date) && !isRangeEdge(date),
                                'rounded-s-md': isStart(date) && !isEnd(date),
                                'rounded-e-md': isEnd(date) && !isStart(date),
                                'rounded-md bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-50': isToday(date) && !isInRange(date) && !isRangeEdge(date),
                                'hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md': !isInRange(date) && !isRangeEdge(date) && !isDisabled(date),
                            }"
                            x-text="date"
                        ></button>
                    </template>
                </div>

                <p class="mt-3 text-xs text-zinc-500 text-center" x-text="hintText"></p>
            </div>
        </div>
    </flux:field>
</div>

@script
<script>
    window.jalaliDateRange = function(config) {
        return {
            showDatepicker: false,
            start: config.start || '',
            end: config.end || '',
            draftStart: '',
            month: '',
            year: '',
            years: [],
            no_of_days: [],
            blankdays: [],
            startModel: config.startModel,
            endModel: config.endModel,
            min: config.min,
            max: config.max,
            placeholder: config.placeholder,
            fromToLabel: config.fromToLabel,
            selectStartHint: config.selectStartHint,
            selectEndHint: config.selectEndHint,
            MONTH_NAMES: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
            DAYS: ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'],

            get displayValue() {
                if (this.start && this.end) {
                    return this.fromToLabel
                        .replace(':start', this.start)
                        .replace(':end', this.end);
                }

                if (this.start) {
                    return this.start;
                }

                if (this.draftStart) {
                    return this.draftStart;
                }

                return '';
            },

            get hintText() {
                return this.draftStart || (this.start && !this.end)
                    ? this.selectEndHint
                    : this.selectStartHint;
            },

            init() {
                if (this.startModel && this.$wire) {
                    this.start = this.$wire.get(this.startModel) || this.start || '';
                    this.end = this.$wire.get(this.endModel) || this.end || '';
                }

                const today = this.getTodayPersian();
                let initYear = parseInt(today.year);
                let initMonth = parseInt(today.month);
                const seed = this.start || this.end;

                if (seed) {
                    const d = this.parsePersianDate(seed);
                    if (d) {
                        initYear = d.year;
                        initMonth = d.month;
                    }
                }

                this.year = initYear;
                this.month = initMonth;
                this.getNoOfDays();

            },

            syncToWire() {
                if (!this.startModel || !this.$wire) {
                    return;
                }

                this.$wire.set(this.startModel, this.start || '');
                this.$wire.set(this.endModel, this.end || '');
            },

            selectDay(date) {
                if (this.isDisabled(date)) {
                    return;
                }

                const selected = this.formatPersianDate(this.year, this.month, date);

                if (!this.draftStart && !(this.start && !this.end)) {
                    this.draftStart = selected;
                    this.start = selected;
                    this.end = '';
                    this.syncToWire();
                    return;
                }

                let start = this.draftStart || this.start;
                let end = selected;

                if (this.compareDates(end, start) < 0) {
                    [start, end] = [end, start];
                }

                this.start = start;
                this.end = end;
                this.draftStart = '';
                this.showDatepicker = false;
                this.syncToWire();
            },

            clearRange() {
                this.start = '';
                this.end = '';
                this.draftStart = '';
                this.syncToWire();
            },

            isStart(date) {
                return this.matches(this.start || this.draftStart, date);
            },

            isEnd(date) {
                return this.matches(this.end, date);
            },

            isRangeEdge(date) {
                return this.isStart(date) || this.isEnd(date);
            },

            isInRange(date) {
                const current = this.formatPersianDate(this.year, this.month, date);
                const start = this.start || this.draftStart;
                const end = this.end;

                if (!start || !end) {
                    return false;
                }

                return this.compareDates(current, start) >= 0 && this.compareDates(current, end) <= 0;
            },

            isToday(date) {
                const today = this.getTodayPersian();
                return today.year === parseInt(this.year)
                    && today.month === parseInt(this.month)
                    && today.day === date;
            },

            isDisabled(date) {
                const current = this.formatPersianDate(this.year, this.month, date);

                if (this.min && this.compareDates(current, this.min) < 0) {
                    return true;
                }

                if (this.max && this.compareDates(current, this.max) > 0) {
                    return true;
                }

                return false;
            },

            matches(value, date) {
                const parsed = this.parsePersianDate(value);
                if (!parsed) return false;

                return parsed.year === parseInt(this.year)
                    && parsed.month === parseInt(this.month)
                    && parsed.day === date;
            },

            compareDates(a, b) {
                const left = this.parsePersianDate(a);
                const right = this.parsePersianDate(b);
                if (!left || !right) return 0;

                if (left.year !== right.year) return left.year - right.year;
                if (left.month !== right.month) return left.month - right.month;
                return left.day - right.day;
            },

            canGoPrevious() {
                if (!this.min) return true;
                const prev = this.month === 1
                    ? this.formatPersianDate(this.year - 1, 12, 1)
                    : this.formatPersianDate(this.year, this.month - 1, 1);
                const min = this.parsePersianDate(this.min);

                return !min || this.compareDates(this.formatPersianDate(min.year, min.month, 1), prev) <= 0;
            },

            canGoNext() {
                if (!this.max) return true;
                const next = this.month === 12
                    ? this.formatPersianDate(this.year + 1, 1, 1)
                    : this.formatPersianDate(this.year, this.month + 1, 1);
                const max = this.parsePersianDate(this.max);

                return !max || this.compareDates(next, this.formatPersianDate(max.year, max.month, this.getDaysInPersianMonth(max.year, max.month))) <= 0;
            },

            nextMonth() {
                if (parseInt(this.month) === 12) {
                    this.year = parseInt(this.year) + 1;
                    this.month = 1;
                } else {
                    this.month = parseInt(this.month) + 1;
                }
                this.getNoOfDays();
            },

            previousMonth() {
                if (parseInt(this.month) === 1) {
                    this.year = parseInt(this.year) - 1;
                    this.month = 12;
                } else {
                    this.month = parseInt(this.month) - 1;
                }
                this.getNoOfDays();
            },

            getNoOfDays() {
                const daysInMonth = this.getDaysInPersianMonth(parseInt(this.year), parseInt(this.month));
                const firstDayDate = this.persianToGregorian(parseInt(this.year), parseInt(this.month), 1);
                const dayOfWeek = firstDayDate.getDay();
                const blankdays = (dayOfWeek + 1) % 7;

                this.blankdays = Array.from({ length: blankdays }, (_, i) => i + 1);
                this.no_of_days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
            },

            getDaysInPersianMonth(year, month) {
                if (month <= 6) return 31;
                if (month <= 11) return 30;
                if (this.isLeapPersianYear(year)) return 30;
                return 29;
            },

            isLeapPersianYear(year) {
                return [1, 5, 9, 13, 17, 22, 26, 30].includes(year % 33);
            },

            getTodayPersian() {
                const today = new Date().toLocaleDateString('fa-IR-u-nu-latn').split('/');
                return {
                    year: parseInt(today[0]),
                    month: parseInt(today[1]),
                    day: parseInt(today[2])
                };
            },

            parsePersianDate(dateStr) {
                if (!dateStr || typeof dateStr !== 'string') return null;

                const parts = dateStr.split(/[\/\-]/);
                if (parts.length !== 3) return null;

                const year = parseInt(parts[0]);
                const month = parseInt(parts[1]);
                const day = parseInt(parts[2]);

                if (isNaN(year) || isNaN(month) || isNaN(day)) return null;

                return { year, month, day };
            },

            formatPersianDate(year, month, day) {
                return `${year}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}`;
            },

            persianToGregorian(jy, jm, jd) {
                let gy = (jy <= 979) ? 621 : 1600;
                jy -= (jy <= 979) ? 0 : 979;
                let days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
                gy += 400 * Math.floor(days / 146097);
                days %= 146097;
                if (days > 36524) {
                    gy += 100 * Math.floor(--days / 36524);
                    days %= 36524;
                    if (days >= 365) days++;
                }
                gy += 4 * Math.floor(days / 1461);
                days %= 1461;
                if (days > 365) {
                    gy += Math.floor((days - 1) / 365);
                    days = (days - 1) % 365;
                }
                let gd = days + 1;
                let sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                let gm;
                for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
                return new Date(gy, gm - 1, gd);
            }
        };
    }
</script>
@endscript
