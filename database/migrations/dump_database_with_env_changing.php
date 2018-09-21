<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Process\Process;

class DumpDatabaseWithEnvChanging extends Migration
{
    /** @var string new database config name (for migration) */
    protected $newConfigName;
    /** @var string old database name */
    protected $oldDbName;
    /** @var string new database name */
    protected $newDbName;
    /** @var string database user */
    protected $user;
    /** @var string database user password */
    protected $password;
    /** @var array with ordered list of tables */
    protected $tables = [
        'table1',
        'table2',
        'table3',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->fillConfigurations();

        /**
         * Create new schema.
         */
        $createNewSchema = new Process("echo \"CREATE DATABASE IF NOT EXISTS {$this->newDbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\" | mysql -u {$this->user} -p{$this->password}");
        $createNewSchema->run();

        /**
         * Seed new table structure.
         */
        $this->seedTablesStructure();
        $migrationSuccess = false;

        /**
         * Lock tables for preventing clients access to empty tables.
         */
        try {
            \DB::getPdo()->beginTransaction();
            \DB::getPdo()->exec($this->getLockTablesQuery());

            $this->switchEnvTables();

            /**
             * We can not use shell because it creates sub-process with another PID. MYSQL table locks allows to access to table only from process created LOCK
             * So we can't gain access to locked within parent process table from child process created by shell_exec / exec / Symfony.Process component.
             */

            /**
             * Migrate tables with copying them by SQL
             * We use IGNORE word for preventing errors occuring while inserting row with save id.
             */
            $this->migrateTables();
            $migrationSuccess = true;
        } catch (\Exception $e) {
            echo 'Error occurred: ' . PHP_EOL . $e->getMessage() . PHP_EOL;
        } finally {
            /**
             * Release tables locks.
             */
            \DB::getPdo()->exec('UNLOCK TABLES');
            \DB::getPdo()->commit();

            if ($migrationSuccess) {
                $this->updateMigrationTable();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->switchEnvTables();
    }

    /**
     * Fill configurations for database access.
     *
     * @throws ConfigIsNotProperlyDefinedException
     * @throws EnvFileIsNotProperlyDefinedException
     */
    protected function fillConfigurations()
    {
        if (config('database.connections.mysql_dump') === null) {
            throw new ConfigIsNotProperlyDefinedException('[database.connections.mysql_dump]');
        }

        $this->newConfigName = 'mysql_dump';
        $this->oldDbName     = env('DB_DATABASE', null);
        $this->newDbName     = env('DB_DUMP_DATABASE', null);
        $this->user          = env('DB_USERNAME', null);
        $this->password      = env('DB_PASSWORD', null);

        if ($this->oldDbName === null
            || $this->newDbName === null
            || $this->user === null
            || $this->password === null) {
            throw new EnvFileIsNotProperlyDefinedException('[DB_DATABASE, DB_DUMP_DATABASE, DB_USERNAME, DB_PASSWORD]');
        }
    }

    /**
     * Get SQL query for locking tables for migration.
     *
     * @return string
     */
    protected function getLockTablesQuery()
    {
        $newDbName = $this->newDbName;
        $oldDbName = $this->oldDbName;

        $tablesLocks = array_map(function ($table) use ($newDbName, $oldDbName) {
            return ["{$newDbName}.{$table} WRITE", "{$oldDbName}.{$table} WRITE"];
        }, $this->tables);

        $tablesLocks = array_collapse($tablesLocks);
        return 'LOCK TABLE ' . implode(',', $tablesLocks);
    }

    /**
     * Process tables migration
     * Based on current instance $tables ordered array.
     */
    protected function migrateTables()
    {
        foreach ($this->tables as $table) {
            \DB::getPdo()->exec("INSERT IGNORE INTO {$this->newDbName}.{$table} SELECT * FROM {$this->oldDbName}.{$table}");
        }
    }

    /**
     * Copy only migration table for allowing to get fresh migrated rows.
     */
    protected function updateMigrationTable()
    {
        $migration   = str_replace('.php', '', basename(__FILE__));
        $batchResult = \DB::select("SELECT max(batch) as batch FROM {$this->newDbName}.migrations");
        $batch       = $batchResult[0]->batch;

        \DB::getPdo()->exec("INSERT INTO {$this->newDbName}.migrations (migration, batch) VALUES ('{$migration}', {$batch} + 1)");
    }

    /**
     * Switch database tables within .env file.
     */
    protected function switchEnvTables()
    {
        /**
         * Update .env file for allowing new users requests to hang within locking cycle.
         */
        $this->updateDotEnv('DB_DATABASE', $this->newDbName); // switch .env database names
        $this->updateDotEnv('DB_DUMP_DATABASE', $this->oldDbName);

        \Artisan::call('config:clear'); // clear .env file cache for allowing new requests to receive fresh config
    }

    /**
     * Update .env file by environment variable key.
     *
     * @param        $key
     * @param        $newValue
     * @param string $delim
     */
    protected function updateDotEnv($key, $newValue, $delim='')
    {
        $path       = base_path('.env');
        $oldValue   = env($key); // get old value from current env

        if ($oldValue === $newValue) { // was there any change?
            return;
        }

        if (file_exists($path)) { // rewrite file content with changed data
            file_put_contents( // replace current value with new value
                $path,
                str_replace(
                    $key . '=' . $delim . $oldValue . $delim,
                    $key . '=' . $delim . $newValue . $delim,
                    file_get_contents($path)
                )
            );
        }
    }

    /**
     * Seed tables structure within sub-processes.
     */
    protected function seedTablesStructure()
    {
        $getStructureDump = new Process("mysqldump -u {$this->user} -p{$this->password} --no-data {$this->oldDbName} > {$this->oldDbName}.dump");
        $getStructureDump->run();

        $fillStructureDump = new Process("mysql -u {$this->user} -p{$this->password} {$this->newDbName} < {$this->oldDbName}.dump");
        $fillStructureDump->run();

        $removeDumpFile = new Process("rm -f {$this->oldDbName}.dump");
        $removeDumpFile->run();
    }
}
