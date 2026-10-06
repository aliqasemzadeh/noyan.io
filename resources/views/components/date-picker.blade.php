@props([
    'name' => null,
    'label' => null,
    'placeholder' => null,
    'description' => null,
    'size' => null,
    'required' => false,
    'value' => null,
])

@php
    $wireModel = $attributes->wire('model');
    $wireModelName = $wireModel->value();
    $name ??= $wireModelName ? (string) str($wireModelName)->afterLast('.') : null;
    $placeholder ??= __('general.select_date');
    $entangle = $wireModelName ? "\$wire.entangle('{$wireModelName}')" : 'null';
@endphp

<div
    class="w-full"
    x-data="window.persianDatePicker({{ $entangle }}, @js($value))"
    x-cloak
>
    <flux:field :$name>
        @if ($label)
            <flux:label>
                {{ $label }}
                @if ($required)
                    <span class="text-red-500" title="required">*</span>
                @endif
            </flux:label>
        @endif

        @if ($description)
            <flux:description>{{ $description }}</flux:description>
        @endif

        <div class="relative w-full">
            <div class="relative">
                <flux:input
                    readonly
                    x-on:click="showDatepicker = !showDatepicker"
                    x-on:keydown.escape="showDatepicker = false"
                    x-bind:value="datepickerValue"
                    :$placeholder
                    :$name
                    :$size
                    :required="$required"
                    :invalid="$name ? $errors->has($name) : false"
                    x-on:keydown.backspace.prevent="clearDate()"
                    x-on:keydown.delete.prevent="clearDate()"
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
                        <template x-if="datepickerValue">
                            <button
                                type="button"
                                x-on:click.stop="clearDate()"
                                class="h-4 w-4 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </template>
                    </x-slot>
                </flux:input>
            </div>

            <div
                class="absolute z-50 mt-2 w-[280px] rounded-md border border-zinc-200 bg-white p-3 text-zinc-950 shadow-md outline-none dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50"
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
                    <button type="button" x-on:click="previousMonth()" class="h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100 transition-opacity">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m9 18 6-6-6-6"/></svg>
                    </button>

                    <div class="flex items-center gap-1">
                        <select x-model.number="month" x-on:change="getNoOfDays()" class="bg-transparent border-none p-0 text-sm font-medium focus:ring-0 cursor-pointer dark:bg-zinc-950">
                            <template x-for="(name, index) in MONTH_NAMES" :key="index">
                                <option :value="index + 1" x-text="name" :selected="index + 1 === month"></option>
                            </template>
                        </select>
                        <select x-model.number="year" x-on:change="getNoOfDays()" class="bg-transparent border-none p-0 text-sm font-medium focus:ring-0 cursor-pointer dark:bg-zinc-950">
                            <template x-for="y in years" :key="y">
                                <option :value="y" x-text="y" :selected="y === year"></option>
                            </template>
                        </select>
                    </div>

                    <button type="button" x-on:click="nextMonth()" class="h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100 transition-opacity">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center">
                    <template x-for="day in DAYS" :key="day">
                        <div class="text-zinc-500 dark:text-zinc-400 rounded-md w-9 h-9 flex items-center justify-center text-[0.8rem] font-normal" x-text="day"></div>
                    </template>

                    <template x-for="blankday in blankdays">
                        <div class="w-9 h-9"></div>
                    </template>

                    <template x-for="(date, dateIndex) in no_of_days" :key="dateIndex">
                        <button
                            type="button"
                            x-on:click="getDateValue(date)"
                            class="h-9 w-9 p-0 font-normal aria-selected:opacity-100 transition-colors rounded-md text-sm flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 dark:focus-visible:ring-zinc-300"
                            :class="{
                                'bg-zinc-900 text-zinc-50 hover:bg-zinc-900 hover:text-zinc-50 focus:bg-zinc-900 focus:text-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-50 dark:hover:text-zinc-900 dark:focus:bg-zinc-50 dark:focus:text-zinc-900': isSelected(date),
                                'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-50': isToday(date) && !isSelected(date),
                                'hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-50': !isSelected(date)
                            }"
                            x-text="date"
                        ></button>
                    </template>
                </div>
            </div>
        </div>
        <flux:error :$name />
    </flux:field>
</div>

@script
<script>
    window.persianDatePicker = function(datepickerValue, initialValue) {
        return {
            showDatepicker: false,
            datepickerValue: datepickerValue,
            month: '',
            year: '',
            years: [],
            no_of_days: [],
            blankdays: [],
            MONTH_NAMES: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
            DAYS: ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'],

            init() {
                if (!this.datepickerValue && initialValue) {
                    this.datepickerValue = initialValue;
                }

                const today = this.getTodayPersian();
                let initYear = parseInt(today.year);
                let initMonth = parseInt(today.month);

                if (this.datepickerValue) {
                    const d = this.parsePersianDate(this.datepickerValue);
                    if (d) {
                        initMonth = parseInt(d.month);
                        initYear = parseInt(d.year);
                    }
                }

                this.year = initYear;
                this.month = initMonth;
                this.updateYears(this.year);
                this.getNoOfDays();

                this.$nextTick(() => {
                    this.year = initYear;
                    this.month = initMonth;
                });

                this.$watch('datepickerValue', (value) => {
                    if (value) {
                        const d = this.parsePersianDate(value);
                        if (d) {
                            this.month = parseInt(d.month);
                            this.year = parseInt(d.year);
                            this.updateYears(this.year);
                            this.getNoOfDays();
                        }
                    }
                });

                this.$watch('year', (value) => {
                    this.updateYears(value);
                });
            },

            updateYears(year) {
                const targetYear = parseInt(year) || this.getTodayPersian().year;

                if (!this.years.includes(targetYear) ||
                    this.years[0] > targetYear - 20 ||
                    this.years[this.years.length - 1] < targetYear + 20) {
                    this.years = Array.from({length: 101}, (_, i) => (targetYear - 50) + i);
                }
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

            isToday(date) {
                const today = this.getTodayPersian();
                return today.year === parseInt(this.year) && today.month === parseInt(this.month) && today.day === date;
            },

            isSelected(date) {
                const sel = this.parsePersianDate(this.datepickerValue);
                if (!sel) return false;
                return sel.year === parseInt(this.year) && sel.month === parseInt(this.month) && sel.day === date;
            },

            getDateValue(date) {
                this.datepickerValue = this.formatPersianDate(this.year, this.month, date);
                this.showDatepicker = false;
            },

            clearDate() {
                this.datepickerValue = '';
                this.showDatepicker = false;
                const today = this.getTodayPersian();
                this.month = today.month;
                this.year = today.year;
                this.updateYears(this.year);
                this.getNoOfDays();
            },

            getNoOfDays() {
                let daysInMonth = this.getDaysInPersianMonth(parseInt(this.year), parseInt(this.month));
                let firstDayDate = this.persianToGregorian(parseInt(this.year), parseInt(this.month), 1);
                let dayOfWeek = firstDayDate.getDay();
                let blankdays = (dayOfWeek + 1) % 7;

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
