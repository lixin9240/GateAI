<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edge_nodes', function (Blueprint $table) {
            $table->string('api_secret', 64)->nullable()->after('edge_version')->comment('边缘端API签名密钥');
        });
    }

    public function down(): void
    {
        Schema::table('edge_nodes', function (Blueprint $table) {
            $table->dropColumn('api_secret');
        });
    }
};
