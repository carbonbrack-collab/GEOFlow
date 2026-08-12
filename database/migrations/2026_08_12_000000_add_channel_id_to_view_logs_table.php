<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 目标站点的访问日志复用 view_logs，用 channel_id 区分来源站点。
 * source='channel' 表示这条记录来自渠道站点，'local' 仍是本站。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('view_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('view_logs', 'channel_id')) {
                $table->unsignedBigInteger('channel_id')->nullable()->after('article_id');
                $table->index('channel_id');
                $table->index(['channel_id', 'created_at'], 'view_logs_channel_created_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('view_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('view_logs', 'channel_id')) {
                $table->dropIndex('view_logs_channel_created_index');
                $table->dropIndex(['channel_id']);
                $table->dropColumn('channel_id');
            }
        });
    }
};
