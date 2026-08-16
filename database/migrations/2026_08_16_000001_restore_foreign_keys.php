<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore relationships that were skipped because the server created
     * tables with MyISAM. Existing data is validated before any schema change.
     */
    public function up(): void
    {
        $invalid = [];

        foreach ($this->foreignKeys() as [$table, $column, $references, , $nullable]) {
            $query = DB::table("{$table} as child")
                ->leftJoin("{$references} as parent", "child.{$column}", '=', 'parent.id')
                ->whereNull('parent.id');

            if ($nullable) {
                $query->whereNotNull("child.{$column}");
            }

            $count = $query->count();
            if ($count > 0) {
                $invalid["{$table}.{$column} -> {$references}.id"] = $count;
            }
        }

        if ($invalid !== []) {
            $details = collect($invalid)
                ->map(fn (int $count, string $relationship) => "{$relationship}: {$count}")
                ->implode(', ');

            throw new RuntimeException(
                "Foreign-key repair stopped. No schema or data was changed. Invalid existing references: {$details}"
            );
        }

        $tables = collect($this->foreignKeys())
            ->flatMap(fn (array $key) => [$key[0], $key[2]])
            ->unique()
            ->sort()
            ->values();

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }

        foreach ($this->foreignKeys() as [$table, $column, $references, $onDelete]) {
            $this->addForeignKey($table, $column, $references, $onDelete);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->foreignKeys()) as [$table, $column]) {
            $name = $this->constraintName($table, $column);

            if ($this->foreignKeyExists($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($name));
            }
        }
    }

    /** @return array<int, array{string, string, string, string, bool}> */
    private function foreignKeys(): array
    {
        return [
            ['users', 'role_id', 'roles', 'RESTRICT', false],
            ['customer_details', 'user_id', 'users', 'CASCADE', false],
            ['pallets', 'user_id', 'users', 'RESTRICT', true],
            ['pallets', 'current_status_id', 'statuses', 'RESTRICT', false],
            ['pallets', 'ghost_pallet_report_id', 'ghost_pallet_reports', 'SET NULL', true],
            ['audit_logs', 'pallet_id', 'pallets', 'RESTRICT', false],
            ['audit_logs', 'made_by_user_id', 'users', 'SET NULL', true],
            ['audit_logs', 'old_status_id', 'statuses', 'SET NULL', true],
            ['audit_logs', 'new_status_id', 'statuses', 'SET NULL', true],
            ['audit_logs', 'old_client_id', 'users', 'SET NULL', true],
            ['audit_logs', 'new_client_id', 'users', 'SET NULL', true],
            ['service_reports', 'pallet_id', 'pallets', 'RESTRICT', false],
            ['service_reports', 'reported_by_user_id', 'users', 'RESTRICT', false],
            ['service_reports', 'resolved_by_user_id', 'users', 'SET NULL', true],
            ['ghost_pallet_reports', 'user_id', 'users', 'RESTRICT', true],
            ['ghost_pallet_reports', 'paired_pallet_id', 'pallets', 'SET NULL', true],
            ['invoices', 'user_id', 'users', 'RESTRICT', true],
            ['invoice_items', 'invoice_id', 'invoices', 'CASCADE', false],
            ['invoice_items', 'pallet_id', 'pallets', 'SET NULL', true],
            ['role_permissions', 'role_id', 'roles', 'CASCADE', false],
            ['role_permissions', 'module_id', 'modules', 'CASCADE', false],
            ['api_tokens', 'user_id', 'users', 'CASCADE', false],
            ['sessions', 'user_id', 'users', 'SET NULL', true],
            ['calendar_notes', 'created_by_user_id', 'users', 'RESTRICT', false],
            ['calendar_note_user', 'calendar_note_id', 'calendar_notes', 'CASCADE', false],
            ['calendar_note_user', 'user_id', 'users', 'CASCADE', false],
            ['pallet_photos', 'pallet_id', 'pallets', 'RESTRICT', false],
            ['pallet_photos', 'service_report_id', 'service_reports', 'SET NULL', true],
            ['pallet_photos', 'uploaded_by_user_id', 'users', 'RESTRICT', false],
            ['pallet_photos', 'old_status_id', 'statuses', 'SET NULL', true],
            ['pallet_photos', 'new_status_id', 'statuses', 'SET NULL', true],
            ['pallet_photos', 'client_id', 'users', 'SET NULL', true],
            ['delivery_locations', 'pallet_id', 'pallets', 'RESTRICT', false],
            ['delivery_locations', 'created_by_user_id', 'users', 'SET NULL', true],
        ];
    }

    private function addForeignKey(string $table, string $column, string $references, string $onDelete): void
    {
        $name = $this->constraintName($table, $column);

        if ($this->foreignKeyExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $column, $references, $onDelete) {
            $foreign = $blueprint->foreign($column, $name)
                ->references('id')
                ->on($references)
                ->onUpdate('cascade');

            match ($onDelete) {
                'CASCADE' => $foreign->onDelete('cascade'),
                'SET NULL' => $foreign->onDelete('set null'),
                default => $foreign->onDelete('restrict'),
            };
        });
    }

    private function constraintName(string $table, string $column): string
    {
        return "fk_{$table}_{$column}";
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
