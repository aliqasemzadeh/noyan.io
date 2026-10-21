<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('number_format', 20)->default('year_month');
            $table->unsignedTinyInteger('number_pad')->default(4);
            $table->string('sale_prefix', 20)->nullable();
            $table->string('purchase_prefix', 20)->nullable();
            $table->string('quote_prefix', 20)->nullable();
            $table->boolean('email_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 10)->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_from_address')->nullable();
            $table->string('smtp_from_name')->nullable();
            $table->string('new_invoice_email_subject')->nullable();
            $table->text('new_invoice_email_body')->nullable();
            $table->text('new_invoice_sms_body')->nullable();
            $table->string('payment_reminder_email_subject')->nullable();
            $table->text('payment_reminder_email_body')->nullable();
            $table->text('payment_reminder_sms_body')->nullable();
            $table->string('payment_receipt_email_subject')->nullable();
            $table->text('payment_receipt_email_body')->nullable();
            $table->text('payment_receipt_sms_body')->nullable();
            $table->unsignedSmallInteger('reminder_days_before')->default(3);
            $table->boolean('reminder_on_due')->default(true);
            $table->unsignedSmallInteger('reminder_days_after')->default(1);
            $table->boolean('reminder_email_enabled')->default(true);
            $table->boolean('reminder_sms_enabled')->default(false);
            $table->text('invoice_footer')->nullable();
            $table->timestamps();

            $table->unique('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_invoice_settings');
    }
};
