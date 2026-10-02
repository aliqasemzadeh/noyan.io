# نویان (Noyan)

<div align="center">

![PHP Version](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel Version](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Livewire Version](https://img.shields.io/badge/Livewire-4.x-FB70A9?style=for-the-badge&logo=livewire&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![Flux UI](https://img.shields.io/badge/Flux_UI-v2-10B981?style=for-the-badge)

**پلتفرم مدرن مدیریت سازمانی، چندکسب‌وکاری و مجهز به دستیار هوش مصنوعی**

[ویژگی‌ها](#-ویژگی‌های-کلیدی) • [پشته فنی](#%EF%B8%8F-پشته-فنی-و-پیش‌نیازها) • [نصب و راه‌اندازی](#-راهنمای-نصب-و-راه‌اندازی) • [پیکربندی](#%EF%B8%8F-پیکربندی-محیط-env) • [معماری و ساختار](#%EF%B8%8F-معماری-و-ساختار-پروژه) • [هوش مصنوعی](#-ماژول-دستیار-هوش-مصنوعی-ai-assistant)

</div>

---

## 📌 درباره پروژه

**نویان (Noyan)** یک بستر یکپارچه و مدرن تحت وب برای مدیریت کسب‌وکارها، کاربران و فرآیندهای سازمانی است که با استفاده از آخرین نسخه فریم‌ورک **Laravel 13** و بر پایه **PHP 8.4** پیاده‌سازی شده است. این سامانه با تمرکز بر سرعت بالا، تجربه کاربری روان (SPA-like) از طریق **Livewire 4 (Single-File Components)** و رابط کاربری اختصاصی **Flux UI** همراه با قابلیت‌های نوین **هوش مصنوعی و پردازش صوتی/متنی** طراحی شده است.

---

## ✨ ویژگی‌های کلیدی

### ۱. معماری چندکسب‌وکاری (Multi-Business / Multi-Tenancy)
- پشتیبانی از چند کسب‌وکار به ازای هر کاربر با نقش‌های مستقل سازمانی (`BusinessRole`).
- امکان سوئیچ سریع و ایمن بین کسب‌وکارهای مختلف (`switchBusiness`).
- تفکیک داده‌ها و تنظیمات ارز و سطوح دسترسی بر اساس هر بیزینس.

### ۲. احراز هویت بدون رمز عبور و پیامکی (Passwordless OTP Authentication)
- ورود امن و سریع فقط با شماره موبایل ایرانی با اعتبارسنجی دقیق.
- ارسال پیامک کد یکبار مصرف (OTP) از طریق کلاینت پیامک اختصاصی (**ستارگان**).
- مدیریت زمان انقضا و زمان انتظار مجدد (Cooldown) برای جلوگیری از اسپم.
- حالت دستیار محیط توسعه (Dev Mode Helper) برای تسریع تست‌ها.

### ۳. دستیار هوشمند و ابزارهای Agentic AI
- پشتیبانی بومی از مدل‌های هوش مصنوعی محلی (مانند **Ollama** با مدل `qwen2.5:1.5b`) و ارائه‌دهندگان ابری از طریق **Laravel AI** و **Prism**.
- عامل هوشمند `UserAssistant` با قابلیت درک فرامین متنی و تبدیل صوت به متن (Speech-to-Text).
- تعریف ابزارهای اختصاصی (AI Tools) نظیر ثبت و مدیریت کاربر از طریق پرامپت یا صوت.

### ۴. فرانت‌اند فوق مدرن و تک‌فایلی (Single-File Components)
- استفاده کامل از کامپوننت‌های تک‌فایلی Livewire 4 با ترکیب استایل‌های **Tailwind CSS v4**.
- کامپوننت‌های اختصاصی **Flux UI** (جداول داده با صفحه‌بندی، مودال‌های فست‌فلایوت، فیلدهای پاک‌شونده و فیلترهای زنده).
- تعاملات سمت کاربر با **Alpine.js** و ناوبری بدون بارگذاری مجدد صفحه (`wire:navigate`).

### ۵. مدیریت دسترسی‌ها و رسانه‌ها
- سیستم پیشرفته کنترل دسترسی با **Spatie Laravel Permission**.
- ذخیره‌سازی و مدیریت فایل‌ها با **Spatie Media Library**.
- مدیریت تنظیمات سیستم با **Spatie Laravel Settings**.
- لاگ‌ویوئر حرفه‌ای داخلی (**Log Viewer**) برای مانیتورینگ عملکرد سامانه.
- بومی‌سازی کامل به زبان فارسی و پشتیبانی از تقویم جلالی (**Morilog Jalali**).

---

## 🛠️ پشته فنی و پیش‌نیازها

برای اجرای پروژه، محیط شما باید پیش‌نیازهای زیر را داشته باشد:

| بخش | فناوری / ابزار | نسخه |
| :--- | :--- | :--- |
| **زبان برنامه‌نویسی** | PHP | 8.4 یا بالاتر |
| **فریم‌ورک بک‌اند** | Laravel | 13.x |
| **فریم‌ورک فرانت‌اند** | Livewire (Single-File Components) | 4.x |
| **کتابخانه رابط کاربری** | Flux UI & Lucide Icons | 2.x |
| **استایل‌دهی** | Tailwind CSS & Vite | v4.x / Vite 8 |
| **پایگاه داده** | MySQL / PostgreSQL / SQLite | نسخه استاندارد |
| **ارائه‌دهنده AI** | Ollama / Prism / Laravel AI | - |

---

## 🚀 راهنمای نصب و راه‌اندازی

برای راه‌اندازی پروژه روی محیط محلی، گام‌های زیر را به ترتیب انجام دهید:

### ۱. کلون کردن مخزن
```bash
git clone <repository-url>
cd noyan
```

### ۲. نصب وابستگی‌های PHP و کامپوزر
```bash
composer install
```

### ۳. تنظیم فایل متغیرهای محیطی
یک کپی از فایل `.env.example` ایجاد کرده و کلید برنامه را تولید کنید:
```bash
cp .env.example .env
php artisan key:generate
```

### ۴. اجرای مایگریشن‌ها و سیدرها
```bash
php artisan migrate --seed
```

### ۵. نصب وابستگی‌های فرانت‌اند و ساخت دارایی‌ها
```bash
npm install
npm run build
```

### ۶. اجرای سرور توسعه
برای اجرای همزمان سرور لاراول و فرانت‌اند، دستور زیر را اجرا کنید:
```bash
composer run dev
# یا به صورت دستی:
php artisan serve
npm run dev
```

---

## ⚙️ پیکربندی محیط (.env)

متغیرهای مهم و کلیدی در فایل `.env` به شرح زیر هستند:

### تنظیمات پایگاه داده
```env
DB_CONNECTION=sqlite
# یا در صورت استفاده از MySQL / PostgreSQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=noyan
# DB_USERNAME=root
# DB_PASSWORD=
```

### تنظیمات سرویس پیامک (OTP SMS)
```env
SMS_ENDPOINT=https://api.example.com/sms/send
SMS_TOKEN=your_sms_api_token
SMS_GATEWAY=your_gateway_number
SMS_LOG_CHANNEL=otp
```

### تنظیمات کد یکبار مصرف (OTP)
```env
OTP_EXPIRES_IN_MINUTES=2
OTP_RESEND_COOLDOWN_SECONDS=120
```

### تنظیمات ارائه‌دهنده هوش مصنوعی (AI & Ollama)
```env
AI_DEFAULT_LAB=ollama
OLLAMA_BASE_URL=http://localhost:11434
```

---

## 📂 معماری و ساختار پروژه

```plaintext
noyan/
├── app/
│   ├── Ai/                     # دستیارها و ابزارهای هوش مصنوعی
│   │   ├── Agents/             # عامل‌های هوشمند (مثل UserAssistant)
│   │   └── Tools/              # ابزارهای قابل اجرا توسط Agent (مثل CreateUser)
│   ├── Enums/                  # ساختارهای ثابت (BusinessRole, Currency, ...)
│   ├── Jobs/                   # صف‌ها و پردازش‌های پس‌زمینه (ارسال OTP)
│   ├── Models/                 # مدل‌های Eloquent (User, Business, ...)
│   └── Services/               # سرویس‌ها و کلاینت‌ها (SetareganSmsClient)
├── config/                     # تنظیمات سیستم، OTP، هوش مصنوعی، و مجوزها
├── database/
│   ├── migrations/             # جداول دیتابیس
│   └── seeders/                # سیدرهای اولیه
├── lang/                       # فایل‌های زبان و ترجمه (fa / en)
├── resources/
│   ├── views/
│   │   ├── components/         # کامپوننت‌های Blade و لایه‌بندی
│   │   └── pages/              # صفحات Livewire Single-File Components
├── routes/
│   ├── web.php                 # روت‌های وب و صفحات Livewire
│   └── console.php             # دستورات آرتیسان
└── tests/                      # آزمون‌های Feature و Unit با PHPUnit
```

---

## 🤖 ماژول دستیار هوش مصنوعی (AI Assistant)

نویان مجهز به معماری **Agentic Development** است:
- **تحلیل فرامین:** کاربر می‌تواند به صورت متنی یا صوتی دستوراتی مانند «یک کاربر با شماره ۰۹۱۷۱۲۳۴۵۶۷ اضافه کن» را ارسال کند.
- **اجرای خودکار ابزارها (Tool Calling):** دستیار `UserAssistant` با استفاده از مدل زبانی هوشمند، ورودی را پردازش کرده و ابزار `CreateUser` را فراخوانی می‌کند.
- **پردازش صوت:** فایل‌های صوتی ضبط شده در کلاینت مستقیماً به متن تبدیل شده و در چرخه پردازش هوشمند قرار می‌گیرند.

---

## 🧪 آزمون‌ها و استانداردهای کدنویسی

### اجرای آزمون‌ها (PHPUnit)
برای اطمینان از صحت عملکرد ماژول‌ها و تست‌های تعریف‌شده:
```bash
php artisan test
```

### مرتب‌سازی و استانداردهای کد (Laravel Pint)
برای حفظ یکدستی و رعایت استانداردهای PSR-12 و لاراول:
```bash
vendor/bin/pint --format agent
```

---

## 📄 لایسنس

این پروژه یک نرم‌افزار کدباز تحت لایسنس **[MIT](LICENSE)** می‌باشد.
