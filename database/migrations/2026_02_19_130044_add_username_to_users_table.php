<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 30)->nullable()->after('id');
            });
        }

        $users = DB::table('users')
            ->select('id', 'name')
            ->get();

        foreach ($users as $user) {
            if (DB::table('users')->where('id', $user->id)->whereNotNull('username')->exists()) {
                continue;
            }

            $base = Str::of((string) $user->name)
                ->lower()
                ->replaceMatches('/[^a-z0-9_]/', '_')
                ->trim('_')
                ->substr(0, 20)
                ->value();

            if ($base === '') {
                $base = "user{$user->id}";
            }

            $candidate = $base;
            $suffix = 1;

            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = Str::limit($base, 20 - strlen((string) $suffix), '').$suffix;
                $suffix++;
            }

            DB::table('users')->where('id', $user->id)->update([
                'username' => $candidate,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
