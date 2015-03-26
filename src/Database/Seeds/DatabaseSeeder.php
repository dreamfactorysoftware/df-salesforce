<?php
namespace DreamFactory\Rave\SalesforceDb\Database\Seeds;

use Illuminate\Database\Seeder;
use DreamFactory\Rave\Models\ServiceType;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Add the service type
        ServiceType::create(
            [
                'name'           => 'salesforce_db',
                'class_name'     => 'DreamFactory\\Rave\\Salesforce\\Services\\SalesforceDb',
                'config_handler' => 'DreamFactory\\Rave\\Salesforce\\Models\\SalesforceConfig',
                'label'          => 'SalesforceDB',
                'description'    => 'Database service for Salesforce connections.',
                'group'          => 'Databases',
                'singleton'      => false,
            ]
        );
        $this->command->info( 'SalesforceDb service type seeded!' );
    }

}
