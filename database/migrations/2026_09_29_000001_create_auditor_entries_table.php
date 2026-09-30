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
            $table->string('correlation_id', 64)->index();
            $table->string('type', 16)->index();
            $table->string('name')->nullable()->index();
            $table->string('user_type')->nullable();
            $this->morphKey($table, 'user_id')->nullable();
            $table->string('guard', 64)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->text('url')->nullable();
            $table->string('route_action')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('failed')->default(false)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('os_user', 64)->nullable();
            $table->string('hostname')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('abilities')->nullable();
            $table->json('models_accessed')->nullable();
            $table->json('mails')->nullable();
            $table->json('notifications')->nullable();
            $table->json('input')->nullable();
            $table->json('properties')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedSmallInteger('denied_abilities_count')->default(0)->index();
            $table->unsignedInteger('model_changes_count')->default(0);
            $table->timestamp('started_at', 6);
            $table->timestamp('completed_at', 6)->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->nullable()->index();
            $table->timestamp('created_at', 6)->index();

            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    private function table(): string
    {
        return Settings::string('auditor.storage.database.tables.entries', 'auditor_entries');
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
