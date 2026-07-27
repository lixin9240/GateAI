<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 将 account 唯一索引改为仅对未删除记录生效，允许软删除后复用同名账号。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 先删掉旧的简单唯一索引
            $table->dropUnique('users_account_unique');
        });

        // 虚拟列：未删除时 = account，软删除时 = NULL
        // MySQL 唯一索引对 NULL 不判重，因此软删除后可创建同名账号
        DB::statement(
            "ALTER TABLE `users` ADD `account_active` VARCHAR(50) "
            . "GENERATED ALWAYS AS (IF(`deleted_at` IS NULL, `account`, NULL)) VIRTUAL"
        );

        Schema::table('users', function (Blueprint $table) {
            $table->unique('account_active', 'users_account_active_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_account_active_unique');
            $table->dropColumn('account_active');
            $table->unique('account', 'users_account_unique');
        });
    }
};
