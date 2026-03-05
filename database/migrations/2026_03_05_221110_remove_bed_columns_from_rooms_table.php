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
    Schema::table('rooms', function (Blueprint $table) { 
        $table->dropColumn(['bed_count','bed_type']); 
    }); 
} 

public function down(): void 
{ 
    Schema::table('rooms', function (Blueprint $table) { 
        $table->integer('bed_count')->default(1)->after('capacity'); 
        $table->string('bed_type')->nullable()->after('bed_count'); 
    }); 
}
};
