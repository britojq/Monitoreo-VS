<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('domain', 255)->unique();
            $table->unsignedInteger('port')->default(443);
            $table->string('subject_cn', 255)->nullable();
            $table->string('subject_org', 255)->nullable();
            $table->string('subject_ou', 255)->nullable();
            $table->string('subject_country', 10)->nullable();
            $table->string('subject_state', 100)->nullable();
            $table->string('subject_locality', 100)->nullable();
            $table->string('issuer_cn', 255)->nullable();
            $table->string('issuer_org', 255)->nullable();
            $table->string('issuer_country', 10)->nullable();
            $table->string('serial_number', 255)->nullable();
            $table->string('signature_algorithm', 100)->nullable();
            $table->string('public_key_algorithm', 100)->nullable();
            $table->unsignedInteger('public_key_bits')->nullable();
            $table->unsignedInteger('version')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->integer('days_remaining')->default(0);
            $table->boolean('is_self_signed')->default(false);
            $table->boolean('is_wildcard')->default(false);
            $table->boolean('is_ev')->default(false);
            $table->json('san_entries')->nullable();
            $table->string('fingerprint_sha256', 64)->nullable();
            $table->string('fingerprint_sha1', 40)->nullable();
            $table->mediumText('pem_certificate')->nullable();
            $table->unsignedInteger('alert_threshold_warning')->default(30);
            $table->unsignedInteger('alert_threshold_critical')->default(7);
            $table->timestamp('last_checked_at')->nullable();
            $table->enum('last_check_status', ['success', 'error', 'expired', 'expiring_soon', 'hostname_mismatch'])->nullable();
            $table->unsignedInteger('consecutive_errors')->default(0);
            $table->unsignedInteger('renewal_count')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('monitored_services')->onDelete('set null');
            $table->index('service_id', 'idx_ssl_service');
            $table->index('valid_to', 'idx_ssl_valid_to');
            $table->index('days_remaining', 'idx_ssl_days_remaining');
            $table->index('last_check_status', 'idx_ssl_status');
        });

        Schema::create('ssl_certificate_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ssl_certificate_id');
            $table->enum('event_type', [
                'initial_discovery',
                'renewal',
                'expiration_warning',
                'expired',
                'issuer_changed',
                'hostname_mismatch',
                'error',
                'recovered'
            ]);
            $table->string('previous_fingerprint', 64)->nullable();
            $table->string('new_fingerprint', 64)->nullable();
            $table->timestamp('previous_valid_to')->nullable();
            $table->timestamp('new_valid_to')->nullable();
            $table->integer('days_remaining_at_event')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->foreign('ssl_certificate_id')->references('id')->on('ssl_certificates')->onDelete('cascade');
            $table->index('ssl_certificate_id', 'idx_ssl_hist_cert');
            $table->index('event_type', 'idx_ssl_hist_event');
            $table->index('occurred_at', 'idx_ssl_hist_occurred');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssl_certificate_history');
        Schema::dropIfExists('ssl_certificates');
    }
};
