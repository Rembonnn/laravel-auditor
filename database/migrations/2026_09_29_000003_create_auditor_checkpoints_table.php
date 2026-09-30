<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rembon\LaravelAuditor\Support\Settings;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return Settings::nullableString('auditor.storage.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->string('table', 64);
            $table->unsignedBigInteger('last_id');
            $table->char('last_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['table', 'id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    private function table(): string
    {
        return Settings::string('auditor.storage.database.tables.checkpoints', 'auditor_checkpoints');
    }
};
