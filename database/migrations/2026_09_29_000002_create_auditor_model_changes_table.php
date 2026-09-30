<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
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
            $table->ulid('ulid')->unique();
            $table->foreignId('entry_id')
                ->nullable()
                ->constrained(Settings::string('auditor.storage.database.tables.entries', 'auditor_entries'))
                ->nullOnDelete();
            $table->string('correlation_id', 64)->index();
            $table->string('auditable_type');
            $this->morphKey($table, 'auditable_id');
            $table->string('event', 16)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('user_type')->nullable();
            $this->morphKey($table, 'user_id')->nullable();
            $table->timestamp('created_at', 6)->index();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->nullable()->index();

            $table->index(['auditable_type', 'auditable_id', 'id']);
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    private function table(): string
    {
        return Settings::string('auditor.storage.database.tables.model_changes', 'auditor_model_changes');
    }

    private function morphKey(Blueprint $table, string $column): ColumnDefinition
    {
        return match (config('auditor.storage.database.morph_key_type', 'string')) {
            'int' => $table->unsignedBigInteger($column),
            'uuid' => $table->uuid($column),
            'ulid' => $table->ulid($column),
            default => $table->string($column, 64),
        };
    }
};
