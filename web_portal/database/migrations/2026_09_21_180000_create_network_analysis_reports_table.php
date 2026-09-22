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
        Schema::create('network_analysis_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 50)->default('network_analyzer')->index(); // network_analyzer, net_radar
            $table->string('interface', 32)->nullable();
            $table->integer('duration_seconds')->default(120);
            $table->integer('total_packets')->default(0);
            $table->integer('local_hosts_count')->default(0);
            $table->integer('external_hosts_count')->default(0);
            $table->integer('suspicious_packets')->default(0);
            $table->text('summary_text')->nullable();
            $table->mediumText('report_markdown')->nullable();
            $table->longText('report_data_json')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_analysis_reports');
    }
};
